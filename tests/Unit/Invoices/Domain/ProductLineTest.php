<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Exceptions\InvalidProductLineException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProductLineTest extends TestCase
{
    public function test_total_unit_price_is_quantity_times_unit_price(): void
    {
        $this->assertSame(300, $this->line(quantity: 3, unitPrice: 100)->totalUnitPrice());
    }

    #[DataProvider('nonPositiveAmounts')]
    public function test_rejects_non_positive_quantity(int $quantity): void
    {
        $this->expectException(InvalidProductLineException::class);

        $this->line(quantity: $quantity, unitPrice: 100);
    }

    #[DataProvider('nonPositiveAmounts')]
    public function test_rejects_non_positive_unit_price(int $unitPrice): void
    {
        $this->expectException(InvalidProductLineException::class);

        $this->line(quantity: 1, unitPrice: $unitPrice);
    }

    /** @return iterable<string, array{int}> */
    public static function nonPositiveAmounts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function test_invoice_total_is_sum_of_line_totals(): void
    {
        $invoice = Invoice::draft(Uuid::uuid4(), 'Jane Doe', new Email('jane@example.com'), [
            $this->line(quantity: 2, unitPrice: 150),
            $this->line(quantity: 3, unitPrice: 40),
        ]);

        $this->assertSame(420, $invoice->totalPrice());
    }

    public function test_invoice_without_lines_totals_zero(): void
    {
        $invoice = Invoice::draft(Uuid::uuid4(), 'Jane Doe', new Email('jane@example.com'), []);

        $this->assertSame(0, $invoice->totalPrice());
    }

    private function line(int $quantity, int $unitPrice): ProductLine
    {
        return new ProductLine(Uuid::uuid7(), 'Keyboard', $quantity, $unitPrice);
    }
}
