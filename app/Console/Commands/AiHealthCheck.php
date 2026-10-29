<?php

namespace App\Console\Commands;

use App\AI\Data\AIMessage;
use App\AI\Exceptions\AIException;
use App\AI\Providers\OpenAIProvider;
use Illuminate\Console\Command;

class AiHealthCheck extends Command
{
    protected $signature = 'ai:health-check';

    protected $description = 'Send a minimal request to the configured OpenAI provider';

    public function handle(OpenAIProvider $provider): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('The AI health check is available only in local development.');

            return self::FAILURE;
        }

        if ((string) config('ai.provider') !== 'openai') {
            $this->error('AI_PROVIDER must be openai.');

            return self::FAILURE;
        }

        $key = config('ai.api_key');

        if (! is_string($key) || $key === '') {
            $this->error('AI_API_KEY is missing.');

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            $response = $provider->complete([
                new AIMessage('user', 'Reply with the single word ok.'),
            ], []);
        } catch (AIException $exception) {
            // The reason code (invalid_api_key, insufficient_quota, rate_limited, timeout, ...) says what to fix.
            $this->error('Reason: '.$exception->reason());
            $this->error($exception->userMessage('en'));

            return self::FAILURE;
        }

        if (! is_string($response->content) || trim($response->content) === '') {
            $this->error('The assistant returned an empty response.');

            return self::FAILURE;
        }

        $latency = (int) round((microtime(true) - $started) * 1000);
        $model = $response->model ?: (string) config('ai.model');

        $this->line('Provider: openai');
        $this->line('Model: '.$model);
        $this->line('Latency: '.$latency.' ms');
        $this->info('The assistant responded.');

        return self::SUCCESS;
    }
}
