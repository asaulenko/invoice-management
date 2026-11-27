<?php

declare(strict_types=1);

namespace Tests\Unit\Invoices\Domain;

use Modules\Invoices\Domain\Email;
use Modules\Invoices\Domain\Exceptions\InvalidEmailException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function test_accepts_valid_address(): void
    {
        $this->assertSame('jane@example.com', new Email('jane@example.com')->value);
    }

    #[DataProvider('invalidAddresses')]
    public function test_rejects_invalid_address(string $address): void
    {
        $this->expectException(InvalidEmailException::class);

        new Email($address);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'no at sign' => ['jane.example.com'];
        yield 'no domain' => ['jane@'];
    }
}
