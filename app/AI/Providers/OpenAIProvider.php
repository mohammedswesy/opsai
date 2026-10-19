<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIMessage;
use App\AI\Data\AIResponse;
use App\AI\Data\AIToolCall;
use App\AI\Exceptions\AIException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIProvider implements AIProviderInterface
{
    public function complete(array $messages, array $tools): AIResponse
    {
        $apiKey = config('ai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new AIException('The assistant is not configured.', 503, ['reason' => 'missing_api_key']);
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
        } catch (ConnectionException) {
            Log::warning('ai.openai.connection', ['reason' => 'timeout']);

            throw new AIException('The assistant took too long to respond.', 504, ['reason' => 'timeout']);
        }

        if ($response->status() === 401) {
            Log::warning('ai.openai.http', ['reason' => 'invalid_api_key', 'status' => 401]);

            throw new AIException('The assistant is not configured.', 503, ['reason' => 'invalid_api_key']);
        }

        if ($response->status() === 429) {
            Log::warning('ai.openai.http', ['reason' => 'rate_limited', 'status' => 429]);

            throw new AIException('The assistant is busy. Please try again in a moment.', 429, ['reason' => 'rate_limited']);
        }

        if ($response->failed()) {
            Log::warning('ai.openai.http', ['reason' => 'http_error', 'status' => $response->status()]);

            throw new AIException('The assistant is unavailable right now.', 503, ['reason' => 'http_error']);
        }

        $output = $response->json('output');

        if (! is_array($output)) {
            Log::warning('ai.openai.malformed', ['reason' => 'missing_output']);

            throw new AIException('The assistant returned a response I could not use.', 502, ['reason' => 'malformed_response']);
        }

        $text = '';
        $calls = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                throw new AIException('The assistant returned a response I could not use.', 502, ['reason' => 'malformed_response']);
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
            throw new AIException('The assistant returned a response I could not use.', 502, ['reason' => 'malformed_response']);
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
            throw new AIException('The assistant returned a response I could not use.', 502, ['reason' => 'malformed_tool_call']);
        }

        $decoded = json_decode($arguments, true);

        if (! is_array($decoded)) {
            throw new AIException('The assistant returned a response I could not use.', 502, ['reason' => 'malformed_tool_call']);
        }

        return new AIToolCall($id, $name, $decoded);
    }
}
