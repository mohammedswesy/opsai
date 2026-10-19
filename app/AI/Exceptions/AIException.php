<?php

namespace App\AI\Exceptions;

use RuntimeException;

class AIException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $publicMessage,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($publicMessage);
    }
}
