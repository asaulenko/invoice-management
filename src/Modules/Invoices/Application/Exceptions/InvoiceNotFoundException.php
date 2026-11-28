<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Exceptions;

use Ramsey\Uuid\UuidInterface;
use RuntimeException;

final class InvoiceNotFoundException extends RuntimeException
{
    public function __construct(UuidInterface $id)
    {
        parent::__construct("Invoice {$id->toString()} not found.");
    }
}
