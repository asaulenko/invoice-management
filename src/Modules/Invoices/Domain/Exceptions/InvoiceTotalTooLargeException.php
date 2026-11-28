<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;

final class InvoiceTotalTooLargeException extends DomainException
{
    public function __construct()
    {
        parent::__construct('The invoice total exceeds the supported integer range.');
    }
}
