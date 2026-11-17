<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Patient;
use App\Support\PatientRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $admin = auth('admin')->user();

        return $admin !== null && Gate::forUser($admin)->allows('create', Patient::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return PatientRules::rulesForCreate();
    }
}
