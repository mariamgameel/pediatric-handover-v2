<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\EnsureDoctorIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            return $request->is('admin*') ? route('admin.login') : route('login');
        });

        $middleware->redirectUsersTo(function (Request $request): string {
            if (auth('admin')->check()) {
                return route('admin.dashboard');
            }

            return route('dashboard');
        });

        $middleware->alias([
            'doctor.active' => EnsureDoctorIsActive::class,
            'admin.active' => EnsureAdminIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
