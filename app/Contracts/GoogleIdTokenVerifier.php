<?php

declare(strict_types=1);

namespace App\Contracts;

interface GoogleIdTokenVerifier
{
    /**
     * Verify the Google ID token and return verified payload.
     *
     * @param string $idToken
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     *
     * @throws \InvalidArgumentException
     */
    public function verify(string $idToken): array;
}
