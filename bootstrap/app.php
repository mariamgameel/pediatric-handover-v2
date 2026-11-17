<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureDoctorIsActive;
use App\Http\Middleware\EnsureDoctorIsAdmin;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => $request->is('api/*') || $request->expectsJson() ? null : route('login')
        );

        $middleware->redirectUsersTo(fn (): string => route('dashboard'));

        $middleware->alias([
            'doctor.active' => EnsureDoctorIsActive::class,
            'admin' => EnsureDoctorIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::error('Validation failed.', 'validation_failed', 422, $e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::error('Unauthenticated.', 'unauthenticated', 401);
            }

            if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException) {
                return ApiResponse::error('This action is unauthorized.', 'forbidden', 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return ApiResponse::error('Resource not found.', 'not_found', 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return ApiResponse::error('Method not allowed.', 'method_not_allowed', 405);
            }

            if ($e instanceof ThrottleRequestsException) {
                return ApiResponse::error('Too many requests.', 'too_many_requests', 429);
            }

            if ($e instanceof ConflictHttpException) {
                return ApiResponse::error($e->getMessage() ?: 'Conflict.', 'conflict', 409);
            }

            if ($e instanceof HttpExceptionInterface) {
                return ApiResponse::error(
                    $e->getMessage() ?: 'HTTP error occurred.',
                    'http_error',
                    $e->getStatusCode()
                );
            }

            $message = app()->isProduction() ? 'Internal server error.' : $e->getMessage();

            return ApiResponse::error($message, 'server_error', 500);
        });
    })->create();
