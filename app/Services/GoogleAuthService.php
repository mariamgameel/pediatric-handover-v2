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

        // 1. Refuse if Google returns no email or empty identifier
        if ($email === '' || $googleId === '') {
            return redirect()->route('login')->with('error', 'Unable to authenticate with Google. Missing email address.');
        }

        // 2. Lookup by google_id
        $doctor = Doctor::withTrashed()->where('google_id', $googleId)->first();

        if ($doctor !== null && $doctor->trashed()) {
            return redirect()->route('login')->with('error', 'Your doctor account is inactive.');
        }

        // 3. Otherwise find by email and link google_id to the existing row
        if ($doctor === null) {
            $rawUser = is_array($googleUser->user ?? null) ? $googleUser->user : [];
            $emailVerified = filter_var(
                $rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            if (!$emailVerified) {
                return redirect()->route('login')->with('error', 'Unable to authenticate with Google. Email address is not verified.');
            }

            $doctor = Doctor::withTrashed()->where('email', $email)->first();

            if ($doctor !== null) {
                // If the doctor found by email is soft-deleted, refuse and do not link
                if ($doctor->trashed()) {
                    return redirect()->route('login')->with('error', 'Your doctor account is inactive.');
                }

                // NEVER overwrite an existing link: if doctor already linked to a different google_id, refuse
                if ($doctor->google_id !== null && $doctor->google_id !== $googleId) {
                    return redirect()->route('login')->with('error', 'Unable to authenticate with Google. This account is already linked to a different Google account.');
                }

                $doctor->update(['google_id' => $googleId]);
            }
        }

        // 4. If doctor does not exist, reject and redirect to /register with message
        if ($doctor === null) {
            return redirect()->route('register')->with(
                'error',
                'Hospital Doctor ID is required for registration. Please register with your hospital ID first.'
            );
        }

        // 5. Update last_login_at, log in using doctor guard, regenerate session
        $doctor->update(['last_login_at' => now()]);
        auth('doctor')->login($doctor);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Logged in successfully with Google.');
    }
}
