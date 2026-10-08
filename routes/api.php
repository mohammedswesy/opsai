<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    /** @var User $user */
    $user = $request->user();

    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});
