<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class EmailChangeNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Email cannot be changed for accounts that sign in with Google.')
    {
        parent::__construct(403, $message);
    }
}
