<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Contracts\GoogleIdTokenVerifier;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new InvalidArgumentException('Google ID token is required.');
        }

        $jwks = $this->getGoogleJwks();

        try {
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($idToken, $keys);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid Google ID token signature or format: ' . $e->getMessage(), 0, $e);
        }

        // Verify issuer
        $iss = (string) ($decoded->iss ?? '');
        if (!in_array($iss, self::VALID_ISSUERS, true)) {
            throw new InvalidArgumentException("Invalid Google token issuer: '{$iss}'.");
        }

        // Verify audience
        $aud = (string) ($decoded->aud ?? '');
        $allowedClientIds = $this->getAllowedClientIds();

        if ($aud === '' || (!empty($allowedClientIds) && !in_array($aud, $allowedClientIds, true))) {
            throw new InvalidArgumentException("Google ID token audience '{$aud}' is not authorized.");
        }

        // Verify expiration
        $exp = (int) ($decoded->exp ?? 0);
        if ($exp <= time()) {
            throw new InvalidArgumentException('Google ID token has expired.');
        }

        $email = (string) ($decoded->email ?? '');
        if ($email === '') {
            throw new InvalidArgumentException('Google ID token does not contain an email address.');
        }

        $emailVerified = filter_var($decoded->email_verified ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'sub' => (string) ($decoded->sub ?? ''),
            'email' => $email,
            'email_verified' => $emailVerified,
            'name' => (string) ($decoded->name ?? ''),
        ];
    }

    /**
     * Get Google JWKS key set, caching for 1 hour.
     *
     * @return array<string, mixed>
     */
    private function getGoogleJwks(): array
    {
        return Cache::remember('google_openid_jwks', 3600, function (): array {
            $response = Http::timeout(5)->get(self::GOOGLE_JWKS_URL);

            if (!$response->successful()) {
                throw new InvalidArgumentException('Unable to fetch Google public signing keys.');
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
