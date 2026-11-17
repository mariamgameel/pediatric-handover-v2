<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var list<string> $genders */
        $genders = config('hospital.genders', ['male', 'female']);

        return [
            'patient_id' => 'PAT-' . fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'date_of_birth' => fake()->dateTimeBetween('-16 years', 'now')->format('Y-m-d'),
            'gender' => fake()->randomElement($genders),
        ];
    }
}
