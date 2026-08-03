<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureApprovedReseller;
use App\Http\Middleware\EnsureCustomerPortal;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->trustHosts(
            at: fn (): array => config('security.trusted_hosts'),
            subdomains: false,
        );
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
        );
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'reseller' => EnsureApprovedReseller::class,
            'customer.portal' => EnsureCustomerPortal::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
