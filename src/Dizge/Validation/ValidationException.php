<?php

declare(strict_types=1);

namespace Dizge\Validation;

final class ValidationException extends \RuntimeException
{
    public function __construct(private ErrorBag $errors)
    {
        parent::__construct('Request validation failed.');
    }

    public function errors(): ErrorBag { return $this->errors; }
}
