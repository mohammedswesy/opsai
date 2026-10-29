<?php

namespace App\AI\Services;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIMessage as PromptMessage;
use App\AI\Data\AIToolCall;
use App\AI\Data\AIToolContext;
use App\AI\Exceptions\AIException;
use App\AI\Prompts\CustomerAssistantPrompt;
use App\AI\Prompts\OperationsAssistantPrompt;
use App\AI\Support\ReplyLanguage;
use App\AI\Tools\AIToolRegistry;
use App\Enums\AiActionStatus;
use App\Enums\AiConversationStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIConversationService
{
    public function __construct(
        private readonly AIProviderInterface $provider,
        private readonly AIToolRegistry $tools,
        private readonly AIContextService $context,
        private readonly AIActionService $actions,
        private readonly OperationsAssistantPrompt $prompt,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @param  string|null  $locale  The user's interface locale, used when the message has no clear language.
     * @return array<string, mixed>
     */
    public function chat(User $user, array $input, ?string $branchId, ?string $locale = null): array
    {
        $conversation = $this->conversation($user, $input['conversation_id'] ?? null, $input['message']);

        return $this->accept($conversation, $input['message'], new AIToolContext($user, $branchId), $locale);
    }

    /**
     * @param  string|null  $locale  Fallback reply language; defaults to the organization locale.
     * @return array<string, mixed>
     */
    public function accept(AiConversation $conversation, string $message, AIToolContext $toolContext, ?string $locale = null): array
    {
        $language = ReplyLanguage::resolve($message, $locale ?? OrganizationContext::current()?->organization->locale);
        $userMessage = $conversation->messages()->create([
            'role' => AiMessageRole::User,
            'content' => $message,
        ]);

        $started = microtime(true);
        $toolsUsed = [];
        $pending = null;
        $calls = 0;
        $maxCalls = max(1, (int) config('ai.max_tool_calls'));

        try {
            while ($calls <= $maxCalls) {
                $response = $this->provider->complete($this->transcript($conversation, $toolContext->branchId, $language), $this->tools->definitionsFor($toolContext));
                $calls++;

                if ($response->toolCalls === []) {
                    $assistant = $this->storeAssistant($conversation, $response->content ?: __('I do not have that information.', [], $language->code), $response, $started, $toolsUsed);

                    return $this->payload($conversation, $assistant, null, $toolsUsed);
                }

                if ($calls > $maxCalls) {
                    break;
                }

                $conversation->messages()->create([
                    'role' => AiMessageRole::Assistant,
                    'content' => $response->content,
                    'tool_calls' => array_map(fn (AIToolCall $call) => $call->toArray(), $response->toolCalls),
                    'metadata' => $this->usage($response, $started),
                ]);

                foreach ($response->toolCalls as $call) {
                    $toolsUsed[] = $call->name;
                    $outcome = $this->handleTool($conversation, $toolContext->user, $userMessage, $call, $toolContext);

                    if ($outcome instanceof AiAction) {
                        $pending = $outcome;
                        $assistant = $this->storeAssistant($conversation, __('Please confirm this action before I save it.', [], $language->code), $response, $started, $toolsUsed);

                        return $this->payload($conversation, $assistant, $pending, $toolsUsed);
                    }
                }
            }
        } catch (AIException $exception) {
            Log::warning('ai.provider_error', [
                'reason' => $exception->context['reason'] ?? 'provider_error',
                'user_id' => $toolContext->user?->id,
            ]);

            throw $exception;
        }

        $assistant = $this->storeAssistant($conversation, __('I stopped because that request needed too many lookups.', [], $language->code), null, $started, $toolsUsed);

        return $this->payload($conversation, $assistant, $pending, $toolsUsed);
    }

    private function conversation(User $user, mixed $id, string $message): AiConversation
    {
        if (is_string($id) && $id !== '') {
            $conversation = AiConversation::query()->whereKey($id)->first();

            if ($conversation === null) {
                abort(404);
            }

            if ($conversation->user_id !== $user->id) {
                abort(403);
            }

            return $conversation;
        }

        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'channel' => 'staff',
            'title' => Str::limit($message, 80, ''),
            'status' => AiConversationStatus::Active,
        ]);

        $conversation->messages()->create([
            'role' => AiMessageRole::System,
            'content' => $this->prompt->text([]),
        ]);

        return $conversation;
    }

    /**
     * @return list<PromptMessage>
     */
    private function transcript(AiConversation $conversation, ?string $branchId, ReplyLanguage $language): array
    {
        $instructions = $conversation->channel === 'customer'
            ? app(CustomerAssistantPrompt::class)->text($this->context->describeCustomer($conversation->customer))
            : $this->prompt->text($this->context->describe($branchId));

        $messages = [
            new PromptMessage('system', $instructions),
            new PromptMessage('system', $language->rule()),
        ];

        foreach ($conversation->messages()->orderBy('id')->get() as $message) {
            if ($message->role === AiMessageRole::System) {
                continue;
            }

            $calls = [];
            foreach ($message->tool_calls ?? [] as $call) {
                if (is_array($call) && is_string($call['id'] ?? null) && is_string($call['name'] ?? null) && is_array($call['arguments'] ?? null)) {
                    $calls[] = new AIToolCall($call['id'], $call['name'], $call['arguments']);
                }
            }

            $messages[] = new PromptMessage(
                $message->role->value,
                $message->content,
                $calls,
                is_array($message->metadata) ? ($message->metadata['tool_call_id'] ?? null) : null,
                $message->metadata,
            );
        }

        return $messages;
    }

    private function handleTool(AiConversation $conversation, ?User $user, AiMessage $userMessage, AIToolCall $call, AIToolContext $toolContext): ?AiAction
    {
        $tool = $this->tools->get($call->name);

        if ($tool === null) {
            $this->storeTool($conversation, $call, ['success' => false, 'message' => 'That tool is not available.']);

            return null;
        }

        $prepared = $this->tools->prepare($tool, $call->arguments);

        if ($prepared['error'] !== null) {
            $this->storeTool($conversation, $call, ['success' => false, 'message' => $prepared['error']]);
            $this->actions->propose($user, $conversation->id, $tool->name(), $call->arguments, $this->key($userMessage, $call).'-invalid');
            AiAction::query()->where('idempotency_key', $this->key($userMessage, $call).'-invalid')->update([
                'status' => AiActionStatus::Failed->value,
                'result' => ['success' => false, 'message' => $prepared['error']],
            ]);

            return null;
        }

        if (! $this->tools->allows($tool, $toolContext)) {
            $this->storeTool($conversation, $call, ['success' => false, 'message' => 'You are not allowed to do that.']);

            return null;
        }

        if ($tool->requiresConfirmation()) {
            return $this->actions->propose(
                $user,
                $conversation->id,
                $tool->name(),
                $prepared['arguments'],
                $this->key($userMessage, $call),
            );
        }

        $scoped = $toolContext->withAllBranches($prepared['all_branches']);
        $result = $this->tools->execute($tool, $prepared['arguments'], $scoped);
        $this->storeTool($conversation, $call, $result);

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function storeTool(AiConversation $conversation, AIToolCall $call, array $result): void
    {
        $conversation->messages()->create([
            'role' => AiMessageRole::Tool,
            'content' => json_encode($result),
            'tool_results' => $result,
            'metadata' => [
                'tool' => $call->name,
                'tool_call_id' => $call->id,
                'source' => $result['source'] ?? null,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $toolsUsed
     */
    private function storeAssistant(AiConversation $conversation, string $content, mixed $response, float $started, array $toolsUsed): AiMessage
    {
        return $conversation->messages()->create([
            'role' => AiMessageRole::Assistant,
            'content' => $content,
            'metadata' => [
                'tools' => $toolsUsed,
                'generated_at' => now()->toIso8601String(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                ...($response ? $this->usage($response, $started) : []),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function usage(mixed $response, float $started): array
    {
        return [
            'model' => $response->model,
            'usage' => $response->usage,
            'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @param  list<string>  $toolsUsed
     * @return array<string, mixed>
     */
    private function payload(AiConversation $conversation, AiMessage $assistant, ?AiAction $action, array $toolsUsed): array
    {
        $tool = $action ? $this->tools->get($action->tool_name) : null;

        return [
            'conversation_id' => $conversation->id,
            'message' => [
                'id' => $assistant->id,
                'role' => 'assistant',
                'content' => $assistant->content,
            ],
            'tools_used' => $toolsUsed,
            'pending_action' => $action === null || $action->status !== AiActionStatus::Pending ? null : [
                'id' => $action->id,
                'tool_name' => $action->tool_name,
                'preview' => $tool?->preview($action->arguments ?? []) ?? [],
                'status' => $action->status->value,
            ],
        ];
    }

    private function key(AiMessage $message, AIToolCall $call): string
    {
        return hash('sha256', $message->id.'|'.$call->name.'|'.json_encode($call->arguments));
    }
}
