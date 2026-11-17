<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('admin')->check() || auth('admin')->id() !== null) {
            $admin = auth('admin')->user();

            if ($admin === null || $admin->trashed()) {
                auth('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('admin.login')->with('error', 'Your administrator account is inactive.');
            }
        }

        return $next($request);
    }
}
