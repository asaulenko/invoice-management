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
        yield 'email too long' => [[...$valid, 'customer_email' => str_repeat('a', 244).'@example.com'], 'customer_email'];
    }
}
