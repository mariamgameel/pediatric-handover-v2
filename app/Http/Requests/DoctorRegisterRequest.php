<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class DoctorRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $doctorIdLength = (int) config('hospital.doctor_id_max_length', 64);

        return [
            'doctor_id' => [
                'required',
                'string',
                'max:' . $doctorIdLength,
                // Checks entire table including soft-deleted records
                Rule::unique(Doctor::class, 'doctor_id'),
            ],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // Checks entire table including soft-deleted records
                Rule::unique(Doctor::class, 'email'),
            ],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'is_admin' => ['prohibited'],
        ];
    }
}
