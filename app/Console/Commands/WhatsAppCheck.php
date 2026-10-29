<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\WhatsApp\ConnectionHealth;
use App\WhatsApp\Providers\MetaWhatsAppProvider;
use App\WhatsApp\WhatsAppException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Read-only readiness report for the WhatsApp Cloud API. It prints yes/no for each
 * setting and never a value. Meta is called only with --live.
 */
class WhatsAppCheck extends Command
{
    protected $signature = 'whatsapp:check {--live : Also call the Meta Graph API with the saved access token}';

    protected $description = 'Report whether the WhatsApp Cloud API settings are ready for a live test';

    /** @var list<string> */
    private array $missing = [];

    /** @var list<string> */
    private array $notes = [];

    public function handle(MetaWhatsAppProvider $meta, ConnectionHealth $health): int
    {
        $this->line('WhatsApp configuration check (values are never shown)');

        $this->section('Application');
        $this->check('WHATSAPP_PROVIDER is meta', config('whatsapp.provider') === 'meta', 'Set WHATSAPP_PROVIDER=meta (it is "fake" by default).');
        $this->check('APP_KEY is set (decrypts saved credentials)', is_string(config('app.key')) && config('app.key') !== '', 'Run php artisan key:generate before saving credentials.');

        $integrations = Integration::query()->withoutGlobalScope('organization')->with('organization:id,name')->where('type', 'whatsapp')->get();
        $saved = $this->savedCredentials($integrations);

        $this->section('Webhook secrets (from .env or saved on the WhatsApp settings page)');
        $this->check('Verify token', $this->filled(config('whatsapp.verify_token')) || $saved->contains(fn ($c) => $this->filled($c['verify_token'] ?? null)), 'Set WHATSAPP_VERIFY_TOKEN or save a verify token in Settings > WhatsApp.');
        $this->check('App secret (checks X-Hub-Signature-256)', $this->filled(config('whatsapp.app_secret')) || $saved->contains(fn ($c) => $this->filled($c['app_secret'] ?? null)), 'Set WHATSAPP_APP_SECRET or save the app secret in Settings > WhatsApp.');

        $this->section('Phone number');
        $active = $integrations->where('status', 'active');
        $this->check('An active WhatsApp integration exists', $active->isNotEmpty(), 'Save the WhatsApp settings with status Connected for your organization.');
        $ids = $active->map(fn (Integration $integration) => (string) ($integration->metadata['phone_number_id'] ?? ''))->filter();
        $this->check('Each phone number ID belongs to one organization', $ids->count() === $ids->unique()->count(), 'Two active organizations use the same phone number ID; webhooks can reach only one of them.');

        foreach ($integrations as $integration) {
            $credentials = $saved->get($integration->id);
            $name = $integration->organization?->name ?? $integration->organization_id;
            $this->line('');
            $this->line("  <options=bold>{$name}</>");
            $this->detail('Active', $integration->status === 'active');
            $this->detail('Phone number ID', $this->filled($integration->metadata['phone_number_id'] ?? null));
            $this->detail('WhatsApp Business Account ID', $this->filled($integration->metadata['business_account_id'] ?? null));
            $this->detail('Saved credentials readable', $credentials !== null);
            $this->detail('Access token (saved or WHATSAPP_ACCESS_TOKEN)', $this->filled($credentials['access_token'] ?? null) || $this->filled(config('whatsapp.access_token')));
            $this->detail('Connection healthy (no rejected token recorded)', ! $integration->needsAttention(), $integration->attention_reason);

            if ($integration->needsAttention()) {
                $this->notes[] = "{$name}: the last send was rejected ({$integration->attention_reason}). Renew the token, save it, then run whatsapp:check --live to clear the warning.";
            }

            if ($integration->status === 'active' && ! $this->filled($integration->metadata['phone_number_id'] ?? null)) {
                $this->missing[] = "Phone number ID for {$name}.";
            }

            if ($integration->status === 'active' && ! $this->filled($credentials['access_token'] ?? null) && ! $this->filled(config('whatsapp.access_token'))) {
                $this->missing[] = "Access token for {$name}.";
            }

            if ($credentials === null) {
                $this->missing[] = "Saved credentials for {$name} cannot be decrypted with this APP_KEY; save them again.";
            }
        }

        $this->section('Webhook to register in Meta');
        $path = '/webhooks/whatsapp';
        $base = rtrim((string) config('app.url'), '/');
        $this->line("  Callback URL path: {$path}");
        $this->line("  Callback URL from APP_URL: {$base}{$path}");
        $this->line('  Webhook field to subscribe: messages');

        if (! str_starts_with($base, 'https://') || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            $this->notes[] = 'APP_URL is not a public https URL. Register your tunnel URL plus '.$path.' in Meta (APP_URL only affects this printout).';
        }

        if (config('queue.default') !== 'sync') {
            $this->notes[] = 'QUEUE_CONNECTION is not sync: keep "php artisan queue:work" running or replies are never sent.';
        }

        if ($this->option('live')) {
            $this->live($meta, $health, $active, $saved);
        }

        $this->section('Missing');
        if ($this->missing === []) {
            $this->line('  Nothing.');
        }
        foreach (array_unique($this->missing) as $item) {
            $this->line("  - {$item}");
        }

        if ($this->notes !== []) {
            $this->section('Notes');
            foreach ($this->notes as $note) {
                $this->line("  - {$note}");
            }
        }

        $this->line('');
        if ($this->missing === []) {
            $this->info('Result: ready for a live test.');

            return self::SUCCESS;
        }

        $this->error('Result: not ready ('.count(array_unique($this->missing)).' missing).');

        return self::FAILURE;
    }

    /**
     * @param  Collection<int, Integration>  $active
     * @param  Collection<string, array<string, mixed>|null>  $saved
     */
    private function live(MetaWhatsAppProvider $meta, ConnectionHealth $health, $active, $saved): void
    {
        $this->section('Live check against the Meta Graph API');

        foreach ($active as $integration) {
            $name = $integration->organization?->name ?? $integration->organization_id;
            $credentials = $saved->get($integration->id) ?? [];
            $token = $this->filled($credentials['access_token'] ?? null) ? $credentials['access_token'] : config('whatsapp.access_token');
            $metadata = $integration->metadata ?? [];

            $this->line("  <options=bold>{$name}</>");

            try {
                $meta->verifyConnection(['access_token' => $token], $metadata);
                $this->detail('Phone number reachable with the access token', true);
                $health->succeeded($integration);
            } catch (WhatsAppException $exception) {
                $this->detail('Phone number reachable with the access token', false, $exception->reason);
                $this->missing[] = "Meta rejected the phone number check for {$name} ({$exception->reason}).";
                $health->failed($integration, $exception->reason);

                continue;
            }

            $this->tokenLifetime($meta, (string) $token);

            $waba = (string) ($metadata['business_account_id'] ?? '');

            if ($waba === '') {
                $this->notes[] = "Add the WhatsApp Business Account ID for {$name} to check the app subscription.";

                continue;
            }

            $subscribed = $this->subscribed($waba, (string) $token);
            $this->detail('App subscribed to the business account', $subscribed === true, $subscribed === null ? 'could not check' : null);

            if ($subscribed === false) {
                $this->missing[] = "The Meta app is not subscribed to the business account for {$name}; subscribe it (see docs/whatsapp-live-setup.md).";
            }
        }
    }

    /** Long-lived or expiring, from Meta's debug_token; the token itself is never printed. */
    private function tokenLifetime(MetaWhatsAppProvider $meta, string $token): void
    {
        $info = $meta->inspectToken($token);

        if ($info === null || $info['expires_at'] === null) {
            $this->components->twoColumnDetail('Token lifetime', 'could not be determined with this token');

            return;
        }

        $type = $info['type'] !== null ? strtolower(str_replace('_', ' ', $info['type'])).' token' : 'token';

        if ($info['expires_at'] === 0) {
            $this->components->twoColumnDetail('Token lifetime', "<fg=green>long-lived</> ({$type}, does not expire)");

            return;
        }

        $expires = Carbon::createFromTimestamp($info['expires_at']);
        $this->components->twoColumnDetail('Token lifetime', "<fg=yellow>expiring</> ({$type}, expires {$expires->toDateTimeString()} UTC, {$expires->diffForHumans()})");
        $this->notes[] = 'The access token expires. Generate a system user token that never expires (docs/whatsapp-live-setup.md, section 7).';
    }

    private function subscribed(string $waba, string $token): ?bool
    {
        try {
            $response = Http::withToken($token)->acceptJson()->timeout(15)
                ->get('https://graph.facebook.com/'.trim((string) config('whatsapp.api_version'), '/').'/'.$waba.'/subscribed_apps');
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $data = $response->json('data');

        return is_array($data) && $data !== [];
    }

    /**
     * Credentials per integration id; null when they cannot be decrypted.
     *
     * @param  Collection<int, Integration>  $integrations
     * @return Collection<string, array<string, mixed>|null>
     */
    private function savedCredentials($integrations)
    {
        return $integrations->mapWithKeys(function (Integration $integration) {
            try {
                $credentials = $integration->credentials;
            } catch (DecryptException) {
                return [$integration->id => null];
            }

            return [$integration->id => is_array($credentials) ? $credentials : []];
        });
    }

    private function section(string $title): void
    {
        $this->line('');
        $this->line("<options=bold>{$title}</>");
    }

    private function check(string $label, bool $present, string $fix): void
    {
        $this->detail($label, $present);

        if (! $present) {
            $this->missing[] = $fix;
        }
    }

    private function detail(string $label, bool $present, ?string $why = null): void
    {
        $answer = $present ? '<fg=green>yes</>' : '<fg=red>no</>';
        $this->components->twoColumnDetail($label, $answer.($why ? " ({$why})" : ''));
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
