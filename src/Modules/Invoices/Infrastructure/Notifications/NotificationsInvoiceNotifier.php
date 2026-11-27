<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Notifications;

use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Domain\Invoice;
use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final readonly class NotificationsInvoiceNotifier implements InvoiceNotifier
{
    public function __construct(
        private NotificationFacadeInterface $notifications,
    ) {}

    public function notify(Invoice $invoice): void
    {
        // The invoice ID is the resource ID so the delivery webhook can find its way back to the invoice.
        $this->notifications->notify(new NotifyData(
            resourceId: $invoice->id,
            toEmail: $invoice->customerEmail->value,
            subject: "Invoice {$invoice->id->toString()}",
            message: "Dear {$invoice->customerName}, your invoice totals {$invoice->totalPrice()}.",
        ));
    }
}
