<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvalidEmailException extends DomainException
{
    public function __construct(string $email)
    {
        parent::__construct("\"{$email}\" is not a valid email address.");
    }
}
