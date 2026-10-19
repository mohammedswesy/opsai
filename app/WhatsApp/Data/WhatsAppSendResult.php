<?php

namespace App\WhatsApp\Data;

final class WhatsAppSendResult
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $status,
    ) {}
}
