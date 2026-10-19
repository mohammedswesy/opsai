<?php

namespace App\AI\Services;

use App\AI\Data\AIToolContext;
use App\AI\Tools\AIToolRegistry;
use App\Enums\AiActionStatus;
use App\Enums\AiMessageRole;
use App\Models\AiAction;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AIActionService
{
    public function __construct(private readonly AIToolRegistry $tools) {}

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function propose(?User $user, string $conversationId, string $toolName, array $arguments, string $idempotencyKey): AiAction
    {
        $existing = AiAction::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return $existing;
        }

        return AiAction::query()->create([
            'user_id' => $user?->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'action_type' => 'write',
            'arguments' => $arguments,
            'status' => AiActionStatus::Pending,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    public function confirm(AiAction $action, User $user, ?string $branchId): AiAction
    {
        $this->guardOwner($action, $user);

        return $this->executeConfirmed($action, $user, $branchId);
    }

    private function executeConfirmed(AiAction $action, ?User $user, ?string $branchId): AiAction
    {
        return DB::transaction(function () use ($action, $user, $branchId) {
            $locked = AiAction::query()->whereKey($action->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === AiActionStatus::Executed) {
                return $locked;
            }

            if ($locked->status !== AiActionStatus::Pending) {
                throw ValidationException::withMessages([
                    'action' => __('This action can no longer be confirmed.'),
                ]);
            }

            $tool = $this->tools->get($locked->tool_name);
            $locked->status = AiActionStatus::Approved;
            $locked->approved_at = now();
            $locked->save();

            if ($tool === null) {
                return $this->fail($locked, 'That action is no longer available.');
            }

            $prepared = $this->tools->prepare($tool, $locked->arguments ?? []);

            if ($prepared['error'] !== null) {
                return $this->fail($locked, $prepared['error']);
            }

            $locked->status = AiActionStatus::Executing;
            $locked->save();

            $conversation = $locked->conversation;
            $channel = $conversation?->channel === 'customer' ? 'customer' : 'staff';
            $result = $this->tools->execute($tool, $prepared['arguments'], (new AIToolContext(
                $channel === 'customer' ? null : $user,
                $branchId,
                false,
                $channel,
                $conversation?->customer,
            ))->withAllBranches($prepared['all_branches']));
            $locked->result = $result;
            $locked->executed_at = now();
            $locked->status = ($result['success'] ?? false) === true ? AiActionStatus::Executed : AiActionStatus::Failed;
            $locked->save();

            $title = is_array($result['data'] ?? null) ? ($result['data']['title'] ?? $result['data']['subject'] ?? null) : null;
            AiMessage::query()->create([
                'conversation_id' => $locked->conversation_id,
                'role' => AiMessageRole::Assistant,
                'content' => ($result['success'] ?? false) === true
                    ? 'Done. '.($title ? (string) $title.' was saved.' : 'The action was saved.')
                    : (string) ($result['message'] ?? 'The action could not be completed.'),
                'metadata' => [
                    'source' => $result['source'] ?? $locked->tool_name,
                    'action_id' => $locked->id,
                    'generated_at' => now()->toIso8601String(),
                ],
            ]);

            return $locked;
        });
    }

    public function confirmForCustomer(AiAction $action): AiAction
    {
        $conversation = $action->conversation;

        if ($action->user_id !== null || $conversation === null || $conversation->channel !== 'customer') {
            abort(403);
        }

        return $this->executeConfirmed($action, null, $conversation->customer?->branch_id);
    }

    public function reject(AiAction $action, User $user): AiAction
    {
        $this->guardOwner($action, $user);

        if ($action->status !== AiActionStatus::Pending) {
            throw ValidationException::withMessages([
                'action' => __('This action can no longer be cancelled.'),
            ]);
        }

        $action->status = AiActionStatus::Rejected;
        $action->save();

        return $action;
    }

    private function fail(AiAction $action, string $message): AiAction
    {
        $action->status = AiActionStatus::Failed;
        $action->result = ['success' => false, 'message' => $message];
        $action->executed_at = now();
        $action->save();

        return $action;
    }

    private function guardOwner(AiAction $action, User $user): void
    {
        if ($action->user_id !== $user->id) {
            abort(403);
        }
    }
}
