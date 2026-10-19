<?php

namespace App\AI\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIMessage;
use App\AI\Data\AIResponse;
use App\AI\Data\AIToolCall;

class FakeAIProvider implements AIProviderInterface
{
    public function complete(array $messages, array $tools): AIResponse
    {
        $names = array_map(fn (array $tool): string => (string) ($tool['function']['name'] ?? ''), $tools);
        $customer = in_array('get_basic_service_information', $names, true);
        $original = $this->latestUserText($messages);
        $toolMessage = $this->toolAfterLatestUser($messages);

        if ($customer) {
            return $this->customerResponse($messages);
        }

        if ($toolMessage !== null) {
            return new AIResponse($this->summarize($toolMessage), [], [
                'input_tokens' => null,
                'output_tokens' => null,
                'estimated_cost' => null,
            ], 'fake');
        }

        $text = strtolower($this->latestUserText($messages));

        if (str_contains($text, 'malformed task')) {
            return $this->call('create_task', []);
        }

        if (str_contains($text, 'open request')) {
            return $this->call('get_open_service_requests', []);
        }

        if (str_contains($text, 'overdue')) {
            return $this->call('get_overdue_invoices', []);
        }

        if (str_contains($text, 'scheduled today') || str_contains($text, 'jobs are scheduled')) {
            return $this->call('get_today_appointments', []);
        }

        if (str_contains($text, 'technicians are available') || str_contains($text, 'which technicians')) {
            return $this->call('get_available_technicians', []);
        }

        if (str_contains($text, 'outstanding') || str_contains($text, 'owe')) {
            return $this->call('get_dashboard_metrics', []);
        }

        if (str_contains($text, 'revenue') || str_contains($text, 'this month')) {
            return $this->call('get_dashboard_metrics', ['period' => 'this_month']);
        }

        if (str_contains($text, 'workload')) {
            return $this->call('get_technician_workload', []);
        }

        if (str_contains($text, 'last five') || str_contains($text, 'work orders')) {
            return $this->call('get_recent_work_orders', ['limit' => 5]);
        }

        if (str_contains($text, 'create a task') || str_contains($text, 'create task')) {
            return $this->call('create_task', [
                'title' => 'Follow up for the technician',
                'assignee_name' => 'Riyadh Technician',
                'priority' => 'normal',
            ]);
        }

        if (str_contains($text, 'invoice') || str_contains($text, 'payment') || str_contains($text, 'refund')) {
            return new AIResponse('I cannot change invoices or payments. Use the finance screens for that.', [], [], 'fake');
        }

        return new AIResponse('Ask about requests, appointments, technicians, work orders, tasks, or invoices.', [], [], 'fake');
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function customerResponse(array $messages): AIResponse
    {
        $original = $this->latestUserText($messages);
        $toolMessages = $this->toolsAfterLatestUser($messages);
        $text = mb_strtolower($original);
        $arabic = preg_match('/\p{Arabic}/u', $original) === 1;

        if ($toolMessages !== []) {
            return $this->customerAfterTools($original, $toolMessages, $arabic);
        }

        if ($this->containsAny($text, ['invoice', 'revenue', 'overdue', 'workload', 'employee', 'money', 'فواتير', 'إيراد', 'refund', 'خصم'])) {
            return new AIResponse('I can only help with your own service request or appointment.', [], [], 'fake');
        }

        if ($this->containsAny($original, ['شكوى', 'تأخر', 'زعلان', 'سيء']) || $this->containsAny($text, ['complaint', 'unhappy', 'technician was late'])) {
            $reply = $arabic
                ? 'نعتذر عن هالتجربة. إذا تبي، نحول المتابعة لموظف. ما نقدر نعد بتعويض أو خصم من هنا.'
                : 'Sorry about that. A teammate can follow up if you want. I can’t promise a refund or a discount from here.';

            return new AIResponse($reply, [], [], 'fake');
        }

        if ($this->containsAny($original, ['متى موعدي', 'موعدي']) || str_contains($text, 'my appointment') || str_contains($text, 'when is my appointment')) {
            return $this->call('get_customer_appointment', []);
        }

        if ($this->containsAny($original, ['وين وصل', 'طلبي', 'طلب الصيانة']) || str_contains($text, 'my request') || str_contains($text, 'request status')) {
            return $this->call('get_customer_service_request', []);
        }

        if ($this->containsAny($original, ['وش الخدمات', 'كم سعر', 'الأسعار']) || str_contains($text, 'what services') || str_contains($text, 'how much is')) {
            return $this->call('get_basic_service_information', []);
        }

        if ($this->containsAny($original, ['فني اليوم', 'عندكم فني']) || str_contains($text, 'technician today') || str_contains($text, 'available today')) {
            return $this->call('get_available_slot', $this->window(10, 11));
        }

        $chosen = $this->chosenWindow($original);
        $offered = str_contains($this->previousAssistant($messages), 'الأوقات الفاضية')
            || str_contains($this->previousAssistant($messages), 'Open times');
        if ($chosen !== null && ($this->wantsVisit($original, $text) || $offered)) {
            return $this->call('get_available_slot', $this->window($chosen[0], $chosen[1]));
        }

        if ($this->wantsVisit($original, $text)) {
            return $this->call('get_available_slot', $this->window(10, 11));
        }

        if (str_contains($text, 'not cooling') || str_contains($text, 'air conditioner')) {
            return $this->call('create_service_request', [
                'subject' => 'Air conditioner is not cooling',
                'description' => $original,
                'priority' => 'normal',
            ]);
        }

        if ($arabic && $this->containsAny($original, ['حار', 'ضعيف']) && $this->containsAny($this->previousUser($messages), ['يبرد', 'تبريد'])) {
            return $this->call('create_service_request', [
                'subject' => 'المكيف لا يبرد',
                'description' => $original,
                'priority' => str_contains($original, 'حار') ? 'high' : 'normal',
            ]);
        }

        if ($arabic && $this->containsAny($original, ['ما يبرد', 'لا يبرد', 'ما بيبرد', 'لا يعمل'])) {
            return new AIResponse('وعليكم السلام 👋 أكيد نساعدك. هل التبريد ضعيف أم المكيف يطلع هواء حار تمامًا؟', [], [], 'fake');
        }

        if ($arabic) {
            return new AIResponse('كيف نقدر نساعدك؟ طلب صيانة أو موعد؟', [], [], 'fake');
        }

        return new AIResponse('How can we help with your service request or appointment?', [], [], 'fake');
    }

    /**
     * @param  list<AIMessage>  $toolMessages
     */
    private function customerAfterTools(string $original, array $toolMessages, bool $arabic): AIResponse
    {
        $latest = $toolMessages[array_key_last($toolMessages)];
        $payload = json_decode((string) $latest->content, true);
        $tool = (string) ($latest->metadata['tool'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $offering = $this->chosenWindow($original) === null;

        if ($tool === 'get_available_slot' && $offering && count($toolMessages) === 1) {
            return $this->call('get_available_slot', $this->window(14, 15));
        }

        if ($tool === 'get_available_slot' && $offering) {
            return new AIResponse($this->offerSlots($toolMessages, $arabic), [], [], 'fake');
        }

        if ($tool === 'get_available_slot' && ($payload['success'] ?? false) === true && ($data['available'] ?? false) === true) {
            return $this->call('request_appointment', [
                'technician_name' => (string) $data['technician_name'],
                'starts_at' => (string) $data['starts_at'],
                'ends_at' => (string) $data['ends_at'],
            ]);
        }

        if ($tool === 'get_available_slot') {
            return new AIResponse('That time is not available.', [], [], 'fake');
        }

        if (($payload['success'] ?? false) !== true) {
            return new AIResponse('I could not retrieve that from the application.', [], [], 'fake');
        }

        return new AIResponse($this->customerFact($tool, $data, $arabic), [], [], 'fake');
    }

    /**
     * @param  list<AIMessage>  $toolMessages
     */
    private function offerSlots(array $toolMessages, bool $arabic): string
    {
        $open = [];

        foreach ($toolMessages as $message) {
            $payload = json_decode((string) $message->content, true);
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            if (($payload['success'] ?? false) === true && ($data['available'] ?? false) === true) {
                $open[] = (string) $data['starts_at'].' – '.(string) $data['ends_at'];
            }
        }

        if ($open === []) {
            return $arabic ? 'ما لقيت وقت فاضي في الأوقات اللي فحصتها.' : 'I checked the schedule and those times are not available.';
        }

        $list = implode($arabic ? ' أو ' : ' or ', $open);

        return $arabic
            ? 'فحصت الجدول. الأوقات الفاضية: '.$list.'. أي وقت يناسبك؟'
            : 'I checked the schedule. Open times: '.$list.'. Which one works for you?';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function customerFact(string $tool, array $data, bool $arabic): string
    {
        if ($tool === 'get_customer_appointment') {
            $rows = is_array($data['appointments'] ?? null) ? $data['appointments'] : [];

            if ($rows === []) {
                return $arabic ? 'ما لقيت موعد قادم باسمك.' : 'I could not find an upcoming appointment for you.';
            }

            $first = $rows[0];

            return $arabic
                ? 'موعدك المسجل: '.($first['starts_at'] ?? '').' والحالة '.($first['status'] ?? '').'.'
                : 'Your appointment is '.($first['starts_at'] ?? '').' and the status is '.($first['status'] ?? '').'.';
        }

        if ($tool === 'get_customer_service_request') {
            $rows = is_array($data['requests'] ?? null) ? $data['requests'] : [];

            if ($rows === []) {
                return $arabic ? 'ما لقيت طلب صيانة باسمك.' : 'I could not find a service request for you.';
            }

            $first = $rows[0];

            return $arabic
                ? 'طلبك: '.($first['subject'] ?? '').' والحالة '.($first['status'] ?? '').'.'
                : 'Your request is '.($first['subject'] ?? '').' and the status is '.($first['status'] ?? '').'.';
        }

        if ($tool === 'get_basic_service_information') {
            $rows = is_array($data['services'] ?? null) ? $data['services'] : [];
            $names = array_map(fn (mixed $row): string => is_array($row) ? (string) ($row['name'] ?? '') : '', $rows);

            return $arabic
                ? 'الخدمات المسجلة: '.implode('، ', array_filter($names)).'.'
                : 'The listed services are: '.implode(', ', array_filter($names)).'.';
        }

        return 'Here is the application result for '.$tool.'.';
    }

    private function wantsVisit(string $original, string $lower): bool
    {
        return $this->containsAny($original, ['بكرة', 'غدا', 'غداً', 'فني', 'موعد', 'احجز'])
            || str_contains($lower, 'tomorrow')
            || str_contains($lower, 'appointment')
            || str_contains($lower, 'technician');
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function chosenWindow(string $original): ?array
    {
        $lower = mb_strtolower($original);

        if (str_contains($lower, 'at 4') || str_contains($original, 'الساعة 4') || str_contains($lower, '16:00') || str_contains($lower, '4 pm')) {
            return [16, 17];
        }

        if (str_contains($lower, '14:00') || str_contains($original, 'العصر') || str_contains($lower, '2 pm')) {
            return [14, 15];
        }

        if (str_contains($lower, '10:00') || str_contains($original, 'الصباح') || preg_match('/(^|\s)10($|\s)/u', $lower) === 1) {
            return [10, 11];
        }

        return null;
    }

    /**
     * @return array{starts_at: string, ends_at: string}
     */
    private function window(int $startHour, int $endHour): array
    {
        return [
            'starts_at' => now()->addDay()->setTime($startHour, 0)->format('Y-m-d H:i'),
            'ends_at' => now()->addDay()->setTime($endHour, 0)->format('Y-m-d H:i'),
        ];
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function previousAssistant(array $messages): string
    {
        $seenUser = false;

        foreach (array_reverse($messages) as $message) {
            if ($message->role === 'user') {
                $seenUser = true;

                continue;
            }

            if ($seenUser && $message->role === 'assistant' && is_string($message->content) && $message->content !== '') {
                return $message->content;
            }
        }

        return '';
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function previousUser(array $messages): string
    {
        $skipped = false;

        foreach (array_reverse($messages) as $message) {
            if ($message->role !== 'user') {
                continue;
            }

            if (! $skipped) {
                $skipped = true;

                continue;
            }

            return (string) $message->content;
        }

        return '';
    }

    /**
     * @param  list<AIMessage>  $messages
     * @return list<AIMessage>
     */
    private function toolsAfterLatestUser(array $messages): array
    {
        $lastUserIndex = null;

        foreach ($messages as $index => $message) {
            if ($message->role === 'user') {
                $lastUserIndex = $index;
            }
        }

        if ($lastUserIndex === null) {
            return [];
        }

        $tools = [];

        foreach (array_slice($messages, $lastUserIndex + 1) as $message) {
            if ($message->role === 'tool') {
                $tools[] = $message;
            }
        }

        return $tools;
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function call(string $name, array $arguments): AIResponse
    {
        return new AIResponse(null, [
            new AIToolCall('call_'.$name, $name, $arguments),
        ], [], 'fake');
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function latestUserText(array $messages): string
    {
        foreach (array_reverse($messages) as $message) {
            if ($message->role === 'user') {
                return (string) $message->content;
            }
        }

        return '';
    }

    /**
     * @param  list<AIMessage>  $messages
     */
    private function toolAfterLatestUser(array $messages): ?AIMessage
    {
        $lastUserIndex = null;

        foreach ($messages as $index => $message) {
            if ($message->role === 'user') {
                $lastUserIndex = $index;
            }
        }

        if ($lastUserIndex === null) {
            return null;
        }

        for ($index = count($messages) - 1; $index > $lastUserIndex; $index--) {
            if ($messages[$index]->role === 'tool') {
                return $messages[$index];
            }
        }

        return null;
    }

    private function summarize(AIMessage $message): string
    {
        $payload = json_decode((string) $message->content, true);
        if (! is_array($payload) || ($payload['success'] ?? false) !== true) {
            return 'I could not retrieve that from the application.';
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $tool = (string) ($message->metadata['tool'] ?? '');

        return match ($tool) {
            'get_open_service_requests' => 'There are '.$data['count'].' open service requests.',
            'get_today_appointments' => 'There are '.$data['count'].' jobs scheduled today.',
            'get_available_technicians' => 'There are '.$data['count'].' available technicians.',
            'get_overdue_invoices' => 'There are '.$data['count'].' overdue invoices totaling '.($data['currency'] ?? '').' '.($data['total_outstanding'] ?? '0.00').'.',
            'get_dashboard_metrics' => $this->metricsSentence($data),
            'get_recent_work_orders' => 'Here are the latest '.count($data['work_orders'] ?? []).' work orders.',
            'get_technician_workload' => $this->workloadSentence($data),
            default => 'Here is the application result for '.$tool.'.',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function metricsSentence(array $data): string
    {
        $currency = (string) ($data['currency'] ?? '');
        $outstanding = (string) ($data['outstanding'] ?? '0.00');
        $overdue = (string) ($data['overdue'] ?? '0.00');
        $revenue = (string) ($data['revenue'] ?? '0.00');

        return "Outstanding is {$currency} {$outstanding}. Overdue is {$currency} {$overdue}. Revenue in the selected period is {$currency} {$revenue}.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function workloadSentence(array $data): string
    {
        $highest = is_array($data['highest'] ?? null) ? $data['highest'] : null;

        if ($highest === null) {
            return 'No technician has an active workload.';
        }

        return $highest['name'].' has the highest workload with '.$highest['count'].' active work orders.';
    }
}
