<?php

declare(strict_types=1);

namespace Modules\Invoices\Application;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private NotificationFacadeInterface $notifications,
    ) {}

    /** @param list<array{productName: string, quantity: int, unitPrice: int}> $productLines */
    public function create(string $customerName, string $customerEmail, array $productLines): Invoice
    {
        $invoice = Invoice::draft(
            Uuid::uuid4(),
            $customerName,
            new Email($customerEmail),
            array_map(
                // UUIDv7 is time-ordered, so line IDs double as the creation order the schema has no column for.
                static fn (array $line): ProductLine => new ProductLine(
                    Uuid::uuid7(),
                    $line['productName'],
                    $line['quantity'],
                    $line['unitPrice'],
                ),
                $productLines,
            ),
        );
        $this->invoices->save($invoice);

        return $invoice;
    }

    public function get(UuidInterface $id): Invoice
    {
        return $this->invoices->find($id) ?? throw new InvoiceNotFoundException($id);
    }

    public function send(UuidInterface $id): Invoice
    {
        $invoice = $this->get($id);
        $invoice->send();
        $this->invoices->save($invoice);

        $this->notifications->notify(new NotifyData(
            resourceId: $invoice->id,
            toEmail: $invoice->customerEmail->value,
            subject: "Invoice {$invoice->id->toString()}",
            message: "Dear {$invoice->customerName}, your invoice totals {$invoice->totalPrice()}.",
        ));

        return $invoice;
    }
}
