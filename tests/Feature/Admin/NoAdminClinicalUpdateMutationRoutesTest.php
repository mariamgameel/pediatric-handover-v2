<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NoAdminClinicalUpdateMutationRoutesTest extends TestCase
{
    public function test_no_admin_route_mutates_clinical_updates(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())->filter(function ($route): bool {
            $uri = $route->uri();
            $name = $route->getName() ?? '';
            return str_starts_with($uri, 'admin') || str_starts_with($name, 'admin.');
        });

        // 1. Assert no route updates or deletes clinical updates
        foreach ($adminRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();

            if (str_contains($uri, 'clinical-update') || str_contains($uri, 'clinical_update')) {
                $this->assertNotContains('POST', $methods, "Admin should not have POST route for clinical updates: {$uri}");
                $this->assertNotContains('PUT', $methods, "Admin should not have PUT route for clinical updates: {$uri}");
                $this->assertNotContains('PATCH', $methods, "Admin should not have PATCH route for clinical updates: {$uri}");
                $this->assertNotContains('DELETE', $methods, "Admin should not have DELETE route for clinical updates: {$uri}");
            }
        }

        // 2. Assert no doctor edit or update routes exist for admin
        $hasDoctorEdit = $adminRoutes->contains(fn ($route) => $route->getName() === 'admin.doctors.edit');
        $hasDoctorUpdate = $adminRoutes->contains(fn ($route) => $route->getName() === 'admin.doctors.update');

        $this->assertFalse($hasDoctorEdit, 'Admins must not have a doctor edit form.');
        $this->assertFalse($hasDoctorUpdate, 'Admins must not have a doctor update route.');
    }
}
