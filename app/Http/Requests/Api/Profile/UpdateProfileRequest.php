<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Profile;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Doctor|null $doctor */
        $doctor = $this->user();
        $doctorId = $doctor?->doctor_id;

        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('doctors', 'email')->ignore($doctorId, 'doctor_id'),
            ],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                'regex:/^[\d\s\+\-\(\)]*$/',
            ],
            'current_password' => [
                Rule::requiredIf(function () use ($doctor): bool {
                    if (!$this->has('email') || empty($doctor?->password)) {
                        return false;
                    }

                    $incomingEmail = strtolower(trim((string) $this->input('email')));
                    $currentEmail = strtolower((string) $doctor->email);

                    return $incomingEmail !== $currentEmail;
                }),
                'string',
            ],
            'doctor_id' => ['prohibited'],
            'google_id' => ['prohibited'],
            'password' => ['prohibited'],
            'last_login_at' => ['prohibited'],
            'remember_token' => ['prohibited'],
            'profile_image' => ['prohibited'],
            'deleted_at' => ['prohibited'],
            'is_admin' => ['prohibited'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasField = $this->exists('full_name')
                || $this->exists('email')
                || $this->exists('phone_number');

            if (!$hasField) {
                $validator->errors()->add(
                    'payload',
                    'At least one field (full_name, email, or phone_number) must be provided.'
                );
            }

            $disallowedFields = [
                'doctor_id',
                'google_id',
                'password',
                'last_login_at',
                'remember_token',
                'profile_image',
                'deleted_at',
                'is_admin',
            ];

            foreach ($disallowedFields as $field) {
                if ($this->exists($field)) {
                    $validator->errors()->add(
                        $field,
                        "The {$field} field is immutable and cannot be submitted."
                    );
                }
            }
        });
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doctor_id.prohibited' => 'The doctor_id field is immutable and cannot be submitted.',
            'google_id.prohibited' => 'The google_id field cannot be submitted.',
            'password.prohibited' => 'The password field cannot be submitted here.',
            'phone_number.regex' => 'The phone number format is invalid.',
        ];
    }
}
