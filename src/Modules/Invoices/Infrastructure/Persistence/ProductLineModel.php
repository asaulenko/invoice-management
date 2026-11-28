<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

final class ProductLineModel extends Model
{
    protected $table = 'invoice_product_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];
}
