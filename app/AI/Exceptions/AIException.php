<?php

namespace App\AI\Exceptions;

use App\AI\Support\AIErrorMessages;
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

    /** An exception whose public message is the standard text for `$reason`. */
    public static function because(string $reason, int $status): self
    {
        return new self(AIErrorMessages::english($reason), $status, ['reason' => $reason]);
    }

    public function reason(): string
    {
        $reason = $this->context['reason'] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : 'unavailable';
    }

    /** The localized message for the user; never contains provider details. */
    public function userMessage(?string $locale = null): string
    {
        return AIErrorMessages::for($this->reason(), $locale);
    }
}
