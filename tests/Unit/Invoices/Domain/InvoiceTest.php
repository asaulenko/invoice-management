<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceHasNoProductLinesException;
use Modules\Invoices\Domain\Exceptions\InvoiceTotalTooLargeException;
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

    public function test_total_can_equal_the_largest_integer(): void
    {
        $invoice = $this->draft([
            $this->line(quantity: 1, unitPrice: PHP_INT_MAX - 1),
            $this->line(quantity: 1, unitPrice: 1),
        ]);

        $this->assertSame(PHP_INT_MAX, $invoice->totalPrice());
    }

    public function test_rejects_invoice_whose_total_exceeds_the_integer_range(): void
    {
        $this->expectException(InvoiceTotalTooLargeException::class);

        $this->draft([
            $this->line(quantity: 1, unitPrice: PHP_INT_MAX),
            $this->line(quantity: 1, unitPrice: 1),
        ]);
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

    public function test_sending_invoice_can_be_marked_sent_to_client(): void
    {
        $invoice = $this->draft([$this->line(quantity: 1, unitPrice: 100)]);
        $invoice->send();

        $invoice->markSentToClient();

        $this->assertSame(StatusEnum::SentToClient, $invoice->status());
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_non_sending_invoice_cannot_be_marked_sent_to_client(StatusEnum $status): void
    {
        $invoice = Invoice::reconstitute(
            Uuid::uuid4(),
            $status,
            'Jane Doe',
            new Email('jane@example.com'),
            [$this->line(quantity: 1, unitPrice: 100)],
        );

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->markSentToClient();
    }

    /** @return iterable<string, array{StatusEnum}> */
    public static function nonSendingStatuses(): iterable
    {
        yield 'draft' => [StatusEnum::Draft];
        yield 'sent to client' => [StatusEnum::SentToClient];
    }

    public function test_sending_invoice_can_be_reverted_to_draft(): void
    {
        $invoice = $this->draft([$this->line(quantity: 1, unitPrice: 100)]);
        $invoice->send();

        $invoice->revertToDraft();

        $this->assertSame(StatusEnum::Draft, $invoice->status());
    }

    #[DataProvider('nonSendingStatuses')]
    public function test_non_sending_invoice_cannot_be_reverted_to_draft(StatusEnum $status): void
    {
        $invoice = Invoice::reconstitute(
            Uuid::uuid4(),
            $status,
            'Jane Doe',
            new Email('jane@example.com'),
            [$this->line(quantity: 1, unitPrice: 100)],
        );

        $this->expectException(InvalidStatusTransitionException::class);

        $invoice->revertToDraft();
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
