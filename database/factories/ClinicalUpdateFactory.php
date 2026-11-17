<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalUpdate>
 */
class ClinicalUpdateFactory extends Factory
{
    protected $model = ClinicalUpdate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'content' => fake()->paragraph(),
            'corrects_update_id' => null,
        ];
    }

    /**
     * Indicate that this update is a correction of an existing clinical update.
     */
    public function correction(ClinicalUpdate $original): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_id' => $original->patient_id,
            'corrects_update_id' => $original->update_id,
            'content' => 'Correction: ' . fake()->paragraph(),
        ]);
    }
}
