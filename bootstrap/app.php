<?php

use App\Http\Middleware\EnsureCurrentOrganization;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecureHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->append(SecureHeaders::class);
        $middleware->statefulApi();

        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
        ]);

        $middleware->alias([
            'organization' => EnsureCurrentOrganization::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureCurrentOrganization::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
