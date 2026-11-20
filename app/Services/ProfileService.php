<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\EmailChangeNotAllowedException;
use App\Models\Doctor;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileService
{
    /**
     * Update doctor profile attributes.
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(Doctor $doctor, array $data): Doctor
    {
        $updates = [];

        if (array_key_exists('full_name', $data)) {
            $updates['full_name'] = trim((string) $data['full_name']);
        }

        if (array_key_exists('phone_number', $data)) {
            $phone = $data['phone_number'];
            $updates['phone_number'] = ($phone !== null && trim((string) $phone) !== '')
                ? trim((string) $phone)
                : null;
        }

        if (array_key_exists('email', $data)) {
            $newEmail = strtolower(trim((string) $data['email']));
            $currentEmail = strtolower((string) $doctor->email);

            if ($newEmail !== $currentEmail) {
                $this->handleEmailChange(
                    $doctor,
                    $newEmail,
                    isset($data['current_password']) ? (string) $data['current_password'] : null
                );
                $updates['email'] = $newEmail;
            }
        }

        if (!empty($updates)) {
            $doctor->update($updates);
        }

        return $doctor;
    }

    /**
     * Handle business rules for doctor email change.
     */
    private function handleEmailChange(Doctor $doctor, string $newEmail, ?string $currentPassword): void
    {
        // 1. If doctor has NO password (Google-only account)
        if (empty($doctor->password)) {
            throw new EmailChangeNotAllowedException('Email cannot be changed for accounts that sign in with Google.');
        }

        // 2. Doctor has a password: verify current_password
        if ($currentPassword === null || !Hash::check($currentPassword, $doctor->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password does not match our records.'],
            ]);
        }

        // 3. Delete all of the doctor's OTHER Sanctum tokens (keep the token used for this request)
        $currentTokenId = $doctor->currentAccessToken()?->id;
        if ($currentTokenId !== null) {
            $doctor->tokens()->where('id', '!=', $currentTokenId)->delete();
        }
    }
}
