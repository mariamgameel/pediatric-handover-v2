<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
        return auth('doctor')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Doctor $doctor */
        $doctor = auth('doctor')->user();

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Check all rows including soft-deleted, ignoring current doctor's own record
                Rule::unique('doctors', 'email')->ignore($doctor->doctor_id, 'doctor_id'),
            ],
            'phone_number' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[\d\s\+\-\(\)]*$/',
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
                if ($this->has($field)) {
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
