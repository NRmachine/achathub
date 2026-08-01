<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalDisplayItem extends Model
{
    protected $fillable = ['professional_display_id', 'professional_product_id', 'quantity', 'unit_price_ht'];

    public function product()
    {
        return $this->belongsTo(ProfessionalProduct::class, 'professional_product_id');
    }
}
