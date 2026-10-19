<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Models\WhatsAppConversation;
use App\Models\WorkOrder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'customer' => Customer::class,
            'service_request' => ServiceRequest::class,
            'appointment' => Appointment::class,
            'work_order' => WorkOrder::class,
            'task' => Task::class,
            'invoice' => Invoice::class,
            'payment' => Payment::class,
            'whatsapp_conversation' => WhatsAppConversation::class,
        ]);

        RateLimiter::for('auth', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $limit = app()->environment('local') ? 30 : 5;

            return Limit::perMinute($limit)->by($request->ip());
        });

        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute((int) config('ai.rate_limit_per_minute'))->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('whatsapp-webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
