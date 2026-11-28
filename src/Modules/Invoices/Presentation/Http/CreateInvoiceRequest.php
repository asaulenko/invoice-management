<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Foundation\Http\FormRequest;

final class CreateInvoiceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email:filter', 'max:255'],
            'product_lines' => ['sometimes', 'array', 'list'],
            'product_lines.*.product_name' => ['required', 'string', 'max:255'],
            'product_lines.*.quantity' => ['required', 'integer:strict', 'min:1', 'max:2147483647'],
            'product_lines.*.unit_price' => ['required', 'integer:strict', 'min:1', 'max:2147483647'],
        ];
    }

    /** @return list<array{productName: string, quantity: int, unitPrice: int}> */
    public function productLines(): array
    {
        return array_map(
            static fn (array $line): array => [
                'productName' => (string) $line['product_name'],
                'quantity' => (int) $line['quantity'],
                'unitPrice' => (int) $line['unit_price'],
            ],
            $this->validated('product_lines', []),
        );
    }
}
