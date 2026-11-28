<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain\Exceptions;

use DomainException;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class InvalidStatusTransitionException extends DomainException
{
    private function __construct(
        string $message,
        public readonly StatusEnum $current,
    ) {
        parent::__construct($message);
    }

    public static function cannotSend(UuidInterface $id, StatusEnum $current): self
    {
        return new self("Invoice {$id->toString()} is {$current->value}; only a draft can be sent.", $current);
    }

    public static function cannotMarkSentToClient(UuidInterface $id, StatusEnum $current): self
    {
        return new self("Invoice {$id->toString()} is {$current->value}; only a sending invoice can be marked sent to client.", $current);
    }

    public static function cannotRevertToDraft(UuidInterface $id, StatusEnum $current): self
    {
        return new self("Invoice {$id->toString()} is {$current->value}; only a sending invoice can be reverted to draft.", $current);
    }
}
