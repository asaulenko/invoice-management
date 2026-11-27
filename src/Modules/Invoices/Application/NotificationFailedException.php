<?php

declare(strict_types=1);

namespace Modules\Invoices\Application;

use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use Throwable;

final class NotificationFailedException extends RuntimeException
{
    public function __construct(UuidInterface $id, Throwable $previous)
    {
        parent::__construct("Invoice {$id->toString()} could not be sent; try again later.", previous: $previous);
    }
}
