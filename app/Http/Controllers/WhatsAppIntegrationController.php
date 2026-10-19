<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppIntegrationController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('update', $request->user()->organizations()->find(session('current_organization_id'))), 403);

        $integration = Integration::query()->where('type', 'whatsapp')->first();

        return Inertia::render('WhatsApp/Settings', [
            'integration' => $integration?->safeMetadata(),
            'status' => $integration?->status ?? 'inactive',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organization = $request->user()->organizations()->find(session('current_organization_id'));
        abort_unless($organization !== null && $request->user()->can('update', $organization), 403);

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

    public function verify(Request $request, WhatsAppProviderInterface $provider): RedirectResponse
    {
        $organization = $request->user()->organizations()->find(session('current_organization_id'));
        abort_unless($organization !== null && $request->user()->can('update', $organization), 403);
        $integration = Integration::query()->where('type', 'whatsapp')->firstOrFail();

        try {
            $result = $provider->verifyConnection($integration->credentials ?? [], $integration->metadata ?? []);
        } catch (WhatsAppException $exception) {
            return back()->withErrors(['whatsapp' => $exception->publicMessage]);
        }

        $metadata = $integration->metadata ?? [];
        $metadata['display_phone'] = $result['phone'];
        $integration->update(['metadata' => $metadata, 'status' => 'active']);

        return back()->with('success', 'WhatsApp is connected.');
    }
}
