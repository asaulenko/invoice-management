<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Listeners;

use Modules\Invoices\Application\InvoiceNotFoundException;
use Modules\Invoices\Application\InvoiceService;
use Modules\Invoices\Domain\Enums\StatusEnum;
use Modules\Invoices\Domain\Exceptions\InvalidStatusTransitionException;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;
use Psr\Log\LoggerInterface;

final readonly class MarkInvoiceSentToClient
{
    public function __construct(
        private InvoiceService $invoices,
        private LoggerInterface $logger,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        try {
            $this->invoices->markSentToClient($event->resourceId);
        } catch (InvoiceNotFoundException $e) {
            // Notifications is shared, so the resource ID may belong to something other than an invoice.
            $this->logger->warning('Delivery webhook for unknown invoice ignored.', [
                'resource_id' => $event->resourceId->toString(),
            ]);
        } catch (InvalidStatusTransitionException $e) {
            // Already sent-to-client means the provider repeated the webhook, which is expected and harmless.
            if ($e->current !== StatusEnum::SentToClient) {
                $this->logger->warning('Delivery webhook for invoice that is not being sent ignored.', [
                    'invoice_id' => $event->resourceId->toString(),
                    'status' => $e->current->value,
                ]);
            }
        }
    }
}
