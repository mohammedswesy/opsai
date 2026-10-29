<?php

namespace App\WhatsApp\Data;

use App\Models\WhatsAppMessage;

/** A template send: the message when one was recorded, else why it was skipped. */
final class TemplateSendOutcome
{
    public function __construct(
        public readonly ?WhatsAppMessage $message,
        public readonly ?string $skipped = null,
    ) {}

    public static function skip(string $reason): self
    {
        return new self(null, $reason);
    }

    public function sent(): bool
    {
        return $this->message !== null && $this->message->status !== 'failed';
    }
}
