<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ClinicalUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClinicalUpdate
 */
class ClinicalUpdateResource extends JsonResource
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
        $author = $this->doctor;

        $data = [
            'update_id' => $this->update_id,
            'patient_id' => $this->patient_id,
            'content' => $this->content,
            'author' => [
                'doctor_id' => $author?->doctor_id ?? $this->doctor_id,
                'full_name' => $author?->full_name ?? '',
                'is_deactivated' => $author !== null ? $author->trashed() : true,
            ],
            'corrects_update_id' => $this->corrects_update_id,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];

        if (isset($this->corrections_count)) {
            $data['corrections_count'] = (int) $this->corrections_count;
        }

        if ($this->relationLoaded('corrections')) {
            $data['corrections'] = $this->corrections->map(function (ClinicalUpdate $correction): array {
                $correctionAuthor = $correction->doctor;

                return [
                    'update_id' => $correction->update_id,
                    'created_at' => $correction->created_at?->toIso8601ZuluString(),
                    'author' => [
                        'doctor_id' => $correctionAuthor?->doctor_id ?? $correction->doctor_id,
                        'full_name' => $correctionAuthor?->full_name ?? '',
                    ],
                ];
            })->values()->all();
        }

        return $data;
    }
}
