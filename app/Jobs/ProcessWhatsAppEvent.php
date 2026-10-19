<?php

namespace App\Jobs;

use App\Models\WhatsAppWebhookEvent;
use App\WhatsApp\WhatsAppInboundProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $eventId) {}

    public function handle(WhatsAppInboundProcessor $processor): void
    {
        $event = WhatsAppWebhookEvent::query()->find($this->eventId);

        if ($event === null) {
            return;
        }

        $processor->handle($event);
    }
}
