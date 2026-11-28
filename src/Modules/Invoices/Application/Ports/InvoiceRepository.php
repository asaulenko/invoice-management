<?php

declare(strict_types=1);

namespace Modules\Invoices\Application\Ports;

use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepository
{
    public function find(UuidInterface $id): ?Invoice;

    /**
     * Must be called inside a transaction.
     * The lock is held until it commits or rolls back.
     */
    public function findForUpdate(UuidInterface $id): ?Invoice;

    public function save(Invoice $invoice): void;
}
