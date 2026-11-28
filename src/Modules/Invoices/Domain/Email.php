<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidEmailException;

final readonly class Email
{
    public function __construct(
        public string $value,
    ) {
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmailException($value);
        }
    }
}
