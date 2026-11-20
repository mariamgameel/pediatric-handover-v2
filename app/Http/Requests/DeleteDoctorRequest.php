<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Doctor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteDoctorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $doctor = $this->user();
        if ($doctor === null) {
            return false;
        }

        $doctorId = (string) $this->route('doctor_id');
        $targetDoctor = Doctor::withTrashed()->find($doctorId);

        return Gate::forUser($doctor)->allows('delete', $targetDoctor);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_delete' => ['required', 'accepted'],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_delete.required' => 'You must check the confirmation checkbox to delete the doctor.',
            'confirm_delete.accepted' => 'You must check the confirmation checkbox to delete the doctor.',
        ];
    }
}
