<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthService
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function getRedirect(): RedirectResponse
    {
        /** @var RedirectResponse */
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google OAuth.
     */
    public function handleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->with('error', 'Unable to authenticate with Google. Please try again.');
        }

        $googleId = (string) $googleUser->getId();
        $email = (string) $googleUser->getEmail();

        if ($email === '') {
            return redirect()->route('login')->with('error', 'Google account must provide a verified email address.');
        }

        // 1. Try finding by google_id (including soft-deleted)
        $doctor = Doctor::withTrashed()->where('google_id', $googleId)->first();

        // 2. Otherwise find by email and link google_id to the existing row
        if ($doctor === null) {
            $doctor = Doctor::withTrashed()->where('email', $email)->first();

            if ($doctor !== null && !$doctor->trashed()) {
                $doctor->update(['google_id' => $googleId]);
            }
        }

        // 3. If doctor does not exist, reject and redirect to /register with message
        if ($doctor === null) {
            return redirect()->route('register')->with(
                'error',
                'Hospital Doctor ID is required for registration. Please register with your hospital ID first.'
            );
        }

        // 4. If soft-deleted, reject login
        if ($doctor->trashed()) {
            return redirect()->route('login')->with('error', 'Your doctor account is inactive.');
        }

        // 5. Update last_login_at, log in using doctor guard, regenerate session
        $doctor->update(['last_login_at' => now()]);
        auth('doctor')->login($doctor);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Logged in successfully with Google.');
    }
}
