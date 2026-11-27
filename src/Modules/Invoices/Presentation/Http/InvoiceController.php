<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Modules\Invoices\Application\InvoiceNotFoundException;
use Modules\Invoices\Application\InvoiceService;
use Modules\Invoices\Domain\Invoice;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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

    /** @return array<string, mixed> */
    private function present(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id->toString(),
            'status' => $invoice->status()->value,
            'customer_name' => $invoice->customerName,
            'customer_email' => $invoice->customerEmail->value,
            // Product lines arrive in S2; the shape is fixed from the start so clients never see it change.
            'product_lines' => [],
            'total_price' => 0,
        ];
    }
}
