<?php

use App\Http\Controllers\AiPageController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchBoardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\WhatsAppInboxController;
use App\Http\Controllers\WhatsAppIntegrationController;
use App\Http\Controllers\WhatsAppSimulationController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->middleware('throttle:whatsapp-webhook');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'store'])->middleware('throttle:whatsapp-webhook');

Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'createLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/register', [AuthController::class, 'createRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::get('/forgot-password', [AuthController::class, 'createForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:auth')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'createResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth')->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::middleware('organization')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/ai/{conversation?}', AiPageController::class)->name('ai.show');
        Route::get('/organization', [OrganizationController::class, 'edit'])->name('organization.edit');
        Route::put('/organization', [OrganizationController::class, 'update'])->name('organization.update');

        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/branches/create', [BranchController::class, 'create'])->name('branches.create');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
        Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');

        Route::get('/users', [OrganizationUserController::class, 'index'])->name('users.index');
        Route::post('/users', [OrganizationUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [OrganizationUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [OrganizationUserController::class, 'destroy'])->name('users.destroy');

        Route::resource('customers', CustomerController::class);
        Route::resource('services', ServiceController::class)->except(['show']);
        Route::resource('technicians', TechnicianController::class);
        Route::resource('requests', ServiceRequestController::class)->except(['destroy'])->parameters([
            'requests' => 'service_request',
        ]);
        Route::get('/dispatch', DispatchBoardController::class)->name('dispatch');
        Route::resource('appointments', AppointmentController::class)->except(['show', 'destroy']);
        Route::resource('work-orders', WorkOrderController::class)->except(['edit', 'update', 'destroy'])->parameters([
            'work-orders' => 'work_order',
        ]);
        Route::post('/work-orders/{work_order}/transition', [WorkOrderController::class, 'transition'])->name('work-orders.transition');
        Route::post('/work-orders/{work_order}/assign', [WorkOrderController::class, 'assign'])->name('work-orders.assign');
        Route::post('/work-orders/{work_order}/invoice', [InvoiceController::class, 'fromWorkOrder'])->name('work-orders.invoice');
        Route::resource('invoices', InvoiceController::class)->except(['destroy']);
        Route::post('/invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
        Route::resource('payments', PaymentController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund'])->name('payments.refund');
        Route::resource('tasks', TaskController::class)->except(['show', 'destroy']);
        Route::get('/whatsapp', [WhatsAppInboxController::class, 'index'])->name('whatsapp.index');
        Route::post('/whatsapp/simulate', [WhatsAppSimulationController::class, 'store'])->name('whatsapp.simulate');
        Route::get('/whatsapp/{conversation}', [WhatsAppInboxController::class, 'show'])->name('whatsapp.show');
        Route::post('/whatsapp/{conversation}/reply', [WhatsAppInboxController::class, 'reply'])->name('whatsapp.reply');
        Route::post('/whatsapp/{conversation}/resume', [WhatsAppInboxController::class, 'resume'])->name('whatsapp.resume');
        Route::get('/settings/integrations/whatsapp', [WhatsAppIntegrationController::class, 'edit'])->name('whatsapp.settings');
        Route::put('/settings/integrations/whatsapp', [WhatsAppIntegrationController::class, 'update'])->name('whatsapp.settings.update');
        Route::post('/settings/integrations/whatsapp/verify', [WhatsAppIntegrationController::class, 'verify'])->name('whatsapp.settings.verify');
    });
});
