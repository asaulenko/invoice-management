<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    private function __construct(
        public readonly UuidInterface $id,
        private StatusEnum $status,
        public readonly string $customerName,
        public readonly Email $customerEmail,
    ) {}

    public static function draft(UuidInterface $id, string $customerName, Email $customerEmail): self
    {
        return new self($id, StatusEnum::Draft, $customerName, $customerEmail);
    }

    // Rebuilds a stored invoice as-is; transition rules don't apply to state that is already persisted.
    public static function reconstitute(
        UuidInterface $id,
        StatusEnum $status,
        string $customerName,
        Email $customerEmail,
    ): self {
        return new self($id, $status, $customerName, $customerEmail);
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }
}
