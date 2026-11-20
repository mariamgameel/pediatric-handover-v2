<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ClinicalUpdateRouteImmutabilityTest extends TestCase
{
    /**
     * Ensure that clinical updates remain strictly immutable and append-only across the system.
     * No registered route may permit PUT, PATCH, or DELETE operations on clinical updates.
     */
    public function test_no_registered_route_permits_editing_or_deleting_clinical_updates(): void
    {
        $routes = Route::getRoutes()->getRoutes();
        $this->assertNotEmpty($routes, 'At least one route should be registered in the application.');

        foreach ($routes as $route) {
            $uri = strtolower($route->uri());
            $actionName = strtolower($route->getActionName());
            $methods = $route->methods();

            $isClinicalUpdateRoute = str_contains($uri, 'clinical-update')
                || str_contains($uri, 'clinical_update')
                || str_contains($uri, 'clinicalupdate')
                || str_contains($actionName, 'clinicalupdate');

            if ($isClinicalUpdateRoute) {
                $this->assertNotContains('PUT', $methods, "Forbidden route mutation: PUT method registered on clinical update route '{$route->uri()}'.");
                $this->assertNotContains('PATCH', $methods, "Forbidden route mutation: PATCH method registered on clinical update route '{$route->uri()}'.");
                $this->assertNotContains('DELETE', $methods, "Forbidden route mutation: DELETE method registered on clinical update route '{$route->uri()}'.");
            }
        }
    }
}
