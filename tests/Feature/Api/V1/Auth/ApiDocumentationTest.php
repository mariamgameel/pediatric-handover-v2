<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_documentation_ui_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('Pediatric Handover System');
    }

    public function test_openapi_json_is_accessible_and_valid(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/json');

        $data = $response->json();
        $this->assertArrayHasKey('openapi', $data);
        $this->assertArrayHasKey('info', $data);
        $this->assertSame('1.0.0', $data['info']['version']);
        $this->assertArrayHasKey('paths', $data);

        // Verify documented paths
        $this->assertArrayHasKey('/auth/register', $data['paths']);
        $this->assertArrayHasKey('/auth/login', $data['paths']);
        $this->assertArrayHasKey('/auth/google', $data['paths']);
        $this->assertArrayHasKey('/auth/logout', $data['paths']);
        $this->assertArrayHasKey('/auth/logout-all', $data['paths']);
        $this->assertArrayHasKey('/auth/me', $data['paths']);

        // Verify bearer authentication is defined in components
        $this->assertArrayHasKey('components', $data);
        $this->assertArrayHasKey('securitySchemes', $data['components']);
    }
}
