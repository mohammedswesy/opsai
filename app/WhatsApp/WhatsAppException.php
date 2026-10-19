<?php

namespace App\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public function __construct(
        public readonly string $publicMessage,
        public readonly string $reason = 'provider_error',
    ) {
        parent::__construct($publicMessage);
    }
}
