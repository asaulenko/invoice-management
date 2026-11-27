<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Invoice;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    public function test_new_invoice_is_draft(): void
    {
        $invoice = Invoice::draft(Uuid::uuid4(), 'Jane Doe', new Email('jane@example.com'));

        $this->assertSame(StatusEnum::Draft, $invoice->status());
    }
}
