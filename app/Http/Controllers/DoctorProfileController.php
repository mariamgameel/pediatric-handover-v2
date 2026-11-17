<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Doctor;
use App\Services\ProfileService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class DoctorProfileController extends Controller
{
    /**
     * Show the authenticated doctor's profile.
     */
    public function show(): View
    {
        /** @var Doctor $doctor */
        $doctor = auth('doctor')->user();

        return view('doctor.profile.show', [
            'doctor_id' => $doctor->doctor_id,
            'full_name' => $doctor->full_name,
            'email' => $doctor->email,
            'phone_number' => $doctor->phone_number,
            'last_login_at' => $doctor->last_login_at,
            'is_google_linked' => !empty($doctor->google_id),
        ]);
    }

    /**
     * Show the edit form for the authenticated doctor.
     */
    public function edit(): View
    {
        /** @var Doctor $doctor */
        $doctor = auth('doctor')->user();

        return view('doctor.profile.edit', [
            'doctor_id' => $doctor->doctor_id,
            'full_name' => $doctor->full_name,
            'email' => $doctor->email,
            'phone_number' => $doctor->phone_number,
        ]);
    }

    /**
     * Update the authenticated doctor's profile.
     */
    public function update(UpdateProfileRequest $request, ProfileService $profileService): RedirectResponse
    {
        /** @var Doctor $doctor */
        $doctor = auth('doctor')->user();

        $profileService->updateProfile($doctor, $request->only([
            'full_name',
            'email',
            'phone_number',
        ]));

        return redirect()->route('profile.show')->with('success', 'Profile updated successfully.');
    }
}
