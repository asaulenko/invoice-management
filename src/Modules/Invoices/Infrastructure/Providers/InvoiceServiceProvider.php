<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\Ports\InvoiceNotifier;
use Modules\Invoices\Application\Ports\InvoiceRepository;
use Modules\Invoices\Infrastructure\Notifications\NotificationsInvoiceNotifier;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;
use Modules\Invoices\Presentation\Listeners\MarkInvoiceSentToClient;
use Modules\Notifications\Api\Events\WebhookDeliveredEvent;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepository::class, EloquentInvoiceRepository::class);
        $this->app->scoped(InvoiceNotifier::class, NotificationsInvoiceNotifier::class);
    }

    public function boot(): void
    {
        Event::listen(WebhookDeliveredEvent::class, MarkInvoiceSentToClient::class);

        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../../Presentation/routes.php');
    }
}
