<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InvoiceModel extends Model
{
    protected $table = 'invoices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /** @return HasMany<ProductLineModel, $this> */
    public function productLines(): HasMany
    {
        // Line IDs are UUIDv7, so sorting by ID gives the order the lines were created in.
        return $this->hasMany(ProductLineModel::class, 'invoice_id')->orderBy('id');
    }
}
