<?php

namespace App\Providers;

use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\Providers\FakeWhatsAppProvider;
use App\WhatsApp\Providers\MetaWhatsAppProvider;
use App\WhatsApp\WhatsAppException;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WhatsAppProviderInterface::class, function ($app): WhatsAppProviderInterface {
            $provider = (string) config('whatsapp.provider');

            if ($provider === 'meta') {
                return $app->make(MetaWhatsAppProvider::class);
            }

            if ($provider === 'fake' && ! $app->environment('production')) {
                return $app->make(FakeWhatsAppProvider::class);
            }

            return new class implements WhatsAppProviderInterface
            {
                public function sendTextMessage(string $to, string $body, string $phoneNumberId, ?string $accessToken = null): never
                {
                    throw new WhatsAppException('WhatsApp is not configured.', 'unsupported_provider');
                }

                public function sendTemplateMessage(string $to, string $template, string $language, string $phoneNumberId, array $components = [], ?string $accessToken = null): never
                {
                    throw new WhatsAppException('WhatsApp templates are not configured.', 'unsupported_provider');
                }

                public function sendMediaMessage(string $to, string $mediaId, string $phoneNumberId, ?string $accessToken = null): never
                {
                    throw new WhatsAppException('WhatsApp is not configured.', 'unsupported_provider');
                }

                public function verifyWebhook(string $mode, string $token, string $challenge): ?string
                {
                    return null;
                }

                public function parseIncomingMessage(array $payload): array
                {
                    return [];
                }

                public function parseDeliveryStatus(array $payload): array
                {
                    return [];
                }

                public function verifySignature(string $payload, ?string $signature): bool
                {
                    return false;
                }

                public function verifyConnection(array $credentials, array $metadata): never
                {
                    throw new WhatsAppException('WhatsApp is not configured.', 'unsupported_provider');
                }
            };
        });
    }
}
