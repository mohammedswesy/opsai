<?php

use App\Http\Controllers\AiActionController;
use App\Http\Controllers\AiChatController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'organization', 'throttle:ai'])->group(function () {
    Route::post('/ai/chat', AiChatController::class)->name('ai.chat');
    Route::post('/ai/actions/{action}/confirm', [AiActionController::class, 'confirm'])->name('ai.actions.confirm');
    Route::post('/ai/actions/{action}/reject', [AiActionController::class, 'reject'])->name('ai.actions.reject');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    /** @var User $user */
    $user = $request->user();

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});
