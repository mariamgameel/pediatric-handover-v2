<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_login_is_rate_limited_after_5_attempts(): void
    {
        $payload = [
            'email' => 'ratelimit.doctor@hospital.local',
            'password' => 'WrongPassword!',
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', $payload);
            $response->assertStatus(302);
        }

        // 6th attempt should be blocked by throttle
        $response = $this->post('/login', $payload);
        $response->assertStatus(429);
    }

    public function test_admin_login_is_rate_limited_after_5_attempts(): void
    {
        $payload = [
            'email' => 'ratelimit.admin@hospital.local',
            'password' => 'WrongPassword!',
        ];

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/admin/login', $payload);
            $response->assertStatus(302);
        }

        // 6th attempt should be blocked by throttle
        $response = $this->post('/admin/login', $payload);
        $response->assertStatus(429);
    }

    public function test_doctor_registration_is_rate_limited_after_5_attempts(): void
    {
        $payload = [
            'doctor_id' => 'DOC-LIMIT',
            'full_name' => 'Dr. Test Limit',
            'email' => 'limit.register@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $payload);
        }

        // 6th attempt with same email
        $response = $this->post('/register', $payload);
        $response->assertStatus(429);
    }

    public function test_google_redirect_is_rate_limited_after_5_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->get('/auth/google/redirect');
        }

        $response = $this->get('/auth/google/redirect');
        $response->assertStatus(429);
    }

    public function test_google_callback_is_rate_limited_after_5_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->get('/auth/google/callback');
        }

        $response = $this->get('/auth/google/callback');
        $response->assertStatus(429);
    }
}
