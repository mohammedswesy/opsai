<?php

namespace App\WhatsApp\Contracts;

use App\WhatsApp\Data\WhatsAppSendResult;

interface WhatsAppProviderInterface
{
    public function sendTextMessage(string $to, string $body, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult;

    /**
     * @param  array<string, mixed>  $components
     */
    public function sendTemplateMessage(string $to, string $template, string $language, string $phoneNumberId, array $components = [], ?string $accessToken = null): WhatsAppSendResult;

    public function sendMediaMessage(string $to, string $mediaId, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult;

    public function verifyWebhook(string $mode, string $token, string $challenge): ?string;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function parseIncomingMessage(array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function parseDeliveryStatus(array $payload): array;

    public function verifySignature(string $payload, ?string $signature): bool;

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $metadata
     * @return array{connected: bool, phone: string|null}
     */
    public function verifyConnection(array $credentials, array $metadata): array;
}
