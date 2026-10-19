<?php

namespace App\Providers;

use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIException;
use App\AI\Providers\FakeAIProvider;
use App\AI\Providers\OpenAIProvider;
use App\AI\Tools\AITool;
use App\AI\Tools\AIToolRegistry;
use App\AI\Tools\AssignTechnicianTool;
use App\AI\Tools\CreateAppointmentTool;
use App\AI\Tools\CreateServiceRequestTool;
use App\AI\Tools\CreateTaskTool;
use App\AI\Tools\CreateWorkOrderTool;
use App\AI\Tools\GetAvailableSlotTool;
use App\AI\Tools\GetAvailableTechniciansTool;
use App\AI\Tools\GetBasicServiceInformationTool;
use App\AI\Tools\GetCustomerAppointmentTool;
use App\AI\Tools\GetCustomerBalanceTool;
use App\AI\Tools\GetCustomerHistoryTool;
use App\AI\Tools\GetCustomerServiceRequestTool;
use App\AI\Tools\GetCustomerTool;
use App\AI\Tools\GetDashboardMetricsTool;
use App\AI\Tools\GetInvoiceTool;
use App\AI\Tools\GetOpenServiceRequestsTool;
use App\AI\Tools\GetOverdueInvoicesTool;
use App\AI\Tools\GetRecentTasksTool;
use App\AI\Tools\GetRecentWorkOrdersTool;
use App\AI\Tools\GetTechnicianWorkloadTool;
use App\AI\Tools\GetTodayAppointmentsTool;
use App\AI\Tools\GetWorkOrderTool;
use App\AI\Tools\RequestAppointmentTool;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app): AIProviderInterface {
            $provider = (string) config('ai.provider');

            if ($provider === 'openai') {
                return $app->make(OpenAIProvider::class);
            }

            if ($provider === 'fake' && ! $app->environment('production')) {
                return $app->make(FakeAIProvider::class);
            }

            return new class implements AIProviderInterface
            {
                public function complete(array $messages, array $tools): never
                {
                    throw new AIException('The assistant is not configured.', 503, [
                        'reason' => 'unsupported_provider',
                    ]);
                }
            };
        });

        $this->app->singleton(AIToolRegistry::class, function ($app) {
            $registry = new AIToolRegistry;
            $tools = [
                GetDashboardMetricsTool::class,
                GetOpenServiceRequestsTool::class,
                GetTodayAppointmentsTool::class,
                GetAvailableTechniciansTool::class,
                GetCustomerTool::class,
                GetCustomerHistoryTool::class,
                GetOverdueInvoicesTool::class,
                GetInvoiceTool::class,
                GetCustomerBalanceTool::class,
                GetRecentWorkOrdersTool::class,
                GetWorkOrderTool::class,
                GetRecentTasksTool::class,
                GetTechnicianWorkloadTool::class,
                CreateTaskTool::class,
                CreateServiceRequestTool::class,
                CreateAppointmentTool::class,
                AssignTechnicianTool::class,
                CreateWorkOrderTool::class,
                GetCustomerServiceRequestTool::class,
                GetCustomerAppointmentTool::class,
                GetBasicServiceInformationTool::class,
                GetAvailableSlotTool::class,
                RequestAppointmentTool::class,
            ];

            foreach ($tools as $tool) {
                $resolved = $app->make($tool);
                if ($resolved instanceof AITool) {
                    $registry->register($resolved);
                }
            }

            return $registry;
        });
    }
}
