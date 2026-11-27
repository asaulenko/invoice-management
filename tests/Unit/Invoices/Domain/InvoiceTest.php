<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class InvoiceTest extends TestCase
{
    public function test_total_is_sum_of_line_totals(): void
    {
        $invoice = $this->draft([
            $this->line(quantity: 2, unitPrice: 150),
            $this->line(quantity: 3, unitPrice: 40),
        ]);

        $this->assertSame(420, $invoice->totalPrice());
    }

    public function test_invoice_without_lines_totals_zero(): void
    {
        $this->assertSame(0, $this->draft([])->totalPrice());
    }

    /** @param list<ProductLine> $lines */
    private function draft(array $lines): Invoice
    {
        return Invoice::draft(Uuid::uuid4(), 'Jane Doe', new Email('jane@example.com'), $lines);
    }

    private function line(int $quantity, int $unitPrice): ProductLine
    {
        return new ProductLine(Uuid::uuid7(), 'Keyboard', $quantity, $unitPrice);
    }
}
