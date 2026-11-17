<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.admin.login');
    }

    public function login(AdminLoginRequest $request, AuthService $authService): RedirectResponse
    {
        $authenticated = $authService->loginAdmin(
            $request->input('email'),
            $request->input('password'),
            $request
        );

        if (!$authenticated) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => trans('auth.failed'),
            ]);
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logoutAdmin($request);

        return redirect()->route('admin.login')->with('info', 'Administrator logged out.');
    }
}
