<?php

namespace App\WhatsApp;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Integration;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppTemplate;
use App\Support\LocaleFormat;
use App\Support\PhoneNormalizer;
use App\WhatsApp\Data\TemplateSendOutcome;

/**
 * Sends an appointment template (reminder, confirmation) to the appointment's customer.
 * Parameters come from existing records only, times are shown in the organization
 * timezone (Asia/Riyadh by default) like the rest of the app, and each
 * (appointment, purpose) is sent at most once. Runs inside an OrganizationContext.
 */
class AppointmentTemplateSender
{
    public const OPEN_STATUSES = [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed];

    public function __construct(
        private readonly WhatsAppMessenger $messenger,
        private readonly PhoneNormalizer $phones,
    ) {}

    /**
     * @param  bool  $requireOptIn  true for business-initiated sends such as reminders
     */
    public function send(Appointment $appointment, string $purpose, string $author, ?WhatsAppTemplate $template = null, bool $requireOptIn = true): TemplateSendOutcome
    {
        $integration = Integration::query()->where('type', 'whatsapp')->first();

        if ($integration === null || $integration->status !== 'active') {
            return TemplateSendOutcome::skip('integration_inactive');
        }

        if (! in_array($appointment->status, self::OPEN_STATUSES, true)) {
            return TemplateSendOutcome::skip('appointment_not_open');
        }

        $template ??= $this->templateFor($purpose, $appointment->organization?->locale);

        if ($template === null || ! $template->isApproved() || $template->purpose !== $purpose) {
            return TemplateSendOutcome::skip('template_not_approved');
        }

        $customer = $appointment->customer;

        if ($customer === null) {
            return TemplateSendOutcome::skip('no_customer');
        }

        if ($requireOptIn && $customer->whatsapp_opt_in_at === null) {
            return TemplateSendOutcome::skip('no_opt_in');
        }

        $country = $customer->country ?: $appointment->organization?->country;
        $phone = $this->phones->normalize($customer->whatsapp_number, $country) ?? $this->phones->normalize($customer->phone, $country);

        if ($phone === null) {
            return TemplateSendOutcome::skip('no_phone');
        }

        $values = $this->values($appointment, $template);

        if ($values === null) {
            return TemplateSendOutcome::skip('missing_parameter');
        }

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            ['organization_id' => $appointment->organization_id, 'contact_phone' => $phone],
            ['integration_id' => $integration->id, 'customer_id' => $customer->id, 'contact_name' => $customer->name, 'status' => 'open', 'unread_count' => 0],
        );

        $message = $this->messenger->sendTemplate(
            $conversation,
            $integration,
            $template,
            $values,
            $author,
            self::idempotencyKey($appointment, $purpose),
        );

        return new TemplateSendOutcome($message);
    }

    public static function idempotencyKey(Appointment $appointment, string $purpose): string
    {
        return 'appointment:'.$appointment->id.':'.$purpose;
    }

    /** The approved template for a purpose, in the organization's language when there is one. */
    public function templateFor(string $purpose, ?string $locale): ?WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->where('purpose', $purpose)
            ->where('status', 'approved')
            ->get()
            ->sortBy(fn (WhatsAppTemplate $template) => $template->language === $locale ? 0 : 1)
            ->first();
    }

    /**
     * Body parameter values in the template's mapping order; null when one is empty
     * (Meta rejects an empty parameter).
     *
     * @return list<string>|null
     */
    public function values(Appointment $appointment, WhatsAppTemplate $template): ?array
    {
        $organization = $appointment->organization;
        $format = new LocaleFormat(str_starts_with($template->language, 'ar') ? 'ar' : 'en', $organization?->timezone ?: 'Asia/Riyadh');
        $request = $appointment->serviceRequest ?? $appointment->workOrder?->serviceRequest;
        $values = [];

        foreach ($template->parameterFields() as $field) {
            $value = match ($field) {
                'customer_first_name' => (string) strtok(trim((string) $appointment->customer?->name), " \t"),
                'appointment_date' => $format->day($appointment->scheduled_start),
                'appointment_time' => $format->time($appointment->scheduled_start),
                'service_name' => (string) ($request?->service?->name ?? $request?->subject ?? ''),
                'company_name' => (string) ($organization?->name ?? ''),
            };

            // Meta rejects newlines, tabs and runs of spaces inside a parameter.
            $value = trim((string) preg_replace('/\s+/u', ' ', $value));

            if ($value === '') {
                return null;
            }

            $values[] = $value;
        }

        return $values;
    }
}
