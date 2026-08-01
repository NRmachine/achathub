<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierStockChange extends Model
{
    protected $fillable = ['supplier_product_id', 'old_stock', 'new_stock', 'difference', 'observed_at'];

    protected $casts = ['observed_at' => 'datetime'];

    public function supplierProduct()
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
