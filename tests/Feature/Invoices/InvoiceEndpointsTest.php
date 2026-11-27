<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use PHPUnit\Framework\Attributes\DataProvider;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

final class InvoiceEndpointsTest extends TestCase
{
    public function test_creates_draft_invoice(): void
    {
        $response = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'draft',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'product_lines' => [],
                'total_price' => 0,
            ]);
        $this->assertTrue(Uuid::isValid($response->json('id')));
    }

    public function test_creates_invoice_with_product_lines_and_totals(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [
                ['product_name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150],
                ['product_name' => 'Mouse', 'quantity' => 3, 'unit_price' => 40],
            ],
        ])
            ->assertCreated()
            ->assertJson([
                'product_lines' => [
                    ['product_name' => 'Keyboard', 'quantity' => 2, 'unit_price' => 150, 'total_unit_price' => 300],
                    ['product_name' => 'Mouse', 'quantity' => 3, 'unit_price' => 40, 'total_unit_price' => 120],
                ],
                'total_price' => 420,
            ]);
    }

    public function test_views_created_invoice(): void
    {
        $id = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ])->json('id');

        $this->getJson("/api/invoices/{$id}")
            ->assertOk()
            ->assertExactJson([
                'id' => $id,
                'status' => 'draft',
                'customer_name' => 'Jane Doe',
                'customer_email' => 'jane@example.com',
                'product_lines' => [],
                'total_price' => 0,
            ]);
    }

    public function test_views_product_lines_in_creation_order(): void
    {
        $names = ['Mouse', 'Keyboard', 'Webcam', 'Cable', 'Monitor'];
        $id = $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => array_map(
                static fn (string $name): array => ['product_name' => $name, 'quantity' => 1, 'unit_price' => 10],
                $names,
            ),
        ])->json('id');

        $response = $this->getJson("/api/invoices/{$id}")->assertOk();

        $this->assertSame($names, $response->json('product_lines.*.product_name'));
        $response->assertJson(['total_price' => 50]);
    }

    public function test_viewing_unknown_invoice_returns_not_found(): void
    {
        $this->getJson('/api/invoices/'.Uuid::uuid4()->toString())
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidCreatePayloads')]
    public function test_creating_with_invalid_customer_data_returns_validation_error(array $payload, string $field): void
    {
        $this->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidCreatePayloads(): iterable
    {
        $valid = ['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com'];

        yield 'missing name' => [['customer_email' => 'jane@example.com'], 'customer_name'];
        yield 'name too long' => [[...$valid, 'customer_name' => str_repeat('a', 256)], 'customer_name'];
        yield 'missing email' => [['customer_name' => 'Jane Doe'], 'customer_email'];
        yield 'invalid email' => [[...$valid, 'customer_email' => 'not-an-email'], 'customer_email'];
        yield 'email without domain dot' => [[...$valid, 'customer_email' => 'jane@localhost'], 'customer_email'];
        yield 'email too long' => [[...$valid, 'customer_email' => str_repeat('a', 244).'@example.com'], 'customer_email'];
    }

    public function test_creates_invoice_with_explicitly_empty_product_lines(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [],
        ])
            ->assertCreated()
            ->assertJson(['product_lines' => [], 'total_price' => 0]);
    }

    /** @param array<string, mixed> $line */
    #[DataProvider('invalidProductLines')]
    public function test_creating_with_invalid_product_line_returns_validation_error(array $line, string $field): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => [$line],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(["product_lines.0.{$field}"]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidProductLines(): iterable
    {
        $valid = ['product_name' => 'Keyboard', 'quantity' => 1, 'unit_price' => 100];

        yield 'missing product name' => [['quantity' => 1, 'unit_price' => 100], 'product_name'];
        yield 'product name too long' => [[...$valid, 'product_name' => str_repeat('a', 256)], 'product_name'];
        yield 'missing quantity' => [['product_name' => 'Keyboard', 'unit_price' => 100], 'quantity'];
        yield 'zero quantity' => [[...$valid, 'quantity' => 0], 'quantity'];
        yield 'negative quantity' => [[...$valid, 'quantity' => -1], 'quantity'];
        yield 'fractional quantity' => [[...$valid, 'quantity' => 1.5], 'quantity'];
        yield 'missing unit price' => [['product_name' => 'Keyboard', 'quantity' => 1], 'unit_price'];
        yield 'zero unit price' => [[...$valid, 'unit_price' => 0], 'unit_price'];
        yield 'negative unit price' => [[...$valid, 'unit_price' => -100], 'unit_price'];
        yield 'fractional unit price' => [[...$valid, 'unit_price' => 9.99], 'unit_price'];
    }

    public function test_creating_with_non_list_product_lines_returns_validation_error(): void
    {
        $this->postJson('/api/invoices', [
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'product_lines' => 'Keyboard',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_lines']);
    }
}
