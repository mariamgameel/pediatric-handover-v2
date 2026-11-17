<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DoctorLoginRequest;
use App\Http\Requests\DoctorRegisterRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorAuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.doctor.register');
    }

    public function register(DoctorRegisterRequest $request, AuthService $authService): RedirectResponse
    {
        $authService->registerDoctor($request->validated(), $request);

        return redirect()->route('dashboard')->with('success', 'Registration successful. Welcome!');
    }

    public function showLogin(): View
    {
        return view('auth.doctor.login');
    }

    public function login(DoctorLoginRequest $request, AuthService $authService): RedirectResponse
    {
        $authenticated = $authService->loginDoctor(
            $request->input('email'),
            $request->input('password'),
            $request
        );

        if (!$authenticated) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => trans('auth.failed'),
            ]);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuthService $authService): RedirectResponse
    {
        $authService->logoutDoctor($request);

        return redirect()->route('login')->with('info', 'You have been logged out.');
    }
}
