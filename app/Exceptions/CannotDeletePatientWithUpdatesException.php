<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class CannotDeletePatientWithUpdatesException extends RuntimeException
{
    public function __construct(string $message = 'Cannot delete patient because clinical updates exist for this patient.')
    {
        parent::__construct($message);
    }
}
