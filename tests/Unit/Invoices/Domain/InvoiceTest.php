<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceHasNoProductLinesException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_sending_draft_with_lines_moves_it_to_sending(): void
    {
        $invoice = $this->draft([$this->line(quantity: 1, unitPrice: 100)]);

        $invoice->send();

        $this->assertSame(StatusEnum::Sending, $invoice->status());
    }

    public function test_invoice_without_lines_cannot_be_sent(): void
    {
        $invoice = $this->draft([]);

        $this->expectException(InvoiceHasNoProductLinesException::class);

        $invoice->send();
    }

    #[DataProvider('nonDraftStatuses')]
    public function test_non_draft_invoice_cannot_be_sent(StatusEnum $status): void
    {
        $invoice = Invoice::reconstitute(
            Uuid::uuid4(),
            $status,
            'Jane Doe',
            new Email('jane@example.com'),
            [$this->line(quantity: 1, unitPrice: 100)],
        );

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->send();
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function nonDraftStatuses(): iterable
    {
        yield 'sending' => [StatusEnum::Sending];
        yield 'sent to client' => [StatusEnum::SentToClient];
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
