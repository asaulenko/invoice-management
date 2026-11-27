<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\InvoiceNotFoundException;
use Modules\Invoices\Application\InvoiceService;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Invoices\Domain\Exceptions\InvoiceHasNoProductLinesException;
use Modules\Invoices\Domain\Invoice;
use Modules\Invoices\Domain\ProductLine;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class InvoiceController
{
    public function __construct(
        private InvoiceService $invoiceService,
    ) {}

    public function create(CreateInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->create(
            customerName: $request->string('customer_name')->toString(),
            customerEmail: $request->string('customer_email')->toString(),
            productLines: $request->productLines(),
        );

        return new JsonResponse($this->present($invoice), Response::HTTP_CREATED);
    }

    public function show(string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->get(Uuid::fromString($id));
        } catch (InvoiceNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return new JsonResponse($this->present($invoice));
    }

    public function send(string $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceService->send(Uuid::fromString($id));
        } catch (InvoiceNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (InvalidStatusTransitionException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        } catch (InvoiceHasNoProductLinesException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return new JsonResponse($this->present($invoice));
    }

    /** @return array<string, mixed> */
    private function present(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id->toString(),
            'status' => $invoice->status()->value,
            'customer_name' => $invoice->customerName,
            'customer_email' => $invoice->customerEmail->value,
            'product_lines' => array_map(
                static fn (ProductLine $line): array => [
                    'product_name' => $line->productName,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'total_unit_price' => $line->totalUnitPrice(),
                ],
                $invoice->productLines,
            ),
            'total_price' => $invoice->totalPrice(),
        ];
    }
}
