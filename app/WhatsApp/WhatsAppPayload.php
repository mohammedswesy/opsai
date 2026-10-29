<?php

namespace App\WhatsApp;

class WhatsAppPayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function messages(array $payload): array
    {
        $messages = [];

        foreach ($this->changes($payload) as $value) {
            $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

            foreach ($this->listOf($value, 'messages') as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $type = is_string($message['type'] ?? null) && $message['type'] !== '' ? $message['type'] : 'unknown';

                $messages[] = [
                    'phone_number_id' => $phoneNumberId,
                    'external_message_id' => (string) ($message['id'] ?? ''),
                    'from' => (string) ($message['from'] ?? ''),
                    'timestamp' => (string) ($message['timestamp'] ?? ''),
                    'type' => $type,
                    'text' => $this->text($message, $type),
                    'media' => $this->media($message, $type),
                    'profile_name' => $this->profileName($value, (string) ($message['from'] ?? '')),
                ];
            }
        }

        return $messages;
    }

    /**
     * Readable text of a message: the body, a media caption, or the title of a tapped
     * button or list row. Null when the message carries no text (audio, location, ...).
     *
     * @param  array<string, mixed>  $message
     */
    private function text(array $message, string $type): ?string
    {
        $candidates = match ($type) {
            'text' => [$message['text']['body'] ?? null],
            'image', 'video', 'document' => [$message[$type]['caption'] ?? null],
            'button' => [$message['button']['text'] ?? null],
            'interactive' => [$message['interactive']['button_reply']['title'] ?? null, $message['interactive']['list_reply']['title'] ?? null],
            default => [],
        };

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * What staff need to find a non-text message: the media id and type, or the shared
     * location. The media itself is not downloaded.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function media(array $message, string $type): ?array
    {
        $part = is_array($message[$type] ?? null) ? $message[$type] : [];

        $media = match ($type) {
            'image', 'audio', 'video', 'document', 'sticker' => [
                'id' => $part['id'] ?? null,
                'mime_type' => $part['mime_type'] ?? null,
                'filename' => $part['filename'] ?? null,
            ],
            'location' => [
                'latitude' => $part['latitude'] ?? null,
                'longitude' => $part['longitude'] ?? null,
                'name' => $part['name'] ?? null,
                'address' => $part['address'] ?? null,
            ],
            'reaction' => ['emoji' => $part['emoji'] ?? null, 'message_id' => $part['message_id'] ?? null],
            'unsupported', 'unknown' => ['error_code' => $message['errors'][0]['code'] ?? null],
            default => null,
        };

        return $media === null ? null : array_filter($media, fn ($value) => is_scalar($value));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function statuses(array $payload): array
    {
        $statuses = [];

        foreach ($this->changes($payload) as $value) {
            $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

            foreach ($this->listOf($value, 'statuses') as $status) {
                if (! is_array($status)) {
                    continue;
                }

                $statuses[] = [
                    'phone_number_id' => $phoneNumberId,
                    'external_message_id' => (string) ($status['id'] ?? ''),
                    'status' => (string) ($status['status'] ?? ''),
                    'timestamp' => (string) ($status['timestamp'] ?? ''),
                    'recipient' => (string) ($status['recipient_id'] ?? ''),
                    'error' => is_array($status['errors'][0] ?? null) ? (string) ($status['errors'][0]['title'] ?? 'failed') : null,
                    'error_code' => is_numeric($status['errors'][0]['code'] ?? null) ? (int) $status['errors'][0]['code'] : null,
                ];
            }
        }

        return $statuses;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function changes(array $payload): array
    {
        $values = [];

        foreach ($this->listOf($payload, 'entry') as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($this->listOf($entry, 'changes') as $change) {
                if (is_array($change['value'] ?? null)) {
                    $values[] = $change['value'];
                }
            }
        }

        return $values;
    }

    /**
     * `$data[$key]` when it is a list, else an empty one, so an odd payload is skipped
     * instead of failing the webhook (Meta would then retry it for days).
     *
     * @param  array<string, mixed>  $data
     * @return array<int|string, mixed>
     */
    private function listOf(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function profileName(array $value, string $from): ?string
    {
        foreach ($this->listOf($value, 'contacts') as $contact) {
            if (is_array($contact) && (string) ($contact['wa_id'] ?? '') === $from) {
                $name = $contact['profile']['name'] ?? null;

                return is_string($name) && $name !== '' ? $name : null;
            }
        }

        return null;
    }
}
