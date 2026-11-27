<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvalidStatusTransitionException extends DomainException
{
    public static function cannotSend(UuidInterface $id, StatusEnum $current): self
    {
        return new self("Invoice {$id->toString()} is {$current->value}; only a draft can be sent.");
    }
}
