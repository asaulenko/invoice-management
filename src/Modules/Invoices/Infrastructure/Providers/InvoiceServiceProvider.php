<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Invoices\Application\InvoiceRepository;
use Modules\Invoices\Infrastructure\Persistence\EloquentInvoiceRepository;

final class InvoiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(InvoiceRepository::class, EloquentInvoiceRepository::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../../Presentation/routes.php');
    }
}
