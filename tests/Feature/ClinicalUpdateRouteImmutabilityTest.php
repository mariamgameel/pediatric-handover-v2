<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

    /**
     * Allow-list route guard: exactly three clinical update routes are registered, and none accept PUT/PATCH/DELETE.
     */
    public function test_allow_list_strictly_restricts_clinical_update_routes(): void
    {
        $routes = Route::getRoutes()->getRoutes();
        $matched = [];

        foreach ($routes as $route) {
            $uri = strtolower($route->uri());
            $actionName = strtolower($route->getActionName());

            if (str_contains($uri, 'updates') || str_contains($actionName, 'clinicalupdate')) {
                foreach ($route->methods() as $method) {
                    $matched[] = [
                        'method' => $method,
                        'name' => $route->getName(),
                        'uri' => $route->uri(),
                    ];
                }
            }
        }

        $this->assertNotEmpty($matched);

        // Ensure no mutation methods are allowed
        foreach ($matched as $entry) {
            $this->assertNotContains($entry['method'], ['PUT', 'PATCH', 'DELETE']);
        }

        // Expected allow-list of (method, URI name)
        $expected = [
            ['method' => 'POST', 'name' => 'api.v1.patients.updates.store'],
            ['method' => 'GET', 'name' => 'api.v1.patients.updates.index'],
            ['method' => 'HEAD', 'name' => 'api.v1.patients.updates.index'],
            ['method' => 'GET', 'name' => 'api.v1.updates.show'],
            ['method' => 'HEAD', 'name' => 'api.v1.updates.show'],
        ];

        $matchedMethodNames = array_map(fn ($m) => ['method' => $m['method'], 'name' => $m['name']], $matched);

        $this->assertEqualsCanonicalizing($expected, $matchedMethodNames);
    }

    /**
     * Verify database-level triggers on MySQL prevent raw UPDATE and DELETE.
     * Skipped on non-MySQL drivers (e.g. SQLite in-memory during tests).
     */
    public function test_mysql_database_triggers_prevent_raw_update_and_delete(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Database triggers test only runs on MySQL/MariaDB connections (current driver: ' . $driver . ').');
        }

        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $update = ClinicalUpdate::factory()->create([
            'patient_id' => $patient->patient_id,
            'doctor_id' => $doctor->doctor_id,
            'content' => 'Immutable check',
        ]);

        try {
            DB::statement('UPDATE clinical_updates SET content = ? WHERE update_id = ?', ['mutated', $update->update_id]);
            $this->fail('Expected QueryException was not thrown on raw UPDATE');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Clinical updates are immutable', $e->getMessage());
        }

        try {
            DB::statement('DELETE FROM clinical_updates WHERE update_id = ?', [$update->update_id]);
            $this->fail('Expected QueryException was not thrown on raw DELETE');
        } catch (QueryException $e) {
            $this->assertStringContainsString('Clinical updates are immutable', $e->getMessage());
        }
    }
}
