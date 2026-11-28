<?php

declare(strict_types=1);

namespace Modules\Invoices\Application;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Exceptions\InvoiceNotFoundException;
use Modules\Invoices\Application\Exceptions\NotificationFailedException;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Application\Ports\InvoiceRepository;
use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

final readonly class InvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private InvoiceNotifier $notifier,
        private LoggerInterface $logger,
        private ConnectionInterface $db,
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
        // Concurrent sends queue on the row lock, so the second one sees `sending` and is rejected.
        // Committing before notifying means a delivery webhook always finds `sending` persisted.
        $invoice = $this->db->transaction(function () use ($id): Invoice {
            $invoice = $this->invoices->findForUpdate($id) ?? throw new InvoiceNotFoundException($id);
            $invoice->send();
            $this->invoices->save($invoice);

            return $invoice;
        });

        try {
            $this->notifier->notify($invoice);
        } catch (Throwable $e) {
            $this->revertToDraft($invoice);

            throw new NotificationFailedException($id, $e);
        }

        return $invoice;
    }

    public function markSentToClient(UuidInterface $id): void
    {
        $invoice = $this->get($id);
        $invoice->markSentToClient();
        $this->invoices->save($invoice);
    }

    private function revertToDraft(Invoice $invoice): void
    {
        try {
            $invoice->revertToDraft();
            $this->invoices->save($invoice);
        } catch (Throwable $e) {
            // Nothing else can be done here; the invoice stays in sending until someone intervenes.
            $this->logger->error('Failed to revert invoice to draft after notification failure.', [
                'invoice_id' => $invoice->id->toString(),
                'exception' => $e,
            ]);
        }
    }
}
