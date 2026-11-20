<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\GoogleIdTokenVerifier;
use App\Exceptions\GoogleServiceUnavailableException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class JwtGoogleIdTokenVerifier implements GoogleIdTokenVerifier
{
    private const GOOGLE_JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const VALID_ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    /**
     * Verify the Google ID token and return verified payload.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     *
     * @throws InvalidArgumentException
     * @throws GoogleServiceUnavailableException
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        $jwks = $this->getGoogleJwks();

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($idToken, $keys);
        } catch (Throwable $e) {
            Log::warning('Google ID token verification failed: invalid signature or format.', [
                'error' => $e->getMessage(),
            ]);
            throw new InvalidArgumentException('Invalid Google ID token.', 0, $e);
        }

        // Verify issuer
        $iss = (string) ($decoded->iss ?? '');
        if (!in_array($iss, self::VALID_ISSUERS, true)) {
            Log::warning('Google ID token verification failed: invalid issuer.', [
                'issuer' => $iss,
            ]);
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        // Verify audience
        $allowedClientIds = $this->getAllowedClientIds();
        if (empty($allowedClientIds)) {
            Log::warning('Google ID token verification failed: no allowed Google client IDs configured.');
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        $aud = (string) ($decoded->aud ?? '');
        if ($aud === '' || !in_array($aud, $allowedClientIds, true)) {
            Log::warning('Google ID token verification failed: audience is not authorized.', [
                'aud' => $aud,
            ]);
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        // Verify expiration
        $exp = (int) ($decoded->exp ?? 0);
        if ($exp <= time()) {
            Log::warning('Google ID token verification failed: token has expired.');
            throw new InvalidArgumentException('Google ID token has expired.');
        }

        // Verify subject
        $sub = trim((string) ($decoded->sub ?? ''));
        if ($sub === '') {
            Log::warning('Google ID token verification failed: empty subject (sub) claim.');
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        // Verify email
        $email = trim((string) ($decoded->email ?? ''));
        if ($email === '') {
            Log::warning('Google ID token verification failed: missing email claim.');
            throw new InvalidArgumentException('Invalid Google ID token.');
        }

        $emailVerified = filter_var($decoded->email_verified ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'sub' => $sub,
            'email' => $email,
            'email_verified' => $emailVerified,
            'name' => (string) ($decoded->name ?? ''),
        ];
    }

    /**
     * Get Google JWKS key set, caching for 1 hour.
     *
     * @return array<string, mixed>
     *
     * @throws GoogleServiceUnavailableException
     */
    private function getGoogleJwks(): array
    {
        return Cache::remember('google_openid_jwks', 3600, function (): array {
            try {
                $response = Http::timeout(5)->get(self::GOOGLE_JWKS_URL);
            } catch (Throwable $e) {
                Log::warning('Failed to reach Google JWKS endpoint.', [
                    'error' => $e->getMessage(),
                ]);
                throw new GoogleServiceUnavailableException('Google authentication service is currently unavailable.', 0, $e);
            }

            if (!$response->successful()) {
                Log::warning('Google JWKS endpoint returned non-successful status.', [
                    'status' => $response->status(),
                ]);
                throw new GoogleServiceUnavailableException('Google authentication service is currently unavailable.');
            }

            /** @var array<string, mixed> */
            return $response->json();
        });
    }

    /**
     * Retrieve the list of allowed Google Client IDs.
     *
     * @return list<string>
     */
    private function getAllowedClientIds(): array
    {
        /** @var list<string> $clientIds */
        $clientIds = config('services.google.client_ids', []);

        if (empty($clientIds)) {
            $singleId = (string) config('services.google.client_id', '');
            if ($singleId !== '') {
                $clientIds = [$singleId];
            }
        }

        return array_values(array_filter(array_map('trim', $clientIds)));
    }
}
