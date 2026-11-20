<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->trashed()) {
            if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken() !== null) {
                $user->currentAccessToken()->delete();
            }

            return ApiResponse::error('Your doctor account is inactive.', 'unauthenticated', 401);
        }

        return $next($request);
    }
}
