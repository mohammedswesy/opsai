<?php

namespace App\AI\Data;

final class AIResponse
{
    /**
     * @param  list<AIToolCall>  $toolCalls
     * @param  array<string, mixed>  $usage
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls = [],
        public readonly array $usage = [],
        public readonly ?string $model = null,
    ) {}
}
