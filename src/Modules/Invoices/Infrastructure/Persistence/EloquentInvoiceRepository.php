<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\ConnectionInterface;
use Modules\Invoices\Application\Ports\InvoiceRepository;
use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function find(UuidInterface $id): ?Invoice
    {
        $model = InvoiceModel::query()->with('productLines')->find($id->toString());

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Invoice $invoice): void
    {
        $this->db->transaction(function () use ($invoice): void {
            $model = InvoiceModel::query()->updateOrCreate(
                ['id' => $invoice->id->toString()],
                [
                    'status' => $invoice->status()->value,
                    'customer_name' => $invoice->customerName,
                    'customer_email' => $invoice->customerEmail->value,
                ],
            );

            // The aggregate owns its lines, so the stored set is replaced rather than diffed.
            $model->productLines()->delete();
            $model->productLines()->createMany(array_map(
                static fn (ProductLine $line): array => [
                    'id' => $line->id->toString(),
                    'name' => $line->productName,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                ],
                $invoice->productLines,
            ));
        });
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        return Invoice::reconstitute(
            id: Uuid::fromString($model->id),
            status: StatusEnum::from($model->status),
            customerName: $model->customer_name,
            customerEmail: new Email($model->customer_email),
            productLines: $model->productLines
                ->map(static fn (ProductLineModel $line): ProductLine => new ProductLine(
                    id: Uuid::fromString($line->id),
                    productName: $line->name,
                    quantity: $line->quantity,
                    unitPrice: $line->unit_price,
                ))
                ->values()
                ->all(),
        );
    }
}
