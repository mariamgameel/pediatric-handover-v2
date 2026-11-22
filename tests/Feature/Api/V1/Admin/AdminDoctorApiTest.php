<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDoctorApiTest extends TestCase
{
    use RefreshDatabase;

    protected Doctor $admin;

    protected Doctor $doctor;

    protected string $adminToken;

    protected string $doctorToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Doctor::factory()->create([
            'doctor_id' => 'DOC-ADMIN-ROOT',
            'is_admin' => true,
        ]);
        $this->adminToken = $this->admin->createToken('admin-device', ['doctor', 'admin'])->plainTextToken;

        $this->doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-REGULAR-01',
            'is_admin' => false,
        ]);
        $this->doctorToken = $this->doctor->createToken('doctor-device', ['doctor'])->plainTextToken;
    }

    // ==========================================
    // AUTH & PERMISSIONS
    // ==========================================

    public function test_unauthenticated_requests_return_401_on_all_four_endpoints(): void
    {
        $target = Doctor::factory()->create(['doctor_id' => 'DOC-TARGET-01']);

        // 1. GET /admin/doctors
        $this->getJson('/api/v1/admin/doctors')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 2. GET /admin/doctors/{id}
        $this->getJson('/api/v1/admin/doctors/DOC-TARGET-01')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 3. DELETE /admin/doctors/{id}
        $this->deleteJson('/api/v1/admin/doctors/DOC-TARGET-01')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 4. POST /admin/doctors/{id}/restore
        $this->postJson('/api/v1/admin/doctors/DOC-TARGET-01/restore')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    public function test_soft_deleted_admin_token_returns_401(): void
    {
        $this->admin->delete();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    public function test_regular_non_admin_doctor_returns_403_on_all_four_endpoints(): void
    {
        $target = Doctor::factory()->create(['doctor_id' => 'DOC-TARGET-02']);

        // 1. GET /admin/doctors
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/admin/doctors')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);

        // 2. GET /admin/doctors/{id}
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/admin/doctors/DOC-TARGET-02')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);

        // 3. DELETE /admin/doctors/{id}
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-TARGET-02')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-TARGET-02']);

        // 4. POST /admin/doctors/{id}/restore
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson('/api/v1/admin/doctors/DOC-TARGET-02/restore')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
    }

    public function test_token_with_spoofed_admin_ability_on_non_admin_doctor_returns_403(): void
    {
        $spoofedDoctor = Doctor::factory()->create(['is_admin' => false]);
        $spoofedToken = $spoofedDoctor->createToken('spoofed-token', ['doctor', 'admin'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $spoofedToken)
            ->getJson('/api/v1/admin/doctors')
            ->assertStatus(403)
            ->assertJson(['success' => false, 'error' => ['code' => 'forbidden']]);
    }

    // ==========================================
    // LIST ENDPOINT
    // ==========================================

    public function test_list_status_filters_active_deactivated_and_all(): void
    {
        $activeDoc = Doctor::factory()->create(['doctor_id' => 'DOC-LIST-ACT']);
        $deletedDoc = Doctor::factory()->create(['doctor_id' => 'DOC-LIST-DEL']);
        $deletedDoc->delete();

        // Default: active only (includes admin and activeDoc, excludes deletedDoc)
        $resDefault = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors');
        $resDefault->assertStatus(200);
        $idsDefault = collect($resDefault->json('data'))->pluck('doctor_id')->all();
        $this->assertContains('DOC-LIST-ACT', $idsDefault);
        $this->assertNotContains('DOC-LIST-DEL', $idsDefault);

        // status=deactivated: only deletedDoc
        $resDeactivated = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?status=deactivated');
        $resDeactivated->assertStatus(200);
        $idsDeactivated = collect($resDeactivated->json('data'))->pluck('doctor_id')->all();
        $this->assertContains('DOC-LIST-DEL', $idsDeactivated);
        $this->assertNotContains('DOC-LIST-ACT', $idsDeactivated);
        $this->assertNotContains('DOC-ADMIN-ROOT', $idsDeactivated);

        // status=all: includes both
        $resAll = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?status=all');
        $resAll->assertStatus(200);
        $idsAll = collect($resAll->json('data'))->pluck('doctor_id')->all();
        $this->assertContains('DOC-LIST-ACT', $idsAll);
        $this->assertContains('DOC-LIST-DEL', $idsAll);
    }

    public function test_list_pagination_meta_and_invalid_param_validation(): void
    {
        Doctor::factory()->count(25)->create();

        // Default per_page is 20
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors');
        $res->assertStatus(200);
        $this->assertSame(20, $res->json('meta.per_page'));
        $this->assertSame(1, $res->json('meta.current_page'));

        // per_page 51 -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=51')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        // per_page 0 -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        // invalid status -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?status=invalid_status')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // invalid direction -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?direction=invalid_direction')
            ->assertStatus(422)
            ->assertJsonValidationErrors('direction');
    }

    public function test_list_searches_by_id_name_arabic_email_and_literal_wildcards(): void
    {
        Doctor::factory()->create([
            'doctor_id' => 'DOC-SRCH-ID-99',
            'full_name' => 'Gregory House',
            'email' => 'house@diagnostic.local',
        ]);
        Doctor::factory()->create([
            'doctor_id' => 'DOC-ARABIC-99',
            'full_name' => 'د. حسام الدين',
            'email' => 'hossam@hospital.local',
        ]);
        Doctor::factory()->create([
            'doctor_id' => 'DOC-LITERAL-01',
            'full_name' => 'Doctor%1_Test',
            'email' => 'literal@hospital.local',
        ]);
        Doctor::factory()->create([
            'doctor_id' => 'DOC-LITERAL-02',
            'full_name' => 'Doctor1XTest',
            'email' => 'other@hospital.local',
        ]);

        // Search by doctor_id
        $resId = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=SRCH-ID-99');
        $resId->assertStatus(200);
        $this->assertSame(1, $resId->json('meta.total'));
        $this->assertSame('DOC-SRCH-ID-99', $resId->json('data.0.doctor_id'));

        // Search by English name
        $resName = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=Gregory');
        $resName->assertStatus(200);
        $this->assertSame(1, $resName->json('meta.total'));
        $this->assertSame('Gregory House', $resName->json('data.0.full_name'));

        // Search by Arabic name
        $resArabic = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=' . urlencode('حسام'));
        $resArabic->assertStatus(200);
        $this->assertSame(1, $resArabic->json('meta.total'));
        $this->assertSame('د. حسام الدين', $resArabic->json('data.0.full_name'));

        // Search by email
        $resEmail = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=diagnostic.local');
        $resEmail->assertStatus(200);
        $this->assertSame(1, $resEmail->json('meta.total'));
        $this->assertSame('DOC-SRCH-ID-99', $resEmail->json('data.0.doctor_id'));

        // Search literal wildcards: %1_
        $resWildcard = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=' . urlencode('%1_'));
        $resWildcard->assertStatus(200);
        $this->assertSame(1, $resWildcard->json('meta.total'));
        $this->assertSame('Doctor%1_Test', $resWildcard->json('data.0.full_name'));
    }

    public function test_duplicate_full_name_across_multiple_pages_is_deterministic_without_duplicates_or_skips(): void
    {
        // Clean out others for clean pagination test
        Doctor::where('doctor_id', '!=', $this->admin->doctor_id)->forceDelete();

        Doctor::factory()->create(['doctor_id' => 'DOC-01', 'full_name' => 'Twin Doctor']);
        Doctor::factory()->create(['doctor_id' => 'DOC-02', 'full_name' => 'Twin Doctor']);
        Doctor::factory()->create(['doctor_id' => 'DOC-03', 'full_name' => 'Twin Doctor']);
        Doctor::factory()->create(['doctor_id' => 'DOC-04', 'full_name' => 'Twin Doctor']);

        $page1 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=Twin&per_page=2&page=1');
        $page1->assertStatus(200);
        $page1Ids = collect($page1->json('data'))->pluck('doctor_id')->all();

        $page2 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?search=Twin&per_page=2&page=2');
        $page2->assertStatus(200);
        $page2Ids = collect($page2->json('data'))->pluck('doctor_id')->all();

        $combined = array_merge($page1Ids, $page2Ids);
        $this->assertSame(['DOC-01', 'DOC-02', 'DOC-03', 'DOC-04'], $combined);
        $this->assertCount(4, array_unique($combined));
    }

    public function test_exact_key_set_of_admin_doctor_resource_and_no_sensitive_fields(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-RES-01']);
        $doc = Doctor::factory()->create([
            'doctor_id' => 'DOC-RESOURCE-KEYS',
            'full_name' => 'Dr. Key Test',
            'email' => 'keys@hospital.local',
            'phone_number' => '+201122334455',
            'password' => Hash::make('Secret123!'),
            'google_id' => 'google-sub-keys',
            'is_admin' => false,
        ]);

        ClinicalUpdate::factory()->count(3)->create([
            'doctor_id' => $doc->doctor_id,
            'patient_id' => $patient->patient_id,
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors/DOC-RESOURCE-KEYS');

        $res->assertStatus(200);
        $doctorData = $res->json('data.doctor');

        $expectedKeys = [
            'doctor_id',
            'full_name',
            'email',
            'phone_number',
            'is_admin',
            'status',
            'has_password',
            'has_google',
            'last_login_at',
            'created_at',
            'updated_at',
            'deleted_at',
            'clinical_updates_count',
        ];

        $this->assertSame($expectedKeys, array_keys($doctorData));
        $this->assertSame(3, $doctorData['clinical_updates_count']);
        $this->assertTrue($doctorData['has_password']);
        $this->assertTrue($doctorData['has_google']);
        $this->assertSame('active', $doctorData['status']);
        $this->assertFalse($doctorData['is_admin']);

        // Verify no sensitive fields
        $this->assertArrayNotHasKey('password', $doctorData);
        $this->assertArrayNotHasKey('google_id', $doctorData);
        $this->assertArrayNotHasKey('remember_token', $doctorData);
        $this->assertArrayNotHasKey('tokens', $doctorData);
    }

    public function test_list_query_count_does_not_grow_with_number_of_doctors(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-N1-01']);

        // Baseline: 5 doctors with updates
        for ($i = 1; $i <= 5; $i++) {
            $doc = Doctor::factory()->create(['doctor_id' => 'DOC-N1-A-' . $i]);
            ClinicalUpdate::factory()->create([
                'doctor_id' => $doc->doctor_id,
                'patient_id' => $patient->patient_id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=5');

        // Warm up authentication so token resolution is not counted unevenly
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=1');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=5');

        $queries5 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Add 10 more doctors with updates (total 15)
        for ($i = 6; $i <= 15; $i++) {
            $doc = Doctor::factory()->create(['doctor_id' => 'DOC-N1-B-' . $i]);
            ClinicalUpdate::factory()->create([
                'doctor_id' => $doc->doctor_id,
                'patient_id' => $patient->patient_id,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors?per_page=15');

        $queries15 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Exactly 2 queries (1 count + 1 select with eager count subquery), no N+1
        $this->assertSame(2, $queries5);
        $this->assertSame(2, $queries15);
    }

    // ==========================================
    // SHOW ENDPOINT
    // ==========================================

    public function test_show_resolves_active_and_deactivated_doctors_and_returns_correct_status(): void
    {
        $activeDoc = Doctor::factory()->create(['doctor_id' => 'DOC-SHOW-ACT']);
        $deletedDoc = Doctor::factory()->create(['doctor_id' => 'DOC-SHOW-DEL']);
        $deletedDoc->delete();

        // Active doctor -> 200, status=active
        $resAct = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors/DOC-SHOW-ACT');
        $resAct->assertStatus(200);
        $this->assertSame('active', $resAct->json('data.doctor.status'));
        $this->assertNull($resAct->json('data.doctor.deleted_at'));

        // Deactivated doctor -> 200, status=deactivated
        $resDel = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors/DOC-SHOW-DEL');
        $resDel->assertStatus(200);
        $this->assertSame('deactivated', $resDel->json('data.doctor.status'));
        $this->assertNotNull($resDel->json('data.doctor.deleted_at'));

        // Unknown ID -> 404
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors/DOC-NOT-FOUND')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);

        // Malformed ID -> 404
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors/BAD%20DOCTOR%20ID!')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    // ==========================================
    // DEACTIVATE (DELETE) ENDPOINT
    // ==========================================

    public function test_deactivate_doctor_succeeds_revokes_tokens_and_preserves_clinical_updates(): void
    {
        $patient = Patient::factory()->create(['patient_id' => 'PAT-DEACT-01']);
        $targetDoc = Doctor::factory()->create([
            'doctor_id' => 'DOC-TO-DEACTIVATE',
            'email' => 'target.deact@hospital.local',
            'password' => Hash::make('Password123!'),
        ]);

        $token1 = $targetDoc->createToken('device-1')->plainTextToken;
        $token2 = $targetDoc->createToken('device-2')->plainTextToken;

        $update = ClinicalUpdate::factory()->create([
            'doctor_id' => $targetDoc->doctor_id,
            'patient_id' => $patient->patient_id,
            'content' => 'Patient stable on observation.',
        ]);

        $this->assertSame(2, $targetDoc->tokens()->count());

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-TO-DEACTIVATE');

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'message' => 'Doctor deactivated successfully.',
            'data' => null,
        ]);

        // Doctor is soft-deleted
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-TO-DEACTIVATE']);

        // Tokens revoked
        $this->assertSame(0, $targetDoc->tokens()->count());

        // Token authentication fails
        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        // Login fails for deactivated doctor
        $this->postJson('/api/v1/auth/login', [
            'email' => 'target.deact@hospital.local',
            'password' => 'Password123!',
        ])->assertStatus(401);

        // Clinical updates remain intact
        $this->assertDatabaseHas('clinical_updates', ['update_id' => $update->update_id]);
    }

    public function test_deactivate_self_returns_403_cannot_deactivate_self(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/' . $this->admin->doctor_id);

        $res->assertStatus(403);
        $res->assertJson([
            'success' => false,
            'error' => [
                'code' => 'cannot_deactivate_self',
            ],
        ]);

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => $this->admin->doctor_id]);
    }

    public function test_deactivate_already_deactivated_doctor_returns_409(): void
    {
        $doc = Doctor::factory()->create(['doctor_id' => 'DOC-ALREADY-DEL']);
        $doc->delete();

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-ALREADY-DEL');

        $res->assertStatus(409);
        $res->assertJson([
            'success' => false,
            'error' => [
                'code' => 'doctor_already_deactivated',
            ],
        ]);
    }

    public function test_deactivate_unknown_doctor_returns_404(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-NON-EXISTENT')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    public function test_another_admin_can_be_deactivated_by_an_admin(): void
    {
        $secondAdmin = Doctor::factory()->create([
            'doctor_id' => 'DOC-ADMIN-TWO',
            'is_admin' => true,
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-ADMIN-TWO');

        $res->assertStatus(200);
        $this->assertSoftDeleted('doctors', ['doctor_id' => 'DOC-ADMIN-TWO']);
        $this->assertNotSoftDeleted('doctors', ['doctor_id' => $this->admin->doctor_id]);
    }

    // ==========================================
    // RESTORE ENDPOINT
    // ==========================================

    public function test_restore_deactivated_doctor_succeeds_and_allows_login_with_password(): void
    {
        $doc = Doctor::factory()->create([
            'doctor_id' => 'DOC-TO-RESTORE',
            'email' => 'restore.doc@hospital.local',
            'password' => Hash::make('RestoredPass123!'),
        ]);

        $oldToken = $doc->createToken('old-device')->plainTextToken;
        $doc->delete();

        // Old token is revoked
        $this->assertSame(0, $doc->tokens()->count());

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/doctors/DOC-TO-RESTORE/restore');

        $res->assertStatus(200);
        $res->assertJson([
            'success' => true,
            'message' => 'Doctor restored successfully.',
            'data' => [
                'doctor' => [
                    'doctor_id' => 'DOC-TO-RESTORE',
                    'status' => 'active',
                    'deleted_at' => null,
                ],
            ],
        ]);

        $this->assertNotSoftDeleted('doctors', ['doctor_id' => 'DOC-TO-RESTORE']);

        // Old token still returns 401
        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        // Doctor can log in and obtain a new valid token
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'email' => 'restore.doc@hospital.local',
            'password' => 'RestoredPass123!',
        ]);

        $loginRes->assertStatus(200);
        $newToken = $loginRes->json('data.token');
        $this->assertNotEmpty($newToken);

        // New token works
        auth()->forgetGuards();
        $this->withHeader('Authorization', 'Bearer ' . $newToken)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200);
    }

    public function test_restore_not_deactivated_doctor_returns_409(): void
    {
        $activeDoc = Doctor::factory()->create(['doctor_id' => 'DOC-ALREADY-ACT']);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/doctors/DOC-ALREADY-ACT/restore');

        $res->assertStatus(409);
        $res->assertJson([
            'success' => false,
            'error' => [
                'code' => 'doctor_not_deactivated',
            ],
        ]);
    }

    public function test_restore_unknown_doctor_returns_404(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/admin/doctors/DOC-NON-EXISTENT/restore')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    public function test_deactivated_doctor_id_and_email_stay_reserved_and_cannot_be_registered(): void
    {
        $doc = Doctor::factory()->create([
            'doctor_id' => 'DOC-RESERVED-01',
            'email' => 'reserved@hospital.local',
        ]);
        $doc->delete();

        // Attempting to register the same doctor_id fails
        $resId = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-RESERVED-01',
            'full_name' => 'New Doctor Attempt',
            'email' => 'other.email@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $resId->assertStatus(422)->assertJsonValidationErrors('doctor_id');

        // Attempting to register the same email fails
        $resEmail = $this->postJson('/api/v1/auth/register', [
            'doctor_id' => 'DOC-NEW-UNIQUE-99',
            'full_name' => 'New Doctor Attempt',
            'email' => 'reserved@hospital.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $resEmail->assertStatus(422)->assertJsonValidationErrors('email');
    }

    // ==========================================
    // RATE LIMITING
    // ==========================================

    public function test_admin_read_rate_limiter_throttles_after_120_requests(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
                ->getJson('/api/v1/admin/doctors')
                ->assertStatus(200);
        }

        // 121st request -> 429
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/admin/doctors')
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'too_many_requests',
                ],
            ]);
    }

    public function test_admin_write_rate_limiter_throttles_after_30_requests(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $doc = Doctor::factory()->create(['doctor_id' => 'DOC-RATE-WR-' . $i]);
            $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
                ->deleteJson('/api/v1/admin/doctors/DOC-RATE-WR-' . $i)
                ->assertStatus(200);
        }

        // 31st request -> 429
        $doc31 = Doctor::factory()->create(['doctor_id' => 'DOC-RATE-WR-31']);
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/admin/doctors/DOC-RATE-WR-31')
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'too_many_requests',
                ],
            ]);
    }
}
