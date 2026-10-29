<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\AvailabilityStatus;
use App\Enums\BranchStatus;
use App\Enums\CustomerType;
use App\Enums\InvoiceStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\PaymentMethod;
use App\Enums\Priority;
use App\Enums\RecordStatus;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TechnicianSkillName;
use App\Enums\WorkOrderStatus;
use App\Models\Activity;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Models\Technician;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Models\WorkOrder;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\PaymentService;
use App\Services\Operations\AppointmentService;
use App\Services\Operations\CatalogService;
use App\Services\Operations\CustomerService;
use App\Services\Operations\ServiceRequestService;
use App\Services\Operations\TaskService;
use App\Services\Operations\TechnicianService;
use App\Services\Operations\WorkOrderService;
use App\Support\Money;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    /** @var list<array{0: Carbon, 1: Carbon}> */
    private array $busy = [];

    public function run(): void
    {
        $organization = $this->organization();

        if (str_contains($organization->slug, 'jeddah')) {
            throw new \RuntimeException('Demo data is limited to the Riyadh company.');
        }
        $owner = $this->member($organization, 'Faisal Al Harbi', 'owner@riyadh-cooling.test', OrganizationRole::Owner);
        $this->act($organization, $owner);
        $this->resetDemoRecords($organization);

        $branches = $this->branches($organization);
        $team = $this->team($organization, $owner);
        $services = $this->services();
        $technicians = $this->technicians($branches, $team['technicians'], $services);
        $customers = $this->customers($branches);
        $jobs = $this->operations($organization, $branches, $customers, $services, $technicians, $team['dispatchers'][0]);
        $this->finance($organization, $jobs['completed']);
        $this->assistant($organization, $owner);
        $this->whatsapp($organization, $customers);

        Auth::logout();
        OrganizationContext::forget();

        $this->command?->info('Demo data ready for '.$organization->name.'. Sign in as owner@riyadh-cooling.test.');
    }

    private function organization(): Organization
    {
        $organization = Organization::query()->where('slug', 'riyadh-cooling-maintenance')->first()
            ?? Organization::query()->where('slug', 'riyadh-cooling-services')->first();

        $attributes = [
            'name' => 'Riyadh Cooling & Maintenance',
            'legal_name' => 'Riyadh Cooling & Maintenance',
            'country' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'locale' => 'ar',
            'status' => OrganizationStatus::Active,
            'email' => 'hello@riyadhcooling.test',
            'phone' => '0112004400',
            'settings' => ['tax_rate' => '15'],
        ];

        if ($organization) {
            $organization->update($attributes);

            return $organization;
        }

        return Organization::query()->create([
            ...$attributes,
            'slug' => 'riyadh-cooling-maintenance',
        ]);
    }

    private function member(Organization $organization, string $name, string $email, OrganizationRole $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if ($organization->members()->whereKey($user->id)->exists()) {
            $organization->members()->updateExistingPivot($user->id, ['role' => $role->value]);
        } else {
            $organization->members()->attach($user->id, ['role' => $role->value]);
        }

        return $user;
    }

    private function act(Organization $organization, User $user): void
    {
        Auth::login($user);
        OrganizationContext::set($organization, OrganizationRole::Owner);
    }

    private function resetDemoRecords(Organization $organization): void
    {
        $id = $organization->id;

        Appointment::withoutGlobalScope('organization')->where('organization_id', $id)->update(['work_order_id' => null]);
        Payment::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        Invoice::withoutGlobalScope('organization')->where('organization_id', $id)->forceDelete();
        Activity::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        Task::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        WorkOrder::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        Appointment::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        ServiceRequest::withoutGlobalScope('organization')->where('organization_id', $id)->delete();

        $technicianIds = Technician::withoutGlobalScope('organization')->where('organization_id', $id)->pluck('id');
        Technician::withoutGlobalScope('organization')->where('organization_id', $id)->forceDelete();
        Service::withoutGlobalScope('organization')->where('organization_id', $id)->forceDelete();
        Customer::withoutGlobalScope('organization')->where('organization_id', $id)->forceDelete();

        $conversationIds = AiConversation::withoutGlobalScope('organization')->where('organization_id', $id)->pluck('id');
        AiMessage::query()->whereIn('conversation_id', $conversationIds)->delete();
        AiConversation::withoutGlobalScope('organization')->where('organization_id', $id)->delete();

        WhatsAppMessage::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        WhatsAppConversation::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        WhatsAppTemplate::withoutGlobalScope('organization')->where('organization_id', $id)->delete();
        Integration::withoutGlobalScope('organization')->where('organization_id', $id)->delete();

        unset($technicianIds);
    }

    /**
     * @return array{main: Branch, north: Branch, east: Branch}
     */
    private function branches(Organization $organization): array
    {
        $rows = [
            'main' => ['Riyadh Main Branch', 'RYD-MAIN', 'Al Olaya'],
            'north' => ['North Riyadh Branch', 'RYD-NORTH', 'Al Malqa'],
            'east' => ['East Riyadh Branch', 'RYD-EAST', 'Al Rawdah'],
        ];

        $branches = [];
        foreach ($rows as $key => [$name, $code, $district]) {
            $branch = Branch::withoutGlobalScope('organization')->firstOrNew([
                'organization_id' => $organization->id,
                'code' => $code,
            ]);
            $branch->fill([
                'name' => $name,
                'city' => 'Riyadh',
                'address' => $district.', Riyadh',
                'country' => 'SA',
                'timezone' => 'Asia/Riyadh',
                'status' => BranchStatus::Active,
            ]);
            $branch->save();
            $branches[$key] = $branch;
        }

        return $branches;
    }

    /**
     * @return array{dispatchers: list<User>, accountants: list<User>, support: list<User>, technicians: list<User>}
     */
    private function team(Organization $organization, User $owner): array
    {
        $admin = $this->member($organization, 'Noura Al Saud', 'admin@riyadh-cooling.test', OrganizationRole::Admin);
        $manager = $this->member($organization, 'Riyadh Manager', 'manager@riyadh-cooling.test', OrganizationRole::Manager);
        $dispatchers = [
            $this->member($organization, 'Riyadh Dispatcher', 'dispatcher@riyadh-cooling.test', OrganizationRole::Dispatcher),
            $this->member($organization, 'Lama Al Qahtani', 'dispatcher2@riyadh-cooling.test', OrganizationRole::Dispatcher),
        ];
        $accountants = [
            $this->member($organization, 'Riyadh Accountant', 'accountant@riyadh-cooling.test', OrganizationRole::Accountant),
            $this->member($organization, 'Hanan Al Otaibi', 'accountant2@riyadh-cooling.test', OrganizationRole::Accountant),
        ];
        $support = [
            $this->member($organization, 'Riyadh Support', 'support@riyadh-cooling.test', OrganizationRole::CustomerService),
            $this->member($organization, 'Reem Al Dosari', 'support2@riyadh-cooling.test', OrganizationRole::CustomerService),
        ];

        $names = [
            'Riyadh Technician', 'Salem Al Dawsari', 'Majed Al Shamri', 'Yazeed Al Mutairi', 'Bandar Al Anazi',
            'Turki Al Shehri', 'Nawaf Al Subaie', 'Khalid Al Ghamdi', 'Saud Al Harbi', 'Omar Al Zahrani',
            'Fahad Al Ajmi', 'Abdulrahman Al Rashid',
        ];
        $technicians = [];
        foreach ($names as $index => $name) {
            $email = $index === 0 ? 'technician@riyadh-cooling.test' : sprintf('tech%02d@riyadh-cooling.test', $index);
            $technicians[] = $this->member($organization, $name, $email, OrganizationRole::Technician);
        }

        $main = Branch::withoutGlobalScope('organization')->where('organization_id', $organization->id)->where('code', 'RYD-MAIN')->firstOrFail();
        $north = Branch::withoutGlobalScope('organization')->where('organization_id', $organization->id)->where('code', 'RYD-NORTH')->firstOrFail();
        $east = Branch::withoutGlobalScope('organization')->where('organization_id', $organization->id)->where('code', 'RYD-EAST')->firstOrFail();
        $main->users()->syncWithoutDetaching([$owner->id, $admin->id, $manager->id, $technicians[0]->id, $technicians[1]->id, $technicians[2]->id, $technicians[3]->id]);
        $north->users()->syncWithoutDetaching([$dispatchers[0]->id, $accountants[0]->id, $support[0]->id, $technicians[4]->id, $technicians[5]->id, $technicians[6]->id, $technicians[7]->id]);
        $east->users()->syncWithoutDetaching([$dispatchers[1]->id, $accountants[1]->id, $support[1]->id, $technicians[8]->id, $technicians[9]->id, $technicians[10]->id, $technicians[11]->id]);

        return compact('dispatchers', 'accountants', 'support', 'technicians');
    }

    /**
     * @return list<Service>
     */
    private function services(): array
    {
        $catalog = app(CatalogService::class);
        $rows = [
            ['Split AC Maintenance', 'HVAC', 180, 60, 'فحص فلتر، تبريد، وتصريف للوحدة السبلت.'],
            ['Central AC Maintenance', 'HVAC', 450, 120, 'صيانة دورية لوحدة مركزية.'],
            ['AC Installation', 'HVAC', 1600, 240, 'تركيب وحدة سبلت جديدة مع اختبار التشغيل.'],
            ['AC Cleaning', 'HVAC', 220, 75, 'غسيل الوحدة الداخلية والخارجية.'],
            ['Refrigerant Refill', 'HVAC', 280, 60, 'فحص تسريب وتعبئة غاز حسب الضغط.'],
            ['HVAC Inspection', 'HVAC', 150, 45, 'فحص شامل قبل موسم الصيف.'],
            ['Preventive Maintenance', 'HVAC', 350, 90, 'زيارة وقائية حسب عقد الصيانة.'],
            ['Compressor Replacement', 'HVAC', 1400, 180, 'استبدال ضاغط تالف بعد موافقة العميل.'],
            ['Electrical Repair', 'Electrical', 200, 60, 'فحص قواطع وتوصيلات وحدة التكييف.'],
            ['Plumbing Repair', 'Plumbing', 180, 60, 'معالجة تسريب خط التصريف أو الماء.'],
            ['Emergency AC Repair', 'HVAC', 350, 90, 'زيارة عاجلة خارج الجدول الاعتيادي.'],
            ['Annual Maintenance Contract', 'HVAC', 2400, 60, 'عقد زيارتين وقائيتين خلال السنة.'],
            ['Filter Replacement', 'HVAC', 90, 30, 'توريد وتركيب فلاتر.'],
            ['Drain Line Cleaning', 'Plumbing', 160, 45, 'تسليك خط تصريف الوحدة الداخلية.'],
            ['Thermostat Repair', 'Electrical', 140, 45, 'ضبط أو استبدال منظم الحرارة.'],
            ['Duct Inspection', 'HVAC', 300, 90, 'فحص مجاري الهواء للمكاتب.'],
            ['Outdoor Unit Service', 'HVAC', 260, 75, 'تنظيف مكثف وفحص مراوح الوحدة الخارجية.'],
            ['Indoor Unit Service', 'HVAC', 240, 75, 'تنظيف المبخر ومعالجة صوت غير طبيعي.'],
        ];

        return array_map(fn (array $row) => $catalog->create([
            'name' => $row[0],
            'category' => $row[1],
            'base_price' => $row[2],
            'estimated_duration' => $row[3],
            'description' => $row[4],
            'status' => RecordStatus::Active->value,
        ]), $rows);
    }

    /**
     * @param  array{main: Branch, north: Branch, east: Branch}  $branches
     * @param  list<User>  $users
     * @param  list<Service>  $services
     * @return list<Technician>
     */
    private function technicians(array $branches, array $users, array $services): array
    {
        $service = app(TechnicianService::class);
        $branchList = [$branches['main'], $branches['north'], $branches['east']];
        $skills = TechnicianSkillName::cases();
        $hvac = $services[0];

        $technicians = [];
        foreach ($users as $index => $user) {
            $skill = $skills[$index % count($skills)];
            $skillRows = [['skill' => $skill->value, 'service_id' => $skill === TechnicianSkillName::Hvac ? $hvac->id : null]];
            if ($skill !== TechnicianSkillName::Hvac) {
                $skillRows[] = ['skill' => TechnicianSkillName::Hvac->value, 'service_id' => $hvac->id];
            }
            $technicians[] = $service->create([
                'user_id' => $user->id,
                'branch_id' => $branchList[$index % 3]->id,
                'employee_code' => sprintf('TEC-%02d', $index + 1),
                'availability_status' => $index % 7 === 0 ? AvailabilityStatus::Busy->value : AvailabilityStatus::Available->value,
                'hourly_rate' => 70 + ($index * 5),
                'status' => RecordStatus::Active->value,
            ], $skillRows);
        }

        return $technicians;
    }

    /**
     * @param  array{main: Branch, north: Branch, east: Branch}  $branches
     * @return list<Customer>
     */
    private function customers(array $branches): array
    {
        $service = app(CustomerService::class);
        $people = [
            'Abdullah Al Fahad', 'Sara Al Nasser', 'Mohammed Al Otaibi', 'Hessa Al Mutlaq', 'Faisal Al Ajlan',
            'Nouf Al Shammari', 'Sultan Al Qahtani', 'Amal Al Harbi', 'Ibrahim Al Dosari', 'Lina Al Saud',
            'Waleed Al Anazi', 'Maha Al Zahrani', 'Yousef Al Ghamdi', 'Dalal Al Subaie', 'Hamad Al Rashid',
            'Areej Al Shehri', 'Nasser Al Dawsari', 'Joud Al Maliki', 'Bader Al Shamri', 'Raghad Al Ajmi',
        ];
        $companies = [
            ['Nakheel Offices', 'مكاتب النخيل'],
            ['Olaya Dental Clinic', 'عيادة العليا للأسنان'],
            ['Sahafa Coffee Roasters', 'محمصة الصحافة'],
            ['Yasmin Kids Nursery', 'حضانة الياسمين'],
            ['Rawdah Tailoring', 'خياطة الروضة'],
            ['Muruj Legal Office', 'مكتب المروج للمحاماة'],
            ['Wurud Bakery', 'مخبز الورود'],
            ['Malqa Fitness Studio', 'استوديو الملقى'],
            ['Olaya Print House', 'مطبعة العليا'],
            ['Nakheel Family Restaurant', 'مطعم النخيل العائلي'],
            ['Sahafa Travel Desk', 'مكتب سفر الصحافة'],
            ['Yasmin Pharmacy', 'صيدلية الياسمين'],
            ['Rawdah Supermarket', 'تموينات الروضة'],
            ['Muruj Architects', 'مكتب المروج للاستشارات'],
            ['Wurud Salon', 'صالون الورود'],
            ['Malqa Bookstore', 'مكتبة الملقى'],
            ['Olaya Co-working', 'مساحة عمل العليا'],
            ['Nakheel Sweets', 'حلويات النخيل'],
            ['Sahafa Auto Care', 'عناية سيارات الصحافة'],
            ['Yasmin Clinic Lab', 'مختبر عيادة الياسمين'],
            ['Rawdah Guest House', 'استراحة الروضة'],
            ['Muruj Design Studio', 'استوديو المروج'],
            ['Wurud Training Center', 'مركز الورود للتدريب'],
            ['Malqa Retail Corner', 'ركن الملقى التجاري'],
            ['Olaya Apartment Block', 'عمارة العليا السكنية'],
            ['Nakheel Warehouse', 'مستودع النخيل'],
            ['Sahafa Call Center', 'مركز اتصال الصحافة'],
            ['Yasmin School Annex', 'ملحق مدرسة الياسمين'],
            ['Rawdah Clinic', 'عيادة الروضة'],
            ['Muruj Cafe', 'مقهى المروج'],
        ];
        $areas = ['Al Olaya', 'Al Malqa', 'Al Nakheel', 'Al Yasmin', 'Al Sahafa', 'Al Rawdah', 'Al Muruj', 'Al Wurud'];
        $branchList = array_values($branches);
        $customers = [];

        foreach ($people as $index => $name) {
            $area = $areas[$index % count($areas)];
            $customers[] = $service->create([
                'branch_id' => $branchList[$index % 3]->id,
                'type' => CustomerType::Individual->value,
                'name' => $name,
                'phone' => sprintf('055100%04d', $index + 1),
                'whatsapp_number' => sprintf('055100%04d', $index + 1),
                'address' => $area.', Riyadh',
                'city' => 'Riyadh',
                'country' => 'SA',
                'status' => RecordStatus::Active->value,
                'source' => RequestSource::Phone->value,
                'notes' => 'Demo household in '.$area.'.',
            ]);
        }

        foreach ($companies as $index => [$name, $arabic]) {
            $area = $areas[$index % count($areas)];
            $customers[] = $service->create([
                'branch_id' => $branchList[$index % 3]->id,
                'type' => CustomerType::Company->value,
                'name' => $name,
                'company_name' => $name,
                'phone' => sprintf('055200%04d', $index + 1),
                'whatsapp_number' => sprintf('055200%04d', $index + 1),
                'address' => $area.', Riyadh',
                'city' => 'Riyadh',
                'country' => 'SA',
                'status' => RecordStatus::Active->value,
                'source' => RequestSource::Manual->value,
                'notes' => $arabic.' — demo business account.',
            ]);
        }

        return $customers;
    }

    /**
     * @param  array{main: Branch, north: Branch, east: Branch}  $branches
     * @param  list<Customer>  $customers
     * @param  list<Service>  $services
     * @param  list<Technician>  $technicians
     * @return array{completed: list<WorkOrder>}
     */
    private function operations(Organization $organization, array $branches, array $customers, array $services, array $technicians, User $dispatcher): array
    {
        $requests = app(ServiceRequestService::class);
        $appointments = app(AppointmentService::class);
        $orders = app(WorkOrderService::class);
        $tasks = app(TaskService::class);
        $timezone = $organization->timezone;
        $today = Carbon::now($timezone)->startOfDay();
        $stories = [
            ['المكيف لا يبرد', 'The split unit runs, but the room stays warm.', Priority::High],
            ['AC is making unusual noise', 'Rattling from the outdoor fan started yesterday.', Priority::Normal],
            ['Need preventive maintenance', 'Quarterly visit before the weekend rush.', Priority::Low],
            ['Water leaking from indoor unit', 'Water drips onto the ceiling tiles.', Priority::Urgent],
            ['Central AC stopped working', 'The office package unit will not start.', Priority::High],
            ['Need AC cleaning', 'Dust on the indoor coil after a sandstorm.', Priority::Normal],
            ['Need urgent technician', 'Restaurant dining room is overheating.', Priority::Urgent],
        ];
        $taskTitles = [
            'Inspect compressor',
            'Check refrigerant pressure',
            'Clean filters',
            'Check electrical connections',
            'Test cooling performance',
            'Replace damaged component',
            'Final quality inspection',
        ];
        $targets = [
            WorkOrderStatus::Assigned, WorkOrderStatus::Assigned, WorkOrderStatus::Assigned, WorkOrderStatus::Assigned,
            WorkOrderStatus::Accepted, WorkOrderStatus::Accepted, WorkOrderStatus::Accepted,
            WorkOrderStatus::OnTheWay, WorkOrderStatus::OnTheWay,
            WorkOrderStatus::Arrived, WorkOrderStatus::Arrived,
            WorkOrderStatus::InProgress, WorkOrderStatus::InProgress, WorkOrderStatus::InProgress,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Completed, WorkOrderStatus::Completed,
            WorkOrderStatus::Cancelled, WorkOrderStatus::Cancelled, WorkOrderStatus::Cancelled,
        ];
        $completed = [];
        $branchList = array_values($branches);

        foreach ($targets as $index => $target) {
            $customer = $customers[$index % count($customers)];
            $service = $services[$index % count($services)];
            $technician = $technicians[$index % count($technicians)];
            $story = $stories[$index % count($stories)];
            $requestedAt = $today->copy()->subDays($index % 45)->setTime(9, 0);
            $request = $requests->create([
                'branch_id' => $customer->branch_id ?? $branchList[$index % 3]->id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'source' => $index % 5 === 0 ? RequestSource::Whatsapp->value : RequestSource::Phone->value,
                'subject' => $story[0],
                'description' => $story[1],
                'priority' => $story[2]->value,
                'status' => ServiceRequestStatus::New->value,
                'requested_at' => $requestedAt->copy()->utc(),
                'location' => $customer->address,
            ]);

            $day = match ($target) {
                WorkOrderStatus::Completed => $today->copy()->subDays(($index % 4) + 1),
                WorkOrderStatus::Cancelled => $today->copy()->subDays(2),
                WorkOrderStatus::Assigned, WorkOrderStatus::Accepted => $index % 2 === 0 ? $today->copy() : $today->copy()->addDay(),
                default => $today->copy(),
            };
            $hour = 8 + (($index % 4) * 2);
            [$start, $end] = $this->slot($technician->id, $day, $hour, (int) $service->estimated_duration, $timezone);
            $appointment = $appointments->create([
                'branch_id' => $request->branch_id,
                'customer_id' => $customer->id,
                'technician_id' => $technician->id,
                'service_request_id' => $request->id,
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'status' => $target === WorkOrderStatus::Completed ? AppointmentStatus::Completed->value : AppointmentStatus::Scheduled->value,
                'notes' => 'Demo visit for '.$customer->name.'.',
            ]);

            $order = $orders->create([
                'branch_id' => $request->branch_id,
                'customer_id' => $customer->id,
                'service_request_id' => $request->id,
                'appointment_id' => $appointment->id,
                'technician_id' => $technician->id,
                'title' => $service->name.' — '.$customer->name,
                'description' => $story[1],
                'priority' => $story[2]->value,
            ]);
            $order = $this->advance($order, $target, 'Filters cleaned, pressures checked, and the unit was tested.');
            if ($target === WorkOrderStatus::Completed) {
                $completed[] = $order;
            }
            if ($target === WorkOrderStatus::Cancelled) {
                $requests->mark($request->fresh(), ServiceRequestStatus::Cancelled);
            }

            foreach ([0, 1] as $taskIndex) {
                $status = match (true) {
                    $target === WorkOrderStatus::Completed => TaskStatus::Completed,
                    $target === WorkOrderStatus::Cancelled => TaskStatus::Cancelled,
                    $taskIndex === 0 && $target === WorkOrderStatus::InProgress => TaskStatus::InProgress,
                    default => TaskStatus::Todo,
                };
                $task = $tasks->create([
                    'branch_id' => $order->branch_id,
                    'assigned_to' => $technician->user_id,
                    'created_by' => $dispatcher->id,
                    'title' => $taskTitles[($index + $taskIndex) % count($taskTitles)],
                    'description' => 'Follow-up for '.$order->title,
                    'priority' => $story[2]->value,
                    'status' => TaskStatus::Todo->value,
                    'due_at' => $start->copy()->addHours($taskIndex + 1),
                    'related_type' => 'work_order',
                    'related_id' => $order->id,
                ]);
                if ($status !== TaskStatus::Todo) {
                    $tasks->update($task, ['status' => $status->value]);
                }
            }
        }

        foreach (range(0, 14) as $index) {
            $customer = $customers[($index + 7) % count($customers)];
            $service = $services[$index % count($services)];
            $story = $stories[$index % count($stories)];
            $request = $requests->create([
                'branch_id' => $customer->branch_id,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'source' => RequestSource::Website->value,
                'subject' => $story[0],
                'description' => $story[1],
                'priority' => $story[2]->value,
                'status' => ServiceRequestStatus::New->value,
                'requested_at' => $today->copy()->setTime(11, 30)->utc(),
                'preferred_date' => $index < 8 ? $today->toDateString() : $today->copy()->addDay()->toDateString(),
                'preferred_time' => sprintf('%02d:30:00', 8 + ($index % 7)),
                'location' => $customer->address,
            ]);
            if (in_array($index, [0, 1, 2, 8, 9], true)) {
                $orders->create([
                    'branch_id' => $request->branch_id,
                    'customer_id' => $customer->id,
                    'service_request_id' => $request->id,
                    'technician_id' => null,
                    'title' => $service->name.' — '.$customer->name,
                    'description' => $story[1],
                    'priority' => $story[2]->value,
                ]);
            }
            if ($index < 4) {
                $requests->mark($request, ServiceRequestStatus::Qualified);
            }
            if ($index >= 12) {
                $requests->mark($request, ServiceRequestStatus::Cancelled);
            }
        }

        $this->fillBoardDays($requests, $appointments, $orders, $today, $timezone, $customers, $services, $technicians);

        return ['completed' => $completed];
    }

    /**
     * @param  list<Customer>  $customers
     * @param  list<Service>  $services
     * @param  list<Technician>  $technicians
     */
    private function fillBoardDays(ServiceRequestService $requests, AppointmentService $appointments, WorkOrderService $orders, Carbon $today, string $timezone, array $customers, array $services, array $technicians): void
    {
        $crew = array_slice($technicians, 0, 8);
        $stories = [
            [Priority::Urgent, 'Kitchen cooling failed'],
            [Priority::High, 'Office split unit is weak'],
            [Priority::Normal, 'Filter visit'],
            [Priority::Low, 'Seasonal inspection'],
        ];

        foreach ([0, 1] as $dayOffset) {
            foreach ($crew as $techIndex => $technician) {
                $plan = [[8, AppointmentStatus::Completed], [11, AppointmentStatus::InProgress], [15, AppointmentStatus::Scheduled]];
                if ($dayOffset === 0 && $techIndex === 0) {
                    $plan[] = [7, AppointmentStatus::Scheduled];
                }
                if ($dayOffset === 1) {
                    $plan = [[8, AppointmentStatus::Scheduled], [11, AppointmentStatus::Scheduled], [15, AppointmentStatus::Scheduled]];
                }
                foreach ($plan as $slotIndex => [$hour, $appointmentStatus]) {
                    $customer = $customers[($techIndex + $slotIndex + $dayOffset) % count($customers)];
                    $service = $services[($techIndex + $slotIndex) % count($services)];
                    [$priority, $subject] = $stories[($techIndex + $slotIndex) % count($stories)];
                    $request = $requests->create([
                        'branch_id' => $technician->branch_id ?? $customer->branch_id,
                        'customer_id' => $customer->id,
                        'service_id' => $service->id,
                        'source' => RequestSource::Phone->value,
                        'subject' => $subject,
                        'description' => $subject.' for '.$customer->name.'.',
                        'priority' => $priority->value,
                        'status' => ServiceRequestStatus::New->value,
                        'requested_at' => $today->copy()->setTime($hour, 0)->utc(),
                        'preferred_date' => $today->copy()->addDays($dayOffset)->toDateString(),
                        'preferred_time' => sprintf('%02d:00:00', $hour),
                        'location' => $customer->address,
                    ]);
                    [$start, $end] = $this->slot($technician->id, $today->copy()->addDays($dayOffset), $hour, (int) $service->estimated_duration, $timezone);
                    $appointment = $appointments->create([
                        'branch_id' => $request->branch_id,
                        'customer_id' => $customer->id,
                        'technician_id' => $technician->id,
                        'service_request_id' => $request->id,
                        'scheduled_start' => $start,
                        'scheduled_end' => $end,
                        'status' => $appointmentStatus->value,
                    ]);
                    $order = $orders->create([
                        'branch_id' => $request->branch_id,
                        'customer_id' => $customer->id,
                        'service_request_id' => $request->id,
                        'appointment_id' => $appointment->id,
                        'technician_id' => $technician->id,
                        'title' => $service->name.' — '.$customer->name,
                        'description' => $subject,
                        'priority' => $priority->value,
                    ]);
                    $target = match ($appointmentStatus) {
                        AppointmentStatus::Completed => WorkOrderStatus::Completed,
                        AppointmentStatus::InProgress => WorkOrderStatus::InProgress,
                        default => WorkOrderStatus::Assigned,
                    };
                    if ($target !== WorkOrderStatus::Assigned) {
                        $this->advance($order, $target, 'Demo visit completed.');
                    }
                }
            }
        }
    }

    private function advance(WorkOrder $order, WorkOrderStatus $target, string $notes): WorkOrder
    {
        $service = app(WorkOrderService::class);
        if ($target === WorkOrderStatus::Cancelled) {
            return $service->transition($order, WorkOrderStatus::Cancelled, 'Customer postponed the visit.');
        }

        $path = [
            WorkOrderStatus::Accepted,
            WorkOrderStatus::OnTheWay,
            WorkOrderStatus::Arrived,
            WorkOrderStatus::InProgress,
            WorkOrderStatus::Completed,
        ];
        foreach ($path as $status) {
            if ($order->status === $target) {
                break;
            }
            $order = $service->transition($order, $status, $status === WorkOrderStatus::Completed ? $notes : null);
            if ($status === $target) {
                break;
            }
        }

        return $order;
    }

    /**
     * @param  list<WorkOrder>  $completed
     */
    private function finance(Organization $organization, array $completed): void
    {
        $invoices = app(InvoiceLifecycleService::class);
        $payments = app(PaymentService::class);
        $timezone = $organization->timezone;
        $outcomes = ['paid', 'paid', 'paid', 'partial', 'partial', 'issued', 'issued', 'overdue', 'draft', 'cancelled'];

        foreach (array_slice($completed, 0, 32) as $index => $order) {
            $outcome = $outcomes[$index % count($outcomes)];
            $issuedOn = Carbon::now($timezone)->subDays($index % 20)->startOfDay();
            $due = $outcome === 'overdue'
                ? $issuedOn->copy()->addDays(3)
                : Carbon::now($timezone)->addDays(14);
            if ($outcome === 'overdue' && $due->greaterThanOrEqualTo(Carbon::now($timezone)->startOfDay())) {
                $due = Carbon::now($timezone)->subDays(2);
            }

            $invoice = $invoices->generateFromWorkOrder($order->fresh());
            if ($outcome === 'draft') {
                continue;
            }

            $invoice = $invoices->issue($invoice);
            $invoice->issue_date = $issuedOn->toDateString();
            $invoice->due_date = $due->toDateString();
            $invoice->save();

            if ($outcome === 'cancelled') {
                $invoices->cancel($invoice->fresh());

                continue;
            }

            if ($outcome === 'paid' || $outcome === 'partial') {
                $amount = $outcome === 'paid' ? (string) $invoice->fresh()->total : Money::mul((string) $invoice->fresh()->total, '0.45');
                $payment = $payments->record($invoice->fresh(), [
                    'amount' => $amount,
                    'method' => $index % 2 === 0 ? PaymentMethod::BankTransfer->value : PaymentMethod::Cash->value,
                    'reference' => 'DEMO-'.$invoice->invoice_number,
                    'notes' => 'Demo receipt',
                ]);
                $payment->paid_at = $index === 0 ? now() : $issuedOn->copy()->setTime(15, 0)->utc();
                $payment->save();
            }
        }

        $invoices->markOverdue();
    }

    private function assistant(Organization $organization, User $owner): void
    {
        $open = ServiceRequest::query()->whereIn('status', [
            ServiceRequestStatus::New->value,
            ServiceRequestStatus::Qualified->value,
            ServiceRequestStatus::Scheduled->value,
            ServiceRequestStatus::InProgress->value,
        ])->count();
        $overdue = Invoice::query()->where('status', InvoiceStatus::Overdue)->get();
        $overdueTotal = '0.00';
        foreach ($overdue as $invoice) {
            $overdueTotal = Money::add($overdueTotal, (string) $invoice->amount_due);
        }

        $conversation = AiConversation::query()->create([
            'user_id' => $owner->id,
            'channel' => 'staff',
            'title' => 'ملخص التشغيل',
            'status' => 'active',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'ما هي الطلبات المفتوحة؟',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'لديكم '.$open.' طلبات مفتوحة حالياً بين الفروع الثلاثة.',
        ]);

        $billing = AiConversation::query()->create([
            'user_id' => $owner->id,
            'channel' => 'staff',
            'title' => 'الفواتير المتأخرة',
            'status' => 'active',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $billing->id,
            'role' => 'user',
            'content' => 'كم إجمالي الفواتير المتأخرة؟',
        ]);
        AiMessage::query()->create([
            'conversation_id' => $billing->id,
            'role' => 'assistant',
            'content' => 'إجمالي الفواتير المتأخرة هو '.$overdueTotal.' SAR عبر '.$overdue->count().' فواتير.',
        ]);
    }

    /**
     * @param  list<Customer>  $customers
     */
    private function whatsapp(Organization $organization, array $customers): void
    {
        $integration = Integration::query()->create([
            'provider' => 'fake',
            'type' => 'whatsapp',
            'status' => 'active',
            'credentials' => ['access_token' => 'demo-token-not-real'],
            'metadata' => [
                'phone_number_id' => '100000000000099',
                'display_phone' => '+966112004400',
            ],
        ]);
        $display = '+966112004400';
        $threads = [
            [$customers[0], 'open', 2, 'السلام عليكم، المكيف عندي ما بيبرد.', 'وعليكم السلام 👋 يسعدنا مساعدتك. هل ترغب في حجز موعد لفحص المكيف؟'],
            [$customers[1], 'open', 0, 'نحتاج تنظيف مكيف المكتب هذا الأسبوع.', 'تم استلام الطلب. أقرب زيارة متاحة غداً الساعة 10 صباحاً.'],
            [$customers[2], 'human_required', 1, 'الفني تأخر عن الموعد.', 'حوّلت المحادثة لأحد موظفي الخدمة للمتابعة معكم مباشرة.'],
            [$customers[3], 'open', 0, 'شكراً، التكييف رجع يبرد.', 'سعداء بذلك. لو احتجتم زيارة وقائية لاحقاً نحن جاهزون.'],
        ];

        foreach ($threads as $index => [$customer, $status, $unread, $inbound, $outbound]) {
            $conversation = WhatsAppConversation::query()->create([
                'integration_id' => $integration->id,
                'customer_id' => $customer->id,
                'contact_phone' => $customer->phone,
                'contact_name' => $customer->name,
                'status' => $status,
                'unread_count' => $unread,
                'last_message_at' => now()->subHours($index),
            ]);
            foreach ([[$inbound, 'inbound', $customer->phone, $display, 'received'], [$outbound, 'outbound', $display, $customer->phone, 'delivered']] as $messageIndex => [$text, $direction, $from, $to, $messageStatus]) {
                WhatsAppMessage::query()->create([
                    'customer_id' => $customer->id,
                    'integration_id' => $integration->id,
                    'whatsapp_conversation_id' => $conversation->id,
                    'external_message_id' => 'demo-wa-'.$index.'-'.$messageIndex,
                    'direction' => $direction,
                    'from_number' => $from,
                    'to_number' => $to,
                    'message_type' => 'text',
                    'text' => $text,
                    'status' => $messageStatus,
                    'sent_at' => now()->subHours($index)->addMinutes($messageIndex),
                    'metadata' => ['author' => $direction === 'inbound' ? 'customer' : 'assistant'],
                ]);
            }
        }

        $this->whatsappTemplates($integration, $customers, $display);
    }

    /**
     * Approved reminder and confirmation templates (as after whatsapp:templates:sync),
     * opt-in for a few households, and one conversation whose 24-hour window has closed.
     *
     * @param  list<Customer>  $customers
     */
    private function whatsappTemplates(Integration $integration, array $customers, string $display): void
    {
        foreach ((array) config('whatsapp.templates') as $purpose => $definition) {
            foreach ((array) config('whatsapp.template_languages') as $language) {
                WhatsAppTemplate::query()->create([
                    'name' => $definition['name'],
                    'language' => $language,
                    'purpose' => $purpose,
                    'status' => 'approved',
                    'provider_status' => 'APPROVED',
                    'category' => 'UTILITY',
                    'parameters' => $definition['parameters'],
                    'synced_at' => now()->subDay(),
                ]);
            }
        }

        foreach (array_slice($customers, 0, 6) as $customer) {
            $customer->update(['whatsapp_opt_in_at' => now()->subMonth()]);
        }

        $threadCustomerIds = array_map(fn (Customer $customer) => $customer->id, array_slice($customers, 0, 4));
        $appointment = Appointment::query()
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->where('scheduled_start', '>', now())
            ->whereNotIn('customer_id', $threadCustomerIds)
            ->orderBy('scheduled_start')
            ->first();
        $customer = $appointment?->customer;

        if ($customer === null) {
            return;
        }

        $conversation = WhatsAppConversation::query()->create([
            'integration_id' => $integration->id,
            'customer_id' => $customer->id,
            'contact_phone' => $customer->phone,
            'contact_name' => $customer->name,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => now()->subDays(2),
        ]);

        foreach ([['متى يوصل الفني؟', 'inbound', $customer->phone, $display, 'received', 49], ['الفني في الطريق إليكم، الوصول خلال ساعة تقريباً.', 'outbound', $display, $customer->phone, 'read', 48]] as $index => [$text, $direction, $from, $to, $status, $hoursAgo]) {
            $message = new WhatsAppMessage([
                'customer_id' => $customer->id,
                'integration_id' => $integration->id,
                'whatsapp_conversation_id' => $conversation->id,
                'external_message_id' => 'demo-wa-closed-'.$index,
                'direction' => $direction,
                'from_number' => $from,
                'to_number' => $to,
                'message_type' => 'text',
                'text' => $text,
                'status' => $status,
                'sent_at' => now()->subHours($hoursAgo),
                'metadata' => ['author' => $direction === 'inbound' ? 'customer' : 'staff'],
            ]);
            $message->forceFill(['created_at' => now()->subHours($hoursAgo), 'updated_at' => now()->subHours($hoursAgo)])->save();
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function slot(string $technicianId, Carbon $day, int $hour, int $minutes, string $timezone): array
    {
        $start = $day->copy()->timezone($timezone)->setTime($hour, 0, 0);
        $end = $start->copy()->addMinutes(max($minutes, 60));

        while ($this->overlaps($technicianId, $start, $end)) {
            $start->addHours(2);
            if ($start->hour >= 18) {
                $start->addDay()->setTime(8, 0, 0);
            }
            $end = $start->copy()->addMinutes(max($minutes, 60));
        }

        $this->busy[$technicianId][] = [$start->copy(), $end->copy()];

        return [$start->copy()->utc(), $end->copy()->utc()];
    }

    private function overlaps(string $technicianId, Carbon $start, Carbon $end): bool
    {
        foreach ($this->busy[$technicianId] ?? [] as [$busyStart, $busyEnd]) {
            if ($start->lt($busyEnd) && $end->gt($busyStart)) {
                return true;
            }
        }

        return false;
    }
}
