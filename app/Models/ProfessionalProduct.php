<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalProduct extends Model
{
    protected $fillable = ['sku', 'name', 'category', 'wholesale_price_ht', 'minimum_order_quantity', 'stock', 'image', 'description', 'active'];

    protected function casts(): array
    {
        return ['wholesale_price_ht' => 'decimal:2', 'active' => 'boolean'];
    }
}
