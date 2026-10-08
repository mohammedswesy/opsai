<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationUserController;
use Illuminate\Support\Facades\Route;

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
    });
});
