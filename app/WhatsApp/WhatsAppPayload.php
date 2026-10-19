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

            foreach ($value['messages'] ?? [] as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $messages[] = [
                    'phone_number_id' => $phoneNumberId,
                    'external_message_id' => (string) ($message['id'] ?? ''),
                    'from' => (string) ($message['from'] ?? ''),
                    'timestamp' => (string) ($message['timestamp'] ?? ''),
                    'type' => (string) ($message['type'] ?? 'text'),
                    'text' => is_array($message['text'] ?? null) ? (string) ($message['text']['body'] ?? '') : null,
                    'profile_name' => $this->profileName($value, (string) ($message['from'] ?? '')),
                ];
            }
        }

        return $messages;
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

            foreach ($value['statuses'] ?? [] as $status) {
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

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (is_array($change['value'] ?? null)) {
                    $values[] = $change['value'];
                }
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function profileName(array $value, string $from): ?string
    {
        foreach ($value['contacts'] ?? [] as $contact) {
            if (is_array($contact) && (string) ($contact['wa_id'] ?? '') === $from) {
                $name = $contact['profile']['name'] ?? null;

                return is_string($name) && $name !== '' ? $name : null;
            }
        }

        return null;
    }
}
