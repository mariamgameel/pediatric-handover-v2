<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doctor_id' => 'DOC-' . fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'google_id' => null,
            'phone_number' => fake()->phoneNumber(),
            'profile_image' => null,
            'last_login_at' => null,
        ];
    }

    /**
     * Indicate that the doctor authenticated via Google only.
     */
    public function googleOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
            'google_id' => fake()->unique()->numerify('google_###########'),
        ]);
    }

    /**
     * Indicate that the doctor is an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }
}
