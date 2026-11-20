<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Patients;

use App\Models\Patient;
use App\Support\PatientRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdatePatientRequest extends FormRequest
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

        $patientId = (string) $this->route('patient_id');
        $patient = Patient::find($patientId);

        return Gate::forUser($doctor)->allows('update', $patient ?? Patient::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return PatientRules::rulesForUpdate();
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('patient_id')) {
                $validator->errors()->add(
                    'patient_id',
                    'The patient_id is immutable and cannot be updated.'
                );
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
            'patient_id.prohibited' => 'The patient_id is immutable and cannot be updated.',
        ];
    }
}
