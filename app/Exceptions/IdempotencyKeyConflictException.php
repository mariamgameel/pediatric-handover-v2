<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class IdempotencyKeyConflictException extends HttpException
{
    public function __construct(string $message = 'The idempotency key has already been used with a different request payload.')
    {
        parent::__construct(409, $message);
    }
}
