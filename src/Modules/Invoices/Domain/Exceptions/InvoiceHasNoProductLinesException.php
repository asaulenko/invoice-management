<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Ramsey\Uuid\UuidInterface;

final class InvoiceHasNoProductLinesException extends DomainException
{
    public function __construct(UuidInterface $id)
    {
        parent::__construct("Invoice {$id->toString()} has no product lines and cannot be sent.");
    }
}
