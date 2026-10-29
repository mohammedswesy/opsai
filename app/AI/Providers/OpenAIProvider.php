<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIMessage;
use App\AI\Data\AIResponse;
use App\AI\Data\AIToolCall;
use App\AI\Exceptions\AIException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    public function complete(array $messages, array $tools): AIResponse
    {
        $apiKey = config('ai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            Log::warning('ai.openai.config', ['reason' => 'missing_api_key']);

            throw AIException::because('missing_api_key', 503);
        }

        $payload = [
            'model' => config('ai.model'),
            'input' => $this->input($messages),
            'store' => false,
        ];
        $instructions = $this->instructions($messages);

        if ($instructions !== '') {
            $payload['instructions'] = $instructions;
        }

        if ($tools !== []) {
            $payload['tools'] = array_map(fn (array $tool): array => [
                'type' => 'function',
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
                'parameters' => $tool['function']['parameters'],
                'strict' => false,
            ], $tools);
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('ai.timeout'))
                ->post('https://api.openai.com/v1/responses', $payload);
        } catch (ConnectionException $exception) {
            // cURL error 28 is a timeout; anything else (DNS, TLS, refused) means the API was not reached.
            $timedOut = preg_match('/cURL error 28|timed out/i', $exception->getMessage()) === 1;
            Log::warning('ai.openai.connection', ['reason' => $timedOut ? 'timeout' : 'unreachable']);

            throw $timedOut ? AIException::because('timeout', 504) : AIException::because('unreachable', 503);
        }

        if ($response->failed()) {
            throw $this->httpFailure($response);
        }

        $output = $response->json('output');

        if (! is_array($output)) {
            Log::warning('ai.openai.malformed', ['reason' => 'missing_output']);

            throw AIException::because('malformed_response', 502);
        }

        $text = '';
        $calls = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                throw AIException::because('malformed_response', 502);
            }

            if (($item['type'] ?? null) === 'message') {
                $text .= $this->messageText($item);
            }

            if (($item['type'] ?? null) === 'function_call') {
                $calls[] = $this->toolCall($item);
            }
        }

        $usage = $response->json('usage');

        return new AIResponse(
            $text !== '' ? $text : null,
            $calls,
            is_array($usage) ? [
                'prompt_tokens' => $usage['input_tokens'] ?? null,
                'completion_tokens' => $usage['output_tokens'] ?? null,
                'total_tokens' => $usage['total_tokens'] ?? null,
            ] : [],
            is_string($response->json('model')) ? $response->json('model') : config('ai.model'),
        );
    }

    /**
     * Maps a failed response to a reason the user can act on, and logs what an operator
     * needs (status, OpenAI error type and code, request id) but never the error message,
     * which can quote part of the API key.
     */
    private function httpFailure(Response $response): AIException
    {
        $status = $response->status();
        $type = $response->json('error.type');
        $code = $response->json('error.code');
        $quota = $type === 'insufficient_quota' || $code === 'insufficient_quota';

        [$reason, $publicStatus] = match (true) {
            $status === 401 => ['invalid_api_key', 503],
            $status === 429 && $quota => ['insufficient_quota', 503],
            $status === 429 => ['rate_limited', 429],
            in_array($status, [408, 504], true) => ['timeout', 504],
            default => ['unavailable', 503],
        };

        Log::warning('ai.openai.http', [
            'reason' => $reason,
            'status' => $status,
            'error_type' => is_string($type) ? $type : null,
            'error_code' => is_string($code) ? $code : null,
            'request_id' => $response->header('x-request-id') ?: null,
            'retry_after' => $response->header('retry-after') ?: null,
        ]);

        return AIException::because($reason, $publicStatus);
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function instructions(array $messages): string
    {
        return collect($messages)
            ->filter(fn (AIMessage $message): bool => $message->role === 'system' && is_string($message->content) && $message->content !== '')
            ->map(fn (AIMessage $message): string => $message->content)
            ->implode("\n\n");
    }

    /**
     * @param  list<AIMessage>  $messages
     * @return list<array<string, mixed>>
     */
    private function input(array $messages): array
    {
        $items = [];

        foreach ($messages as $message) {
            if ($message->role === 'system') {
                continue;
            }

            if ($message->role === 'tool' && is_string($message->toolCallId)) {
                $items[] = [
                    'type' => 'function_call_output',
                    'call_id' => $message->toolCallId,
                    'output' => $message->content ?? '',
                ];

                continue;
            }

            if ($message->role === 'assistant') {
                foreach ($message->toolCalls as $call) {
                    $items[] = [
                        'type' => 'function_call',
                        'call_id' => $call->id,
                        'name' => $call->name,
                        'arguments' => json_encode($call->arguments) ?: '{}',
                    ];
                }
            }

            if (is_string($message->content) && $message->content !== '' && in_array($message->role, ['user', 'assistant'], true)) {
                $items[] = [
                    'type' => 'message',
                    'role' => $message->role,
                    'content' => $message->content,
                ];
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function messageText(array $item): string
    {
        $content = $item['content'] ?? null;

        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            throw AIException::because('malformed_response', 502);
        }

        $text = '';

        foreach ($content as $part) {
            if (is_array($part) && is_string($part['text'] ?? null)) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toolCall(array $item): AIToolCall
    {
        $id = $item['call_id'] ?? null;
        $name = $item['name'] ?? null;
        $arguments = $item['arguments'] ?? null;

        if (! is_string($id) || ! is_string($name) || ! is_string($arguments)) {
            throw AIException::because('malformed_tool_call', 502);
        }

        $decoded = json_decode($arguments, true);

        if (! is_array($decoded)) {
            throw AIException::because('malformed_tool_call', 502);
        }

        return new AIToolCall($id, $name, $decoded);
    }
}
