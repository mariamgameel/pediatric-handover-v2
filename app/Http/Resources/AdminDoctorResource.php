<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Doctor
 */
class AdminDoctorResource extends JsonResource
{
    /**
     * Disable data wrapping inside the resource itself
     * as our outer envelope handles the wrapping.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->doctor_id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'is_admin' => $this->isAdmin(),
            'status' => $this->trashed() ? 'deactivated' : 'active',
            'has_password' => $this->password !== null,
            'has_google' => $this->google_id !== null,
            'last_login_at' => $this->last_login_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
            'deleted_at' => $this->deleted_at?->toIso8601ZuluString(),
            'clinical_updates_count' => (int) ($this->clinical_updates_count ?? 0),
        ];
    }
}
