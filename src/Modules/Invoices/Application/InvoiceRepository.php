<?php

declare(strict_types=1);

namespace Modules\Invoices\Application;

use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\UuidInterface;

interface InvoiceRepository
{
    public function find(UuidInterface $id): ?Invoice;

    public function save(Invoice $invoice): void;
}
