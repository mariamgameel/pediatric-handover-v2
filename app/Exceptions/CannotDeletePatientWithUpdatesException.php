<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class CannotDeletePatientWithUpdatesException extends RuntimeException
{
    public function __construct(
        string $message = 'Cannot delete patient because clinical updates exist for this patient.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
