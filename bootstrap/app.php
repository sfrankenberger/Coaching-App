<?php

use App\Http\Middleware\EnsureMembership;
use App\Http\Middleware\TenantWebAuthn;
use App\Tenancy\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Mandant zuerst, damit Session, Auth und alles Weitere ihn kennen.
        $middleware->web(prepend: [IdentifyTenant::class]);
        $middleware->web(append: [TenantWebAuthn::class]);
        $middleware->alias(['membership' => EnsureMembership::class]);
        $middleware->redirectGuestsTo(fn (Request $request) => route('anmelden', ['weiter' => $request->getRequestUri()]));
        $middleware->redirectUsersTo(fn () => route('home'));
        $middleware->validateCsrfTokens(except: ['hooks/*']);
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
