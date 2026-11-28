<?php

declare(strict_types=1);

namespace Modules\Invoices\Domain;

use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceHasNoProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvoiceTotalTooLargeException;
use Ramsey\Uuid\UuidInterface;

final class Invoice
{
    /** @param list<ProductLine> $productLines */
    private function __construct(
        public readonly UuidInterface $id,
        private StatusEnum $status,
        public readonly string $customerName,
        public readonly Email $customerEmail,
        public readonly array $productLines,
    ) {
        // Reject unrepresentable totals before an invoice can be persisted.
        $this->totalPrice();
    }

    /** @param list<ProductLine> $productLines */
    public static function draft(
        UuidInterface $id,
        string $customerName,
        Email $customerEmail,
        array $productLines,
    ): self {
        return new self($id, StatusEnum::Draft, $customerName, $customerEmail, $productLines);
    }

    /**
     * Rebuilds a stored invoice as-is; transition rules don't apply to state that is already persisted.
     *
     * @param  list<ProductLine>  $productLines
     */
    public static function reconstitute(
        UuidInterface $id,
        StatusEnum $status,
        string $customerName,
        Email $customerEmail,
        array $productLines,
    ): self {
        return new self($id, $status, $customerName, $customerEmail, $productLines);
    }

    public function status(): StatusEnum
    {
        return $this->status;
    }

    public function send(): void
    {
        if ($this->status !== StatusEnum::Draft) {
            throw InvalidStatusTransitionException::cannotSend($this->id, $this->status);
        }

        if ($this->productLines === []) {
            throw new InvoiceHasNoProductLinesException($this->id);
        }

        $this->status = StatusEnum::Sending;
    }

    public function markSentToClient(): void
    {
        if ($this->status !== StatusEnum::Sending) {
            throw InvalidStatusTransitionException::cannotMarkSentToClient($this->id, $this->status);
        }

        $this->status = StatusEnum::SentToClient;
    }

    public function revertToDraft(): void
    {
        if ($this->status !== StatusEnum::Sending) {
            throw InvalidStatusTransitionException::cannotRevertToDraft($this->id, $this->status);
        }

        $this->status = StatusEnum::Draft;
    }

    public function totalPrice(): int
    {
        $total = 0;

        foreach ($this->productLines as $line) {
            $lineTotal = $line->totalUnitPrice();
            if ($lineTotal > PHP_INT_MAX - $total) {
                throw new InvoiceTotalTooLargeException;
            }

            $total += $lineTotal;
        }

        return $total;
    }
}
