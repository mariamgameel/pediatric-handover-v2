<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_403_renders_custom_view(): void
    {
        Route::get('/_test/403', fn () => abort(403, 'Unauthorized test action'));

        $response = $this->get('/_test/403');

        $response->assertStatus(403);
        $response->assertSee('403 Forbidden');
        $response->assertSee('Unauthorized test action');
    }

    public function test_404_renders_custom_view(): void
    {
        $response = $this->get('/_test/non-existent-route-for-testing-404');

        $response->assertStatus(404);
        $response->assertSee('404 Not Found');
    }

    public function test_419_renders_custom_view(): void
    {
        Route::get('/_test/419', fn () => abort(419));

        $response = $this->get('/_test/419');

        $response->assertStatus(419);
        $response->assertSee('419 Page Expired');
    }

    public function test_500_renders_custom_view_without_stack_trace_leak(): void
    {
        config(['app.debug' => false]);

        Route::get('/_test/500', function () {
            throw new \RuntimeException('Sensitive database secret or internal error details');
        });

        $response = $this->get('/_test/500');

        $response->assertStatus(500);
        $response->assertSee('500 Server Error');
        $response->assertDontSee('Sensitive database secret or internal error details');
        $response->assertDontSee('Stack trace');
    }
}
