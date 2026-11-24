<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\ClinicalUpdates;

use App\Models\ClinicalUpdate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreClinicalUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $doctor = $this->user();

        return $doctor !== null && Gate::forUser($doctor)->allows('create', ClinicalUpdate::class);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('content') && is_string($this->input('content'))) {
            $this->merge([
                'content' => trim($this->input('content')),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
            'corrects_update_id' => ['sometimes', 'nullable', 'string', 'ulid'],
            'doctor_id' => ['prohibited'],
            'update_id' => ['prohibited'],
            'patient_id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
            'idempotency_key' => ['prohibited'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $idempotencyKey = $this->header('Idempotency-Key');
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $idempotencyKey)) {
                    $validator->errors()->add(
                        'idempotency_key',
                        'The idempotency key must be between 8 and 64 alphanumeric characters, underscores, or dashes.'
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
            'content.required' => 'The content field is required.',
            'content.max' => 'The content field must not exceed 5000 characters.',
            'doctor_id.prohibited' => 'The doctor_id field is prohibited.',
            'update_id.prohibited' => 'The update_id field is prohibited.',
            'patient_id.prohibited' => 'The patient_id field is prohibited.',
            'created_at.prohibited' => 'The created_at field is prohibited.',
            'updated_at.prohibited' => 'The updated_at field is prohibited.',
            'idempotency_key.prohibited' => 'The idempotency_key field is prohibited in the request body.',
        ];
    }
}
