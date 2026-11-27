<?php

declare(strict_types=1);

namespace Modules\Invoices\Application;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
    ) {}

    public function create(string $customerName, string $customerEmail): Invoice
    {
        $invoice = Invoice::draft(Uuid::uuid4(), $customerName, new Email($customerEmail));
        $this->invoices->save($invoice);

        return $invoice;
    }

    public function get(UuidInterface $id): Invoice
    {
        return $this->invoices->find($id) ?? throw new InvoiceNotFoundException($id);
    }
}
