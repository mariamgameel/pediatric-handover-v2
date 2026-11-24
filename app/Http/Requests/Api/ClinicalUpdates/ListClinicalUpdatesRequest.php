<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\ClinicalUpdates;

use App\Models\ClinicalUpdate;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;
use Throwable;

class ListClinicalUpdatesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $doctor = $this->user();

        return $doctor !== null && Gate::forUser($doctor)->allows('viewAny', ClinicalUpdate::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $doctorIdMaxLength = (int) config('hospital.doctor_id_max_length', 64);
        $doctorIdPattern = (string) config('hospital.doctor_id_pattern', config('hospital.patient_id_pattern', '/^[A-Za-z0-9\-_]+$/'));

        return [
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['sometimes', 'nullable', 'string'],
            'since' => ['sometimes', 'nullable', 'date'],
            'doctor_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:' . $doctorIdMaxLength,
                'regex:' . $doctorIdPattern,
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('since')) {
                try {
                    Carbon::parse((string) $this->query('since'));
                } catch (Throwable) {
                    $validator->errors()->add(
                        'since',
                        'The since field must be a valid ISO 8601 datetime.'
                    );
                }
            }
        });
    }
}
