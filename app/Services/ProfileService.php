<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Doctor;

class ProfileService
{
    /**
     * Update doctor profile attributes.
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(Doctor $doctor, array $data): Doctor
    {
        // TODO: The behavior of the linked Google account on email change is an open business question (e.g., whether to retain the link, unlink the account, or require re-authentication).
        $doctor->update([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'] ?? null,
        ]);

        return $doctor;
    }
}
