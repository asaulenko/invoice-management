<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Ramsey\Uuid\UuidInterface;

final readonly class ProductLine
{
    public function __construct(
        public UuidInterface $id,
        public string $productName,
        public int $quantity,
        public int $unitPrice,
    ) {
        if ($quantity < 1) {
            throw InvalidProductLineException::nonPositiveQuantity($quantity);
        }

        if ($unitPrice < 1) {
            throw InvalidProductLineException::nonPositiveUnitPrice($unitPrice);
        }
    }

    public function totalUnitPrice(): int
    {
        return $this->quantity * $this->unitPrice;
    }
}
