<?php

namespace App\WhatsApp\Contracts;

use App\WhatsApp\Data\WhatsAppSendResult;

interface WhatsAppProviderInterface
{
    public function sendTextMessage(string $to, string $body, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult;

    /**
     * @param  list<array<string, mixed>>  $components  Graph API template components (body parameters, ...)
     */
    public function sendTemplateMessage(string $to, string $template, string $language, string $phoneNumberId, array $components = [], ?string $accessToken = null): WhatsAppSendResult;

    /**
     * Names, languages and review statuses of the templates in a WhatsApp Business Account.
     *
     * @return list<array{name: string, language: string, status: string, category: string|null}>
     */
    public function listTemplates(string $businessAccountId, ?string $accessToken = null): array;

    public function sendMediaMessage(string $to, string $mediaId, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult;

    /** `$expected` overrides the configured verify token (for one saved in settings). */
    public function verifyWebhook(string $mode, string $token, string $challenge, ?string $expected = null): ?string;

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

    /** `$secret` overrides the configured app secret (for one saved in settings). */
    public function verifySignature(string $payload, ?string $signature, ?string $secret = null): bool;

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $metadata
     * @return array{connected: bool, phone: string|null}
     */
    public function verifyConnection(array $credentials, array $metadata): array;
}
