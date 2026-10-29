<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\Organization;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\ConnectionHealth;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\TemplateRegistry;
use App\WhatsApp\WhatsAppException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppIntegrationController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->manageableOrganization($request);

        $integration = Integration::query()->where('type', 'whatsapp')->first();

        return Inertia::render('WhatsApp/Settings', [
            'integration' => $integration?->safeMetadata(),
            'status' => $integration?->status ?? 'inactive',
            'health' => $integration?->health(),
            'remindersEnabled' => $integration?->reminders_enabled === true,
            'templates' => WhatsAppTemplate::query()->orderBy('purpose')->orderBy('language')->get()
                ->map(fn (WhatsAppTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'language' => $template->language,
                    'purpose' => $template->purpose,
                    'status' => $template->status,
                    'parameters' => $template->parameterFields(),
                    'synced_at' => $template->synced_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->manageableOrganization($request);

        $data = $request->validate([
            'phone_number_id' => ['required', 'string', 'max:64'],
            'display_phone' => ['nullable', 'string', 'max:32'],
            'business_account_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'app_secret' => ['nullable', 'string', 'max:500'],
            'verify_token' => ['nullable', 'string', 'max:200'],
            'status' => ['required', 'in:active,disabled'],
        ]);

        $integration = Integration::query()->firstOrNew([
            'type' => 'whatsapp',
        ]);
        $credentials = is_array($integration->credentials) ? $integration->credentials : [];

        foreach (['access_token', 'app_secret', 'verify_token'] as $field) {
            if (is_string($data[$field] ?? null) && $data[$field] !== '') {
                $credentials[$field] = $data[$field];
            }
        }

        $integration->fill([
            'provider' => (string) config('whatsapp.provider'),
            'type' => 'whatsapp',
            'status' => $data['status'],
            'credentials' => $credentials,
            'metadata' => [
                'phone_number_id' => $data['phone_number_id'],
                'display_phone' => $data['display_phone'] ?? null,
                'business_account_id' => $data['business_account_id'] ?? null,
            ],
        ]);
        $integration->save();

        return back();
    }

    public function verify(Request $request, WhatsAppProviderInterface $provider, ConnectionHealth $health): RedirectResponse
    {
        $this->manageableOrganization($request);
        $integration = Integration::query()->where('type', 'whatsapp')->firstOrFail();

        try {
            $result = $provider->verifyConnection($integration->credentials ?? [], $integration->metadata ?? []);
        } catch (WhatsAppException $exception) {
            $health->failed($integration, $exception->reason);

            return back()->withErrors(['whatsapp' => $exception->publicMessage]);
        }

        $metadata = $integration->metadata ?? [];
        $metadata['display_phone'] = $result['phone'];
        $integration->update(['metadata' => $metadata, 'status' => 'active']);
        $health->succeeded($integration);

        return back()->with('success', 'WhatsApp is connected.');
    }

    public function reminders(Request $request): RedirectResponse
    {
        $this->manageableOrganization($request);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $integration = Integration::query()->where('type', 'whatsapp')->firstOrFail();

        $integration->update(['reminders_enabled' => (bool) $data['enabled']]);

        return back();
    }

    public function syncTemplates(Request $request, TemplateRegistry $registry): RedirectResponse
    {
        $this->manageableOrganization($request);
        $integration = Integration::query()->where('type', 'whatsapp')->firstOrFail();

        try {
            $registry->sync($integration);
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['templates' => $exception->reason]);
        }

        return back();
    }

    /** The setup instructions (docs/whatsapp-live-setup.md), linked from the needs-attention banner. */
    public function guide(Request $request): Response
    {
        $organization = $request->user()->organizations()->find(session('current_organization_id'));
        abort_unless($organization !== null && ($request->user()->can('update', $organization) || $request->user()->can('viewAny', WhatsAppConversation::class)), 403);

        $path = base_path('docs/whatsapp-live-setup.md');
        abort_unless(is_file($path), 404);

        return Inertia::render('WhatsApp/Guide', [
            'html' => Str::markdown((string) file_get_contents($path), ['html_input' => 'escape', 'allow_unsafe_links' => false]),
        ]);
    }

    /** Only roles that may manage the organization's settings manage WhatsApp settings. */
    private function manageableOrganization(Request $request): Organization
    {
        $organization = $request->user()->organizations()->find(session('current_organization_id'));
        abort_unless($organization !== null && $request->user()->can('update', $organization), 403);

        return $organization;
    }
}
