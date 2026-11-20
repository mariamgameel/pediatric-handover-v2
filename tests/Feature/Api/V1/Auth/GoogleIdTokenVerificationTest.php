<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Doctor;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

class GoogleIdTokenVerificationTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKey;

    private array $jwks;

    private string $keyId = 'test-key-id';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('google_openid_jwks');

        // Generate RSA key pair for testing
        $configArgs = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $possiblePaths = [
            'C:/Program Files/Git/usr/ssl/openssl.cnf',
            'C:/Program Files/Git/mingw64/etc/ssl/openssl.cnf',
        ];
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $configArgs['config'] = $path;
                break;
            }
        }

        $keyResource = openssl_pkey_new($configArgs);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $keyResource);

        $privKey = '';
        openssl_pkey_export($keyResource, $privKey, null, $configArgs);
        $this->privateKey = $privKey;
        $details = openssl_pkey_get_details($keyResource);

        $modulus = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $exponent = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        $this->jwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $this->keyId,
                    'n' => $modulus,
                    'e' => $exponent,
                ],
            ],
        ];

        config([
            'services.google.client_ids' => ['expected-client-id-1.apps.googleusercontent.com'],
            'services.google.client_id' => 'expected-client-id-1.apps.googleusercontent.com',
        ]);
    }

    private function createToken(array $customClaims = []): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'expected-client-id-1.apps.googleusercontent.com',
            'sub' => 'google-sub-unique-12345',
            'email' => 'verified.doc@hospital.local',
            'email_verified' => true,
            'name' => 'Dr. Verified Google',
            'exp' => time() + 3600,
            'iat' => time(),
        ], $customClaims);

        return JWT::encode($payload, $this->privateKey, 'RS256', $this->keyId);
    }

    public function test_empty_client_ids_configuration_rejects_token(): void
    {
        config([
            'services.google.client_ids' => [],
            'services.google.client_id' => '',
        ]);

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $token = $this->createToken();

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'invalid_token',
            ],
        ]);
    }

    public function test_wrong_audience_rejects_token_and_does_not_leak_audience(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $wrongAud = 'malicious-client-id.apps.googleusercontent.com';
        $token = $this->createToken(['aud' => $wrongAud]);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid Google ID token.',
            'error' => [
                'code' => 'invalid_token',
            ],
        ]);
        $response->assertDontSee($wrongAud);
    }

    public function test_correct_audience_accepts_token(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        Doctor::factory()->create([
            'doctor_id' => 'DOC-GOOGLE-01',
            'google_id' => 'google-sub-unique-12345',
            'email' => 'verified.doc@hospital.local',
        ]);

        $token = $this->createToken();

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Logged in successfully with Google.',
        ]);
    }

    public function test_expired_token_is_rejected(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $token = $this->createToken(['exp' => time() - 60]);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'invalid_token',
            ],
        ]);
    }

    public function test_empty_sub_is_rejected(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $token = $this->createToken(['sub' => '']);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'invalid_token',
            ],
        ]);
    }

    public function test_jwks_fetch_failure_returns_503_service_unavailable(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response('Server Error', 500),
        ]);

        $token = $this->createToken();

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(503);
        $response->assertJson([
            'success' => false,
            'error' => [
                'code' => 'service_unavailable',
            ],
        ]);
    }

    public function test_response_bodies_contain_no_internal_details_on_invalid_token(): void
    {
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response($this->jwks, 200),
        ]);

        $bogusIss = 'https://fake-issuer.com';
        $token = $this->createToken(['iss' => $bogusIss]);

        $response = $this->postJson('/api/v1/auth/google', ['id_token' => $token]);

        $response->assertStatus(401);
        $response->assertDontSee($bogusIss);
        $response->assertDontSee('issuer');
    }
}
