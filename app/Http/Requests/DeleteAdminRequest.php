<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeleteAdminRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $admin = auth('admin')->user();
        if ($admin === null) {
            return false;
        }

        $adminId = $this->route('admin_id');
        $targetAdmin = Admin::withTrashed()->find($adminId);

        return Gate::forUser($admin)->allows('delete', $targetAdmin);
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
            'confirm_delete.required' => 'You must check the confirmation checkbox to delete the administrator.',
            'confirm_delete.accepted' => 'You must check the confirmation checkbox to delete the administrator.',
        ];
    }
}
