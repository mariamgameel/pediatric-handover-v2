<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hospital.api_docs_enabled' => true]);
    }

    public function test_api_documentation_ui_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('Pediatric Handover System');
    }

    public function test_api_documentation_is_forbidden_when_disabled_in_config(): void
    {
        config(['hospital.api_docs_enabled' => false]);

        $responseUi = $this->get('/docs/api');
        $responseUi->assertStatus(403);

        $responseJson = $this->get('/docs/api.json');
        $responseJson->assertStatus(403);
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
        $this->assertArrayHasKey('/profile', $data['paths']);
        $this->assertArrayHasKey('/patients', $data['paths']);
        $this->assertArrayHasKey('/patients/{patient_id}', $data['paths']);
        $this->assertArrayHasKey('/patients/{patient_id}/updates', $data['paths']);
        $this->assertArrayHasKey('/updates/{update_id}', $data['paths']);
        $this->assertArrayHasKey('/admin/patients', $data['paths']);
        $this->assertArrayHasKey('/admin/patients/{patient_id}', $data['paths']);
        $this->assertArrayHasKey('/admin/doctors', $data['paths']);
        $this->assertArrayHasKey('/admin/doctors/{doctor_id}', $data['paths']);
        $this->assertArrayHasKey('/admin/doctors/{doctor_id}/restore', $data['paths']);

        $profilePatch = $data['paths']['/profile']['patch'];
        $this->assertArrayHasKey('responses', $profilePatch);
        $this->assertArrayHasKey('200', $profilePatch['responses']);
        $this->assertArrayHasKey('403', $profilePatch['responses']);
        $this->assertArrayHasKey('422', $profilePatch['responses']);
        $this->assertArrayHasKey('429', $profilePatch['responses']);

        // Verify bearer authentication is defined in components
        $this->assertArrayHasKey('components', $data);
        $this->assertArrayHasKey('securitySchemes', $data['components']);
    }
}
