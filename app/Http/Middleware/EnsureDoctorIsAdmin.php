<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $doctor = auth('doctor')->user();

        if ($doctor === null || !$doctor->isAdmin()) {
            abort(403, 'Administrator access required.');
        }

        return $next($request);
    }
}
