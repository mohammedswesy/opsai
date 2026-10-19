<?php

namespace App\AI\Data;

final class AIMessage
{
    /**
     * @param  list<AIToolCall>  $toolCalls
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly string $role,
        public readonly ?string $content = null,
        public readonly array $toolCalls = [],
        public readonly ?string $toolCallId = null,
        public readonly ?array $metadata = null,
    ) {}
}
