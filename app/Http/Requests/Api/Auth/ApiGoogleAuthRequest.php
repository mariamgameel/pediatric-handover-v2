<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ApiGoogleAuthRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
            'doctor_id' => ['nullable', 'string', 'max:64'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'is_admin' => ['prohibited'],
        ];
    }
}
