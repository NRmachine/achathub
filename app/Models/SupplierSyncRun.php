<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierSyncRun extends Model
{
    protected $fillable = [
        'provider', 'mode', 'status', 'pages_scanned', 'products_seen', 'variants_seen',
        'mapped_count', 'updated_count', 'out_of_stock_count', 'error_count', 'message',
        'started_at', 'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
