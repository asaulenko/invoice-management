<?php

declare(strict_types=1);

namespace Modules\Invoices\Presentation\Listeners;

use Modules\Invoices\Application\InvoiceService;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final readonly class MarkInvoiceSentToClient
{
    public function __construct(
        private InvoiceService $invoices,
    ) {}

    public function handle(WebhookDeliveredEvent $event): void
    {
        $this->invoices->markSentToClient($event->resourceId);
    }
}
