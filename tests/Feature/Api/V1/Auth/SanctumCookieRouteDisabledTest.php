<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumCookieRouteDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_csrf_cookie_route_is_disabled_and_returns_404_json(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Resource not found.',
            'error' => [
                'code' => 'not_found',
            ],
        ]);
    }
}
