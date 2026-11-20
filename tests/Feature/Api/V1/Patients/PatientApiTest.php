<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Patients;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    protected Doctor $doctor;

    protected Doctor $admin;

    protected string $doctorToken;

    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = Doctor::factory()->create(['is_admin' => false]);
        $this->doctorToken = $this->doctor->createToken('doctor-device', ['doctor'])->plainTextToken;

        $this->admin = Doctor::factory()->create(['is_admin' => true]);
        $this->adminToken = $this->admin->createToken('admin-device', ['doctor', 'admin'])->plainTextToken;
    }

    // ==========================================
    // AUTH & PERMISSIONS TESTS
    // ==========================================

    public function test_unauthenticated_requests_return_401_on_every_endpoint(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-AUTH-01']);

        // 1. GET /patients
        $resIndex = $this->getJson('/api/v1/patients');
        $resIndex->assertStatus(401)->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 2. GET /patients/{id}
        $resShow = $this->getJson('/api/v1/patients/PAT-AUTH-01');
        $resShow->assertStatus(401)->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 3. POST /admin/patients
        $resStore = $this->postJson('/api/v1/admin/patients', [
            'patient_id' => 'PAT-AUTH-02',
            'full_name' => 'Unauth Test',
            'date_of_birth' => '2020-01-01',
            'gender' => 'male',
        ]);
        $resStore->assertStatus(401)->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 4. PUT /admin/patients/{id}
        $resUpdate = $this->putJson('/api/v1/admin/patients/PAT-AUTH-01', [
            'full_name' => 'Unauth Updated',
            'date_of_birth' => '2020-01-01',
            'gender' => 'female',
        ]);
        $resUpdate->assertStatus(401)->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 5. DELETE /admin/patients/{id}
        $resDestroy = $this->deleteJson('/api/v1/admin/patients/PAT-AUTH-01');
        $resDestroy->assertStatus(401)->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    public function test_soft_deleted_doctor_token_returns_401(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-SOFT-01']);
        $this->doctor->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients');

        $response->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    public function test_non_admin_doctor_returns_403_on_all_admin_endpoints_and_data_is_unchanged(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-FORBID-01',
            'full_name' => 'Original Name',
            'date_of_birth' => '2022-05-10',
            'gender' => 'male',
        ]);

        // 1. POST /admin/patients
        $resStore = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-NEW-FAIL',
                'full_name' => 'Forbidden Child',
                'date_of_birth' => '2023-01-01',
                'gender' => 'female',
            ]);
        $resStore->assertStatus(403)->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-NEW-FAIL']);

        // 2. PUT /admin/patients/{id}
        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->putJson('/api/v1/admin/patients/PAT-FORBID-01', [
                'full_name' => 'Attempted Hack',
                'date_of_birth' => '2022-05-10',
                'gender' => 'male',
            ]);
        $resUpdate->assertStatus(403)->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
        $patient->refresh();
        $this->assertSame('Original Name', $patient->full_name);

        // 3. DELETE /admin/patients/{id}
        $resDestroy = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->deleteJson('/api/v1/admin/patients/PAT-FORBID-01');
        $resDestroy->assertStatus(403)->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-FORBID-01']);
    }

    public function test_admin_can_access_both_doctor_and_admin_endpoints(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-ADM-01']);

        // Doctor read endpoints accessed by admin
        $resIndex = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/patients');
        $resIndex->assertStatus(200);

        $resShow = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/patients/PAT-ADM-01');
        $resShow->assertStatus(200);

        // Admin endpoints accessed by admin
        $resStore = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-ADM-02',
                'full_name' => 'Admin Created Patient',
                'date_of_birth' => '2021-08-20',
                'gender' => 'female',
            ]);
        $resStore->assertStatus(201);

        $resUpdate = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/admin/patients/PAT-ADM-02', [
                'full_name' => 'Admin Updated Patient',
                'date_of_birth' => '2021-08-20',
                'gender' => 'female',
            ]);
        $resUpdate->assertStatus(200);

        $resDestroy = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/patients/PAT-ADM-02');
        $resDestroy->assertStatus(200);
    }

    // ==========================================
    // LIST & SEARCH TESTS
    // ==========================================

    public function test_list_pagination_meta_and_per_page_validation(): void
    {
        Patient::factory()->count(25)->create();

        // Default per_page is 20
        $resDefault = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients');

        $resDefault->assertStatus(200);
        $resDefault->assertJsonStructure([
            'success',
            'message',
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
            ],
        ]);
        $this->assertSame(20, $resDefault->json('meta.per_page'));
        $this->assertSame(25, $resDefault->json('meta.total'));
        $this->assertSame(1, $resDefault->json('meta.current_page'));
        $this->assertSame(2, $resDefault->json('meta.last_page'));
        $this->assertCount(20, $resDefault->json('data'));

        // per_page 51 returns 422
        $res51 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?per_page=51');
        $res51->assertStatus(422)
            ->assertJson(['success' => false, 'error' => ['code' => 'validation_failed']])
            ->assertJsonValidationErrors('per_page');

        // per_page 0 returns 422
        $res0 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?per_page=0');
        $res0->assertStatus(422)
            ->assertJson(['success' => false, 'error' => ['code' => 'validation_failed']])
            ->assertJsonValidationErrors('per_page');
    }

    public function test_list_searches_by_id_and_english_and_arabic_and_literal_wildcards(): void
    {
        Patient::factory()->create([
            'patient_id' => 'PAT-SEARCH-ID-99',
            'full_name' => 'Christopher Robin',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-ARABIC-01',
            'full_name' => 'يوسف محمد عبد الرحمن',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-LITERAL-01',
            'full_name' => 'Baby%1_Test',
        ]);
        Patient::factory()->create([
            'patient_id' => 'PAT-LITERAL-02',
            'full_name' => 'Baby1XTest',
        ]);

        // Search by patient_id
        $resId = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?search=SEARCH-ID-99');
        $resId->assertStatus(200);
        $this->assertSame(1, $resId->json('meta.total'));
        $this->assertSame('PAT-SEARCH-ID-99', $resId->json('data.0.patient_id'));

        // Search by English name
        $resName = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?search=Christopher');
        $resName->assertStatus(200);
        $this->assertSame(1, $resName->json('meta.total'));
        $this->assertSame('Christopher Robin', $resName->json('data.0.full_name'));

        // Search by Arabic name
        $resArabic = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?search=' . urlencode('يوسف'));
        $resArabic->assertStatus(200);
        $this->assertSame(1, $resArabic->json('meta.total'));
        $this->assertSame('يوسف محمد عبد الرحمن', $resArabic->json('data.0.full_name'));

        // Literal % and _ matching: 'Baby%1_Test' should match only Baby%1_Test and NOT Baby1XTest
        $resWildcard = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?search=' . urlencode('%1_'));
        $resWildcard->assertStatus(200);
        $this->assertSame(1, $resWildcard->json('meta.total'));
        $this->assertSame('Baby%1_Test', $resWildcard->json('data.0.full_name'));
    }

    public function test_list_filters_by_gender_and_direction(): void
    {
        Patient::factory()->create(['full_name' => 'Aaron Boy', 'gender' => 'male']);
        Patient::factory()->create(['full_name' => 'Zoe Girl', 'gender' => 'female']);

        // Invalid gender -> 422
        $resInvalidGender = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?gender=unknown_gender');
        $resInvalidGender->assertStatus(422)
            ->assertJsonValidationErrors('gender');

        // Valid gender male
        $resMale = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?gender=male');
        $resMale->assertStatus(200);
        $this->assertSame(1, $resMale->json('meta.total'));
        $this->assertSame('Aaron Boy', $resMale->json('data.0.full_name'));

        // Invalid direction -> 422
        $resInvalidDir = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?direction=sideways');
        $resInvalidDir->assertStatus(422)
            ->assertJsonValidationErrors('direction');

        // Sorting asc
        $resAsc = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?direction=asc');
        $resAsc->assertStatus(200);
        $this->assertSame('Aaron Boy', $resAsc->json('data.0.full_name'));

        // Sorting desc
        $resDesc = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?direction=desc');
        $resDesc->assertStatus(200);
        $this->assertSame('Zoe Girl', $resDesc->json('data.0.full_name'));
    }

    public function test_duplicate_full_name_across_multiple_pages_is_deterministic_without_duplicates_or_skips(): void
    {
        Patient::factory()->create(['patient_id' => 'PAT-01', 'full_name' => 'Twin Child']);
        Patient::factory()->create(['patient_id' => 'PAT-02', 'full_name' => 'Twin Child']);
        Patient::factory()->create(['patient_id' => 'PAT-03', 'full_name' => 'Twin Child']);
        Patient::factory()->create(['patient_id' => 'PAT-04', 'full_name' => 'Twin Child']);

        $page1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?per_page=2&page=1');
        $page1->assertStatus(200);
        $page1Ids = collect($page1->json('data'))->pluck('patient_id')->all();

        $page2 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients?per_page=2&page=2');
        $page2->assertStatus(200);
        $page2Ids = collect($page2->json('data'))->pluck('patient_id')->all();

        $combined = array_merge($page1Ids, $page2Ids);
        $this->assertSame(['PAT-01', 'PAT-02', 'PAT-03', 'PAT-04'], $combined);
        $this->assertCount(4, array_unique($combined));
    }

    // ==========================================
    // SHOW TESTS
    // ==========================================

    public function test_show_returns_200_with_exact_patient_resource_key_set(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-EXACT-KEYS',
            'full_name' => 'Baby Alex',
            'date_of_birth' => '2023-04-12',
            'gender' => 'male',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients/PAT-EXACT-KEYS');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => null,
            'data' => [
                'patient' => [
                    'patient_id' => 'PAT-EXACT-KEYS',
                    'full_name' => 'Baby Alex',
                    'date_of_birth' => '2023-04-12',
                    'gender' => 'male',
                ],
            ],
        ]);

        $patientData = $response->json('data.patient');
        $this->assertSame(
            ['patient_id', 'full_name', 'date_of_birth', 'gender', 'created_at', 'updated_at'],
            array_keys($patientData)
        );
    }

    public function test_show_with_unknown_id_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients/PAT-NON-EXISTENT');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'not_found',
                ],
            ]);
    }

    public function test_show_with_malformed_id_returns_404(): void
    {
        // Spaces and special characters outside [A-Za-z0-9\-_]+
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients/BAD%20PATIENT%20ID!');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'not_found',
                ],
            ]);
    }

    // ==========================================
    // STORE (CREATE) TESTS
    // ==========================================

    public function test_store_creates_patient_successfully(): void
    {
        $payload = [
            'patient_id' => 'PAT-NEW-VALID-01',
            'full_name' => 'Baby Charlie',
            'date_of_birth' => '2022-11-15',
            'gender' => 'male',
            'created_at' => '2020-01-01T00:00:00Z', // Extra field, should be ignored
            'unsupported_field' => 'ignore_me',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Patient created successfully.',
            'data' => [
                'patient' => [
                    'patient_id' => 'PAT-NEW-VALID-01',
                    'full_name' => 'Baby Charlie',
                    'date_of_birth' => '2022-11-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $this->assertDatabaseHas('patients', [
            'patient_id' => 'PAT-NEW-VALID-01',
            'full_name' => 'Baby Charlie',
            'gender' => 'male',
        ]);
    }

    public function test_store_validates_date_of_birth_boundaries(): void
    {
        // Future date fails
        $futureDate = now('Africa/Cairo')->addDays(5)->toDateString();
        $resFuture = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-FUTURE',
                'full_name' => 'Future Child',
                'date_of_birth' => $futureDate,
                'gender' => 'male',
            ]);
        $resFuture->assertStatus(422)->assertJsonValidationErrors('date_of_birth');

        // Date before 1900-01-01 fails
        $resOld = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-OLD',
                'full_name' => 'Ancient Child',
                'date_of_birth' => '1899-12-31',
                'gender' => 'male',
            ]);
        $resOld->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
    }

    public function test_store_hospital_timezone_boundary_today_vs_tomorrow(): void
    {
        // In UTC: 2026-11-20 22:30:00.
        // In Africa/Cairo (UTC+2): 2026-11-21 00:30:00.
        // Cairo date is '2026-11-21', while UTC date is '2026-11-20'.
        Carbon::setTestNow(Carbon::parse('2026-11-20 22:30:00', 'UTC'));

        try {
            // '2026-11-21' is Cairo today -> accepted
            $resToday = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
                ->postJson('/api/v1/admin/patients', [
                    'patient_id' => 'PAT-CAIRO-TODAY',
                    'full_name' => 'Cairo Newborn Today',
                    'date_of_birth' => '2026-11-21',
                    'gender' => 'female',
                ]);
            $resToday->assertStatus(201);
            $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-CAIRO-TODAY']);

            // '2026-11-22' is Cairo tomorrow -> rejected with 422
            $resTomorrow = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
                ->postJson('/api/v1/admin/patients', [
                    'patient_id' => 'PAT-CAIRO-TOMORROW',
                    'full_name' => 'Cairo Newborn Tomorrow',
                    'date_of_birth' => '2026-11-22',
                    'gender' => 'female',
                ]);
            $resTomorrow->assertStatus(422)->assertJsonValidationErrors('date_of_birth');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_store_validates_patient_id_pattern_and_duplicates_and_gender(): void
    {
        Patient::factory()->create(['patient_id' => 'PAT-EXISTING-01']);

        // Invalid pattern (spaces or disallowed characters)
        $resPattern = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'INVALID ID WITH SPACES',
                'full_name' => 'Valid Name',
                'date_of_birth' => '2022-01-01',
                'gender' => 'male',
            ]);
        $resPattern->assertStatus(422)->assertJsonValidationErrors('patient_id');

        // Duplicate patient_id
        $resDuplicate = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-EXISTING-01',
                'full_name' => 'Another Child',
                'date_of_birth' => '2022-01-01',
                'gender' => 'female',
            ]);
        $resDuplicate->assertStatus(422)->assertJsonValidationErrors('patient_id');

        // Invalid gender
        $resGender = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-GENDER-BAD',
                'full_name' => 'Gender Test',
                'date_of_birth' => '2022-01-01',
                'gender' => 'non_binary',
            ]);
        $resGender->assertStatus(422)->assertJsonValidationErrors('gender');
    }

    public function test_store_concurrent_duplicate_insert_returns_422_validation_failed(): void
    {
        // Simulate concurrent insert: between FormRequest validation and DB insert,
        // another thread inserts the same patient_id, causing a DB unique constraint violation.
        Patient::creating(function (Patient $p): void {
            if ($p->patient_id === 'PAT-RACE-01') {
                DB::table('patients')->insert([
                    'patient_id' => 'PAT-RACE-01',
                    'full_name' => 'Concurrent Inserted First',
                    'date_of_birth' => '2021-01-01',
                    'gender' => 'female',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-RACE-01',
                'full_name' => 'Concurrent Child',
                'date_of_birth' => '2021-01-01',
                'gender' => 'female',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'validation_failed',
                ],
            ])
            ->assertJsonValidationErrors('patient_id');
    }

    // ==========================================
    // UPDATE TESTS
    // ==========================================

    public function test_update_modifies_patient_successfully(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-UPD-01',
            'full_name' => 'Original Baby',
            'date_of_birth' => '2022-03-10',
            'gender' => 'male',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/admin/patients/PAT-UPD-01', [
                'full_name' => 'Updated Baby Name',
                'date_of_birth' => '2022-03-15',
                'gender' => 'male',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Patient updated successfully.',
            'data' => [
                'patient' => [
                    'patient_id' => 'PAT-UPD-01',
                    'full_name' => 'Updated Baby Name',
                    'date_of_birth' => '2022-03-15',
                    'gender' => 'male',
                ],
            ],
        ]);

        $patient->refresh();
        $this->assertSame('Updated Baby Name', $patient->full_name);
        $this->assertSame('2022-03-15', $patient->date_of_birth->format('Y-m-d'));
    }

    public function test_update_with_patient_id_in_payload_returns_422_and_leaves_id_unchanged(): void
    {
        $patient = Patient::factory()->create([
            'patient_id' => 'PAT-IMMUTABLE-01',
            'full_name' => 'Baby Safe',
            'date_of_birth' => '2022-03-10',
            'gender' => 'female',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/admin/patients/PAT-IMMUTABLE-01', [
                'patient_id' => 'PAT-HACKED-ID',
                'full_name' => 'Updated Baby Safe',
                'date_of_birth' => '2022-03-10',
                'gender' => 'female',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('patient_id');

        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-IMMUTABLE-01']);
        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-HACKED-ID']);
    }

    public function test_update_with_unknown_id_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/admin/patients/PAT-DOES-NOT-EXIST', [
                'full_name' => 'Ghost Patient',
                'date_of_birth' => '2022-01-01',
                'gender' => 'male',
            ]);

        $response->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    // ==========================================
    // DELETE TESTS
    // ==========================================

    public function test_delete_patient_without_updates_succeeds(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-DEL-CLEAN']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/patients/PAT-DEL-CLEAN');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Patient deleted successfully.',
            'data' => null,
        ]);

        $this->assertDatabaseMissing('patients', ['patient_id' => 'PAT-DEL-CLEAN']);
    }

    public function test_delete_patient_with_clinical_updates_returns_409_patient_has_clinical_updates(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-HAS-UPDATES']);
        ClinicalUpdate::factory()->create([
            'patient_id' => 'PAT-HAS-UPDATES',
            'doctor_id' => $this->doctor->doctor_id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/patients/PAT-HAS-UPDATES');

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'patient_has_clinical_updates',
            ],
        ]);

        // Patient still exists in database
        $this->assertDatabaseHas('patients', ['patient_id' => 'PAT-HAS-UPDATES']);
    }

    public function test_delete_with_unknown_id_returns_404(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/patients/PAT-DEL-UNKNOWN');

        $response->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    // ==========================================
    // RATE LIMITING TESTS
    // ==========================================

    public function test_patients_read_rate_limiter_throttles_after_120_requests(): void
    {
        // Hit read endpoint 120 times successfully
        for ($i = 0; $i < 120; $i++) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
                ->getJson('/api/v1/patients');
            $res->assertStatus(200);
        }

        // 121st request should be throttled (429)
        $res121 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/patients');

        $res121->assertStatus(429);
        $res121->assertJson([
            'success' => false,
            'error' => [
                'code' => 'too_many_requests',
            ],
        ]);
    }

    public function test_admin_write_rate_limiter_throttles_after_30_requests(): void
    {
        // Hit admin write endpoint 30 times
        for ($i = 0; $i < 30; $i++) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
                ->postJson('/api/v1/admin/patients', [
                    'patient_id' => 'PAT-RATE-' . $i,
                    'full_name' => 'Rate Test Child ' . $i,
                    'date_of_birth' => '2022-01-01',
                    'gender' => 'male',
                ]);
            $res->assertStatus(201);
        }

        // 31st request should be throttled (429)
        $res31 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/patients', [
                'patient_id' => 'PAT-RATE-31',
                'full_name' => 'Rate Test Child 31',
                'date_of_birth' => '2022-01-01',
                'gender' => 'male',
            ]);

        $res31->assertStatus(429);
        $res31->assertJson([
            'success' => false,
            'error' => [
                'code' => 'too_many_requests',
            ],
        ]);
    }
}
