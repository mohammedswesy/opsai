<?php

namespace App\Console\Commands;

use App\Enums\OrganizationRole;
use App\Models\Appointment;
use App\Models\Integration;
use App\Support\OrganizationContext;
use App\WhatsApp\AppointmentTemplateSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends the appointment_reminder template about 24 hours before open appointments, for
 * organizations that enabled reminders. Customers without WhatsApp opt-in, without a
 * phone number, or without an approved template are skipped; each skip is logged with
 * a reason and the appointment id only.
 */
class WhatsAppSendReminders extends Command
{
    public const PURPOSE = 'appointment_reminder';

    protected $signature = 'whatsapp:send-reminders';

    protected $description = 'Send WhatsApp appointment reminders about 24 hours ahead';

    public function handle(AppointmentTemplateSender $sender): int
    {
        $hours = max(1, (int) config('whatsapp.reminder_hours_before'));
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        $integrations = Integration::query()->withoutGlobalScope('organization')->with('organization')->where('type', 'whatsapp')->get();

        foreach ($integrations as $integration) {
            if ($integration->organization === null) {
                continue;
            }

            if ($integration->reminders_enabled !== true || $integration->status !== 'active') {
                Log::info('whatsapp.reminders_skipped', ['integration_id' => $integration->id, 'reason' => $integration->reminders_enabled === true ? 'integration_inactive' : 'reminders_disabled']);

                continue;
            }

            OrganizationContext::set($integration->organization, OrganizationRole::Owner);

            // Starts within the next $hours, but not within the hour: "about a day before"
            // when run hourly, and never a reminder for a visit that is about to begin.
            $appointments = Appointment::query()
                ->with(['customer', 'organization', 'serviceRequest.service', 'workOrder.serviceRequest.service'])
                ->whereIn('status', array_map(fn ($status) => $status->value, AppointmentTemplateSender::OPEN_STATUSES))
                ->where('scheduled_start', '>', now()->addHour())
                ->where('scheduled_start', '<=', now()->addHours($hours))
                ->orderBy('scheduled_start')
                ->get();

            foreach ($appointments as $appointment) {
                $outcome = $sender->send($appointment, self::PURPOSE, 'reminder');

                if ($outcome->skipped !== null) {
                    $skipped++;
                    Log::info('whatsapp.reminder_skipped', ['appointment_id' => $appointment->id, 'reason' => $outcome->skipped]);

                    continue;
                }

                if ($outcome->sent()) {
                    $sent++;
                } else {
                    $failed++;
                }
            }

            OrganizationContext::forget();
        }

        $this->info("Reminders: {$sent} sent or already sent, {$skipped} skipped, {$failed} failed.");

        return self::SUCCESS;
    }
}
