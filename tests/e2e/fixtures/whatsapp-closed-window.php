<?php

/*
 * Playwright fixture: a Riyadh customer with an appointment tomorrow whose last WhatsApp
 * message is 30 hours old, so the inbox shows the closed-window composer. Runs only
 * against database/e2e.sqlite. Prints the conversation id.
 */

use App\Enums\OrganizationRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\OrganizationContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;

require __DIR__.'/../../../vendor/autoload.php';

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.sqlite.database');

if (config('database.default') !== 'sqlite' || ! str_ends_with(str_replace('\\', '/', $database), 'database/e2e.sqlite')) {
    fwrite(STDERR, "Refusing to run outside database/e2e.sqlite.\n");
    exit(1);
}

$organization = Organization::query()->where('slug', 'like', 'riyadh%')->firstOrFail();
OrganizationContext::set($organization, OrganizationRole::Owner);

$integration = Integration::query()->where('type', 'whatsapp')->firstOrFail();
$technician = Technician::query()->firstOrFail();

$customer = Customer::query()->firstOrCreate(['phone' => '0550007001'], [
    'type' => 'individual',
    'name' => 'Huda Al Otaibi',
    'whatsapp_number' => '0550007001',
    'city' => 'Riyadh',
    'country' => 'SA',
    'status' => 'active',
    'source' => 'whatsapp',
    'whatsapp_opt_in_at' => now()->subMonth(),
]);

$request = ServiceRequest::query()->firstOrCreate(['customer_id' => $customer->id, 'subject' => 'AC maintenance visit'], [
    'service_id' => $technician->skills()->value('service_id'),
    'source' => 'whatsapp',
    'description' => 'Yearly maintenance of two split units.',
    'priority' => 'normal',
    'status' => 'scheduled',
    'requested_at' => now()->subDays(2),
]);

$start = Carbon::now($organization->timezone ?: 'Asia/Riyadh')->addDay()->setTime(11, 0);
Appointment::query()->firstOrCreate(['customer_id' => $customer->id, 'service_request_id' => $request->id], [
    'technician_id' => $technician->id,
    'scheduled_start' => $start->copy()->utc(),
    'scheduled_end' => $start->copy()->addHour()->utc(),
    'status' => 'confirmed',
]);

$conversation = WhatsAppConversation::query()->firstOrCreate(['contact_phone' => '966550007001'], [
    'integration_id' => $integration->id,
    'customer_id' => $customer->id,
    'contact_name' => $customer->name,
    'status' => 'open',
    'unread_count' => 0,
    'last_message_at' => now()->subHours(29),
]);

if ($conversation->messages()->doesntExist()) {
    foreach ([['متى يوصل الفني بكرة؟', 'inbound', 30], ['الفني يوصل الساعة 11 الصبح إن شاء الله.', 'outbound', 29]] as [$text, $direction, $hours]) {
        $message = new WhatsAppMessage([
            'customer_id' => $customer->id,
            'integration_id' => $integration->id,
            'whatsapp_conversation_id' => $conversation->id,
            'direction' => $direction,
            'from_number' => $direction === 'inbound' ? '966550007001' : '100000000000001',
            'to_number' => $direction === 'inbound' ? '100000000000001' : '966550007001',
            'message_type' => 'text',
            'text' => $text,
            'status' => $direction === 'inbound' ? 'received' : 'read',
            'metadata' => ['author' => $direction === 'inbound' ? 'customer' : 'staff'],
        ]);
        $message->forceFill(['created_at' => now()->subHours($hours), 'updated_at' => now()->subHours($hours)])->save();
    }
}

echo $conversation->id;
