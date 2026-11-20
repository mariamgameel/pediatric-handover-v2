<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_json_401_without_redirect_even_without_accept_header(): void
    {
        // Notice we use ->get() without ->getJson() or Accept header
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401);
        $response->assertHeader('content-type', 'application/json');
        $response->assertJson([
            'success' => false,
            'message' => 'Unauthenticated.',
            'error' => [
                'code' => 'unauthenticated',
            ],
        ]);
    }

    public function test_nonexistent_api_endpoint_returns_json_404(): void
    {
        $response = $this->get('/api/v1/nonexistent-route');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Resource not found.',
            'error' => [
                'code' => 'not_found',
            ],
        ]);
    }

    public function test_method_not_allowed_on_api_returns_json_405(): void
    {
        // GET on a POST-only route
        $response = $this->get('/api/v1/auth/login');

        $response->assertStatus(405);
        $response->assertJson([
            'success' => false,
            'message' => 'Method not allowed.',
            'error' => [
                'code' => 'method_not_allowed',
            ],
        ]);
    }

    public function test_validation_errors_return_json_422_with_code_and_field_details(): void
    {
        $response = $this->post('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation failed.',
            'error' => [
                'code' => 'validation_failed',
            ],
        ]);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_500_response_masks_exception_message_when_debug_is_false(): void
    {
        config(['app.debug' => false]);

        \Illuminate\Support\Facades\Route::get('/_test/error-500', function () {
            throw new \RuntimeException('Database credentials secret leak');
        });

        $response = $this->getJson('/_test/error-500');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Internal server error.',
            'error' => [
                'code' => 'server_error',
            ],
        ]);
        $response->assertDontSee('Database credentials secret leak');
    }

    public function test_500_response_exposes_exception_message_when_debug_is_true(): void
    {
        config(['app.debug' => true]);

        \Illuminate\Support\Facades\Route::get('/_test/error-500-debug', function () {
            throw new \RuntimeException('Helpful development error details');
        });

        $response = $this->getJson('/_test/error-500-debug');

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'Helpful development error details',
            'error' => [
                'code' => 'server_error',
            ],
        ]);
    }
}
