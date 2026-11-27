<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Modules\Invoices\Application\InvoiceRepository;
use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final readonly class EloquentInvoiceRepository implements InvoiceRepository
{
    public function find(UuidInterface $id): ?Invoice
    {
        $model = InvoiceModel::query()->find($id->toString());

        return $model === null ? null : $this->toDomain($model);
    }

    public function save(Invoice $invoice): void
    {
        InvoiceModel::query()->updateOrCreate(
            ['id' => $invoice->id->toString()],
            [
                'status' => $invoice->status()->value,
                'customer_name' => $invoice->customerName,
                'customer_email' => $invoice->customerEmail->value,
            ],
        );
    }

    private function toDomain(InvoiceModel $model): Invoice
    {
        return Invoice::reconstitute(
            id: Uuid::fromString($model->id),
            status: StatusEnum::from($model->status),
            customerName: $model->customer_name,
            customerEmail: new Email($model->customer_email),
        );
    }
}
