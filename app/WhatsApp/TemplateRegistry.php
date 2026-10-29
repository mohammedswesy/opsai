<?php

namespace App\WhatsApp;

use App\Models\Integration;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;

/**
 * The organization's template registry: the configured templates (config/whatsapp.php)
 * in each language, with review statuses read from Meta. Runs inside an
 * OrganizationContext.
 */
class TemplateRegistry
{
    public function __construct(
        private readonly WhatsAppProviderInterface $provider,
        private readonly ConnectionHealth $health,
    ) {}

    /** Adds the configured templates that are not registered yet (status unknown). */
    public function ensureDefaults(): void
    {
        foreach ((array) config('whatsapp.templates') as $purpose => $definition) {
            if (! in_array($purpose, WhatsAppTemplate::PURPOSES, true)) {
                continue;
            }

            foreach ((array) config('whatsapp.template_languages') as $language) {
                WhatsAppTemplate::query()->firstOrCreate(
                    ['name' => (string) $definition['name'], 'language' => (string) $language],
                    ['purpose' => $purpose, 'status' => 'unknown', 'parameters' => array_values((array) $definition['parameters'])],
                );
            }
        }
    }

    /**
     * Reads the templates from the provider and updates the registry's statuses.
     *
     * @return array{updated: int, missing: list<string>, unregistered: list<string>}
     *
     * @throws WhatsAppException
     */
    public function sync(Integration $integration): array
    {
        $waba = (string) ($integration->metadata['business_account_id'] ?? '');

        if ($waba === '') {
            throw new WhatsAppException('The WhatsApp Business Account ID is not saved.', 'missing_business_account');
        }

        $this->ensureDefaults();
        $token = is_array($integration->credentials) ? ($integration->credentials['access_token'] ?? null) : null;

        try {
            $remote = $this->provider->listTemplates($waba, is_string($token) ? $token : null);
        } catch (WhatsAppException $exception) {
            $this->health->failed($integration, $exception->reason);

            throw $exception;
        }

        $byKey = [];
        foreach ($remote as $row) {
            $byKey[$row['name'].'|'.$row['language']] = $row;
        }

        $updated = 0;
        $missing = [];
        $registered = [];

        foreach (WhatsAppTemplate::query()->orderBy('purpose')->orderBy('language')->get() as $template) {
            $key = $template->name.'|'.$template->language;
            $registered[$key] = true;
            $row = $byKey[$key] ?? null;

            if ($row === null) {
                $missing[] = $template->name.' ('.$template->language.')';
            }

            $template->update([
                'status' => $row === null ? 'unknown' : self::status($row['status']),
                'provider_status' => $row === null ? 'NOT_FOUND' : substr($row['status'], 0, 32),
                'category' => $row['category'] ?? $template->category,
                'synced_at' => now(),
            ]);
            $updated++;
        }

        $unregistered = array_values(array_map(
            fn (array $row) => $row['name'].' ('.$row['language'].')',
            array_filter($byKey, fn (string $key) => ! isset($registered[$key]), ARRAY_FILTER_USE_KEY),
        ));

        return ['updated' => $updated, 'missing' => $missing, 'unregistered' => $unregistered];
    }

    /** Meta review status to the registry's approved / pending / rejected / unknown. */
    public static function status(string $metaStatus): string
    {
        return match (strtoupper($metaStatus)) {
            'APPROVED' => 'approved',
            'PENDING', 'IN_APPEAL' => 'pending',
            'REJECTED' => 'rejected',
            default => 'unknown',
        };
    }
}
