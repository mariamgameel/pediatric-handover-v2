<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\ClinicalUpdates;

use App\Models\ClinicalUpdate;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\ClinicalUpdateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicalUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected Doctor $doctor;

    protected Doctor $otherDoctor;

    protected Doctor $admin;

    protected string $doctorToken;

    protected string $otherDoctorToken;

    protected string $adminToken;

    protected Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->doctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-00001',
            'is_admin' => false,
            'full_name' => 'Dr. Jane Smith',
        ]);
        $this->doctorToken = $this->doctor->createToken('device-1', ['doctor'])->plainTextToken;

        $this->otherDoctor = Doctor::factory()->create([
            'doctor_id' => 'DOC-00002',
            'is_admin' => false,
            'full_name' => 'Dr. John Doe',
        ]);
        $this->otherDoctorToken = $this->otherDoctor->createToken('device-2', ['doctor'])->plainTextToken;

        $this->admin = Doctor::factory()->create([
            'doctor_id' => 'DOC-00099',
            'is_admin' => true,
            'full_name' => 'Dr. Chief Admin',
        ]);
        $this->adminToken = $this->admin->createToken('admin-device', ['doctor', 'admin'])->plainTextToken;

        $this->patient = Patient::factory()->create([
            'patient_id' => 'PAT-12345',
        ]);
    }

    // ==========================================
    // 1. AUTH & PERMISSIONS
    // ==========================================

    public function test_unauthenticated_requests_return_401_on_all_endpoints(): void
    {
        // 1. POST /patients/{patient_id}/updates
        $resStore = $this->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Sample note',
        ]);
        $resStore->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 2. GET /patients/{patient_id}/updates
        $resIndex = $this->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");
        $resIndex->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);

        // 3. GET /updates/{update_id}
        $resShow = $this->getJson('/api/v1/updates/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $resShow->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    public function test_soft_deleted_doctor_token_returns_401_via_active_middleware(): void
    {
        $this->doctor->delete();

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Sample note',
            ]);

        $res->assertStatus(401)
            ->assertJson(['success' => false, 'error' => ['code' => 'unauthenticated']]);
    }

    // ==========================================
    // 2. CREATE CLINICAL UPDATE
    // ==========================================

    public function test_doctor_can_create_clinical_update_with_exact_resource_structure(): void
    {
        Carbon::setTestNow('2026-11-24 14:30:00');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Patient vitals stable. Temp 37.1C, HR 110, RR 24.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'update' => [
                    'update_id',
                    'patient_id',
                    'content',
                    'created_at',
                    'author' => [
                        'doctor_id',
                        'full_name',
                        'is_deactivated',
                    ],
                    'corrects_update_id',
                ],
            ],
        ]);

        $updateData = $response->json('data.update');
        $this->assertTrue($response->json('success'));
        $this->assertEquals($this->patient->patient_id, $updateData['patient_id']);
        $this->assertEquals('Patient vitals stable. Temp 37.1C, HR 110, RR 24.', $updateData['content']);
        $this->assertEquals(Carbon::now()->toIso8601ZuluString(), $updateData['created_at']);
        $this->assertNull($updateData['corrects_update_id']);

        // Assert author block
        $this->assertEquals([
            'doctor_id' => $this->doctor->doctor_id,
            'full_name' => $this->doctor->full_name,
            'is_deactivated' => false,
        ], $updateData['author']);

        // Verify no sensitive fields in author
        $this->assertArrayNotHasKey('email', $updateData['author']);
        $this->assertArrayNotHasKey('phone', $updateData['author']);
        $this->assertArrayNotHasKey('is_admin', $updateData['author']);
        $this->assertArrayNotHasKey('password', $updateData['author']);

        // Check DB row
        $this->assertDatabaseHas('clinical_updates', [
            'update_id' => $updateData['update_id'],
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Patient vitals stable. Temp 37.1C, HR 110, RR 24.',
        ]);

        Carbon::setTestNow();
    }

    public function test_admin_doctor_can_create_clinical_update_authored_as_themselves(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Admin assessment note.',
            ]);

        $response->assertStatus(201);
        $this->assertEquals($this->admin->doctor_id, $response->json('data.update.author.doctor_id'));
        $this->assertEquals($this->admin->full_name, $response->json('data.update.author.full_name'));
    }

    public function test_create_rejects_client_supplied_created_at_and_all_prohibited_fields(): void
    {
        $prohibited = [
            'doctor_id' => 'DOC-00099',
            'update_id' => '01JM6XYZ000000000000000000',
            'patient_id' => 'PAT-99999',
            'created_at' => '2020-01-01 00:00:00',
            'updated_at' => '2020-01-01 00:00:00',
            'idempotency_key' => 'body-idempotency-key',
        ];

        foreach ($prohibited as $key => $val) {
            $payload = [
                'content' => 'Valid update content',
                $key => $val,
            ];

            $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
                ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", $payload);

            $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'error' => [
                        'code' => 'validation_failed',
                    ],
                ])
                ->assertJsonValidationErrors([$key]);

            $this->assertDatabaseMissing('clinical_updates', [
                'content' => 'Valid update content',
            ]);
        }
    }

    // ==========================================
    // 3. CONTENT VALIDATION
    // ==========================================

    public function test_content_validation_required_non_empty_and_max_5000(): void
    {
        // 1. Missing content
        $resMissing = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", []);
        $resMissing->assertStatus(422)->assertJsonValidationErrors(['content']);

        // 2. Empty string
        $resEmpty = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", ['content' => '']);
        $resEmpty->assertStatus(422)->assertJsonValidationErrors(['content']);

        // 3. Whitespace only
        $resWhitespace = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", ['content' => "   \t\n  "]);
        $resWhitespace->assertStatus(422)->assertJsonValidationErrors(['content']);

        // 4. Over 5000 characters
        $resTooLong = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => str_repeat('A', 5001),
            ]);
        $resTooLong->assertStatus(422)->assertJsonValidationErrors(['content']);

        // 5. Exactly 5000 characters passes
        $resExact = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => str_repeat('B', 5000),
            ]);
        $resExact->assertStatus(201);
    }

    public function test_content_is_trimmed_and_stores_arabic_characters_intact(): void
    {
        $arabicText = 'ملاحظة سريرية: الطفل مستقر ويتناول العلاج بانتظام.';
        $padded = "   \n " . $arabicText . " \t  ";

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => $padded,
            ]);

        $response->assertStatus(201);
        $this->assertEquals($arabicText, $response->json('data.update.content'));

        $this->assertDatabaseHas('clinical_updates', [
            'patient_id' => $this->patient->patient_id,
            'content' => $arabicText,
        ]);
    }

    // ==========================================
    // 4. PATIENT VALIDATION
    // ==========================================

    public function test_create_returns_404_for_unknown_or_malformed_patient_id(): void
    {
        // Unknown patient (valid format)
        $resUnknown = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson('/api/v1/patients/PAT-99999/updates', [
                'content' => 'Clinical note for missing patient',
            ]);
        $resUnknown->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);

        // Malformed patient ID
        $resMalformed = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson('/api/v1/patients/invalid_id!@/updates', [
                'content' => 'Clinical note for malformed ID',
            ]);
        $resMalformed->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    // ==========================================
    // 5. CORRECTIONS
    // ==========================================

    public function test_doctor_can_correct_own_update(): void
    {
        // 1. Create original update
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Original note with typo.',
            ]);
        $res1->assertStatus(201);
        $originalId = $res1->json('data.update.update_id');
        $this->assertNotNull($originalId);

        // 2. Post correction
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Corrected note: typo fixed.',
                'corrects_update_id' => $originalId,
            ]);
        $res2->assertStatus(201);
        $correctionId = $res2->json('data.update.update_id');
        $this->assertEquals($originalId, $res2->json('data.update.corrects_update_id'));

        // 3. Original update in DB is completely unchanged
        $this->assertDatabaseHas('clinical_updates', [
            'update_id' => $originalId,
            'content' => 'Original note with typo.',
            'corrects_update_id' => null,
        ]);

        // 4. Show endpoint on original includes the correction
        $resShow = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/updates/{$originalId}");
        $resShow->assertStatus(200);
        $corrections = $resShow->json('data.update.corrections');
        $this->assertCount(1, $corrections);
        $this->assertEquals($correctionId, $corrections[0]['update_id']);

        // 5. List endpoint reflects corrections_count: 1 on original
        $resList = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");
        $resList->assertStatus(200);
        $items = collect($resList->json('data'));
        $originalInList = $items->firstWhere('update_id', $originalId);
        $this->assertEquals(1, $originalInList['corrections_count']);
    }

    public function test_admin_can_correct_another_doctors_update(): void
    {
        // Doctor creates original update
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Doctor original observation.',
            ]);
        $res1->assertStatus(201);
        $originalId = $res1->json('data.update.update_id');

        $this->app['auth']->forgetGuards();

        // Admin corrects it
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Admin correction to observation.',
                'corrects_update_id' => $originalId,
            ]);

        $res2->assertStatus(201);
        $this->assertEquals($originalId, $res2->json('data.update.corrects_update_id'));
        $this->assertEquals($this->admin->doctor_id, $res2->json('data.update.author.doctor_id'));
    }

    public function test_doctor_cannot_correct_another_doctors_update(): void
    {
        // Doctor 1 creates original update
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Doctor 1 original note.',
            ]);
        $res1->assertStatus(201);
        $originalId = $res1->json('data.update.update_id');

        $this->app['auth']->forgetGuards();

        // Doctor 2 attempts to correct it -> 403 Forbidden
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->otherDoctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Doctor 2 unauthorized correction.',
                'corrects_update_id' => $originalId,
            ]);

        $res2->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'forbidden',
                ],
            ]);
    }

    public function test_correction_validations(): void
    {
        // Create an original update
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'First note.',
            ]);
        $originalId = $res1->json('data.update.update_id');

        // 1. Nonexistent original update ULID
        $resNonExistent = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Correction to nonexistent',
                'corrects_update_id' => '01JM6B7SZ00000000000000000',
            ]);
        $resNonExistent->assertStatus(422)->assertJsonValidationErrors(['corrects_update_id']);

        // 2. Malformed ULID format
        $resMalformed = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Correction to bad ULID',
                'corrects_update_id' => 'not-a-valid-ulid',
            ]);
        $resMalformed->assertStatus(422)->assertJsonValidationErrors(['corrects_update_id']);

        // 3. Original belongs to a different patient
        $patient2 = Patient::factory()->create(['patient_id' => 'PAT-88888']);
        $resDiffPatient = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$patient2->patient_id}/updates", [
                'content' => 'Correction for different patient',
                'corrects_update_id' => $originalId,
            ]);
        $resDiffPatient->assertStatus(422)->assertJsonValidationErrors(['corrects_update_id']);

        // 4. Original is already a correction (cannot chain corrections)
        $resCorrection = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Valid correction.',
                'corrects_update_id' => $originalId,
            ]);
        $resCorrection->assertStatus(201);
        $correctionId = $resCorrection->json('data.update.update_id');

        $resChained = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Attempt to correct a correction.',
                'corrects_update_id' => $correctionId,
            ]);
        $resChained->assertStatus(422)->assertJsonValidationErrors(['corrects_update_id']);
    }

    // ==========================================
    // 6. IDEMPOTENCY
    // ==========================================

    public function test_idempotent_request_with_same_payload_returns_200_and_replays(): void
    {
        $idemKey = 'key-valid-12345678';
        $payload = ['content' => 'Initial assessment for idempotency.'];

        // 1st request -> 201 Created
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", $payload);

        $res1->assertStatus(201);
        $res1->assertHeaderMissing('Idempotent-Replayed');
        $updateId = $res1->json('data.update.update_id');

        // 2nd request with same key and payload -> 200 OK + Idempotent-Replayed: true
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", $payload);

        $res2->assertStatus(200);
        $res2->assertHeader('Idempotent-Replayed', 'true');
        $this->assertEquals($updateId, $res2->json('data.update.update_id'));

        // DB has only 1 row
        $this->assertEquals(1, ClinicalUpdate::where('idempotency_key', $idemKey)->count());
    }

    public function test_idempotent_request_with_different_payload_returns_409_conflict(): void
    {
        $idemKey = 'key-conflict-12345678';

        // 1st request
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Payload version 1',
        ]);
        $res1->assertStatus(201);

        // 2nd request with same key but different payload -> 409
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Payload version 2 (different content)',
        ]);

        $res2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'idempotency_key_conflict',
                ],
            ]);
    }

    public function test_idempotency_key_is_scoped_per_doctor(): void
    {
        $idemKey = 'shared-key-12345678';

        // Doctor 1 uses key
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Doctor 1 content',
        ]);
        $res1->assertStatus(201);

        $this->app['auth']->forgetGuards();

        // Doctor 2 uses same key
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->otherDoctorToken,
            'Idempotency-Key' => $idemKey,
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Doctor 2 content',
        ]);
        $res2->assertStatus(201);

        // Two distinct rows created in DB
        $this->assertEquals(2, ClinicalUpdate::where('idempotency_key', $idemKey)->count());
    }

    public function test_invalid_idempotency_key_format_returns_422(): void
    {
        // 1. Shorter than 8 chars
        $resShort = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => 'short',
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Test short key',
        ]);
        $resShort->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // 2. Contains invalid symbols
        $resInvalidChars = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => 'key_with_@_symbols!',
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Test invalid chars',
        ]);
        $resInvalidChars->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // 3. Longer than 64 chars
        $resTooLong = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->doctorToken,
            'Idempotency-Key' => str_repeat('A', 65),
        ])->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
            'content' => 'Test too long key',
        ]);
        $resTooLong->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_unique_index_race_condition_replays_existing_record_without_500(): void
    {
        $service = app(ClinicalUpdateService::class);
        $idemKey = 'race-condition-key-12345';
        $payload = [
            'content' => 'First inserted content',
        ];

        // First creation succeeds
        $res1 = $service->create($this->doctor, $this->patient->patient_id, $payload, $idemKey);
        $this->assertFalse($res1['replayed']);

        // Second creation with same payload replayed cleanly
        $res2 = $service->create($this->doctor, $this->patient->patient_id, $payload, $idemKey);
        $this->assertTrue($res2['replayed']);
        $this->assertEquals($res1['update']->update_id, $res2['update']->update_id);
    }

    // ==========================================
    // 7. LIST CLINICAL UPDATES
    // ==========================================

    public function test_list_returns_updates_newest_first_and_defaults_to_20(): void
    {
        // Create 25 clinical updates with staggered timestamps
        for ($i = 1; $i <= 25; $i++) {
            ClinicalUpdate::factory()->create([
                'patient_id' => $this->patient->patient_id,
                'doctor_id' => $this->doctor->doctor_id,
                'content' => "Update #{$i}",
                'created_at' => Carbon::parse('2026-11-20 10:00:00')->addMinutes($i),
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(20, $data);

        // Verify newest first: update 25 is first
        $this->assertEquals('Update #25', $data[0]['content']);
        $this->assertEquals('Update #6', $data[19]['content']);

        // Check meta
        $this->assertNotNull($response->json('meta.next_cursor'));
        $this->assertEquals(20, $response->json('meta.per_page'));
    }

    public function test_list_validates_query_parameters(): void
    {
        // per_page = 0 -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=0")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // per_page = 51 -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=51")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // invalid since -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?since=invalid-date")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['since']);

        // invalid doctor_id -> 422
        $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?doctor_id=invalid*doc")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['doctor_id']);
    }

    public function test_list_since_filter_is_strictly_greater_than(): void
    {
        $t1 = Carbon::parse('2026-11-20 10:00:00');
        $t2 = Carbon::parse('2026-11-20 11:00:00');
        $t3 = Carbon::parse('2026-11-20 12:00:00');

        ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Update 1 at T1',
            'created_at' => $t1,
        ]);
        ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Update 2 at T2',
            'created_at' => $t2,
        ]);
        ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Update 3 at T3',
            'created_at' => $t3,
        ]);

        // Filter since=T1 -> should return T2 and T3, NOT T1
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?since=" . urlencode($t1->toIso8601String()));

        $response->assertStatus(200);
        $contents = collect($response->json('data'))->pluck('content')->all();

        $this->assertContains('Update 3 at T3', $contents);
        $this->assertContains('Update 2 at T2', $contents);
        $this->assertNotContains('Update 1 at T1', $contents);
    }

    public function test_list_doctor_id_filter_works(): void
    {
        ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Note by Doctor 1',
        ]);
        ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->otherDoctor->doctor_id,
            'content' => 'Note by Doctor 2',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?doctor_id={$this->otherDoctor->doctor_id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Note by Doctor 2', $data[0]['content']);
        $this->assertEquals($this->otherDoctor->doctor_id, $data[0]['author']['doctor_id']);
    }

    public function test_cursor_pagination_with_identical_timestamps_has_no_duplicates_or_skips(): void
    {
        $fixedTime = Carbon::parse('2026-11-20 12:00:00');
        $createdIds = [];

        for ($i = 0; $i < 25; $i++) {
            $u = ClinicalUpdate::factory()->create([
                'patient_id' => $this->patient->patient_id,
                'doctor_id' => $this->doctor->doctor_id,
                'content' => "Identical timestamp note {$i}",
                'created_at' => $fixedTime,
            ]);
            $createdIds[] = $u->update_id;
        }

        // Fetch page 1 (per_page = 10)
        $resPage1 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=10");
        $resPage1->assertStatus(200);
        $page1Items = $resPage1->json('data');
        $this->assertCount(10, $page1Items);
        $cursor1 = $resPage1->json('meta.next_cursor');
        $this->assertNotNull($cursor1);

        // Fetch page 2 (per_page = 10)
        $resPage2 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=10&cursor=" . urlencode($cursor1));
        $resPage2->assertStatus(200);
        $page2Items = $resPage2->json('data');
        $this->assertCount(10, $page2Items);
        $cursor2 = $resPage2->json('meta.next_cursor');
        $this->assertNotNull($cursor2);

        // Fetch page 3 (per_page = 10)
        $resPage3 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=10&cursor=" . urlencode($cursor2));
        $resPage3->assertStatus(200);
        $page3Items = $resPage3->json('data');
        $this->assertCount(5, $page3Items);
        $cursor3 = $resPage3->json('meta.next_cursor');
        $this->assertNull($cursor3);

        // Verify total retrieved items across all 3 pages
        $allRetrievedIds = array_merge(
            array_column($page1Items, 'update_id'),
            array_column($page2Items, 'update_id'),
            array_column($page3Items, 'update_id')
        );

        $this->assertCount(25, $allRetrievedIds);
        $this->assertCount(25, array_unique($allRetrievedIds), 'Found duplicate IDs across paginated requests.');
        $this->assertEqualsCanonicalizing($createdIds, $allRetrievedIds);
    }

    public function test_list_shows_deactivated_doctor_with_is_deactivated_true(): void
    {
        $update = ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->otherDoctor->doctor_id,
            'content' => 'Note from doctor who later departs.',
        ]);

        // Soft-delete the author
        $this->otherDoctor->delete();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");

        $response->assertStatus(200);
        $item = collect($response->json('data'))->firstWhere('update_id', $update->update_id);
        $this->assertNotNull($item);
        $this->assertEquals($this->otherDoctor->doctor_id, $item['author']['doctor_id']);
        $this->assertEquals($this->otherDoctor->full_name, $item['author']['full_name']);
        $this->assertTrue($item['author']['is_deactivated']);
    }

    public function test_list_prevents_n_plus_one_queries(): void
    {
        // Seed 20 updates by different doctors, some with corrections
        $doc1 = Doctor::factory()->create();
        $doc2 = Doctor::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $u = ClinicalUpdate::factory()->create([
                'patient_id' => $this->patient->patient_id,
                'doctor_id' => ($i % 2 === 0) ? $doc1->doctor_id : $doc2->doctor_id,
            ]);
            if ($i % 5 === 0) {
                ClinicalUpdate::factory()->create([
                    'patient_id' => $this->patient->patient_id,
                    'doctor_id' => $doc1->doctor_id,
                    'corrects_update_id' => $u->update_id,
                ]);
            }
        }

        // Measure query count using actingAs to isolate application query count
        $this->actingAs($this->doctor, 'sanctum');

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=5");
        $queryCountFor5 = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->getJson("/api/v1/patients/{$this->patient->patient_id}/updates?per_page=20");
        $queryCountFor20 = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Query count must remain constant regardless of item count (no N+1)
        $this->assertEquals($queryCountFor5, $queryCountFor20);
    }

    // ==========================================
    // 8. SHOW CLINICAL UPDATE
    // ==========================================

    public function test_show_returns_200_with_corrections_list(): void
    {
        $original = ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Initial medical note',
        ]);

        $correction = ClinicalUpdate::factory()->create([
            'patient_id' => $this->patient->patient_id,
            'doctor_id' => $this->doctor->doctor_id,
            'content' => 'Correction to note',
            'corrects_update_id' => $original->update_id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/updates/{$original->update_id}");

        $response->assertStatus(200);
        $data = $response->json('data.update');

        $this->assertEquals($original->update_id, $data['update_id']);
        $this->assertArrayHasKey('corrections', $data);
        $this->assertCount(1, $data['corrections']);
        $this->assertEquals($correction->update_id, $data['corrections'][0]['update_id']);
        $this->assertEquals($this->doctor->doctor_id, $data['corrections'][0]['author']['doctor_id']);
        $this->assertEquals($this->doctor->full_name, $data['corrections'][0]['author']['full_name']);
    }

    public function test_show_returns_404_for_unknown_or_malformed_update_id(): void
    {
        // Unknown valid Crockford Base32 ULID (26 chars)
        $resUnknown = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/updates/01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $resUnknown->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);

        // Malformed ULID
        $resMalformed = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson('/api/v1/updates/invalid_id');
        $resMalformed->assertStatus(404)->assertJson(['success' => false, 'error' => ['code' => 'not_found']]);
    }

    // ==========================================
    // 9. RATE LIMITING
    // ==========================================

    public function test_create_endpoint_throttled_at_30_per_minute(): void
    {
        // 30 successful creates
        for ($i = 0; $i < 30; $i++) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
                ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                    'content' => "Rate test note {$i}",
                ]);
            $res->assertStatus(201);
        }

        // 31st request -> 429
        $res31 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->postJson("/api/v1/patients/{$this->patient->patient_id}/updates", [
                'content' => 'Rate test note 31',
            ]);

        $res31->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'too_many_requests',
                ],
            ]);
    }

    public function test_read_endpoint_throttled_at_120_per_minute(): void
    {
        // 120 successful reads
        for ($i = 0; $i < 120; $i++) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
                ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");
            $res->assertStatus(200);
        }

        // 121st request -> 429
        $res121 = $this->withHeader('Authorization', 'Bearer ' . $this->doctorToken)
            ->getJson("/api/v1/patients/{$this->patient->patient_id}/updates");

        $res121->assertStatus(429)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'too_many_requests',
                ],
            ]);
    }
}
