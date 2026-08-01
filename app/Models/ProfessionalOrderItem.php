<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalOrderItem extends Model
{
    protected $fillable = ['professional_order_id', 'professional_display_id', 'professional_product_id', 'name', 'price_ht', 'quantity', 'vat_rate'];

    public function display()
    {
        return $this->belongsTo(ProfessionalDisplay::class, 'professional_display_id');
    }

    public function product()
    {
        return $this->belongsTo(ProfessionalProduct::class, 'professional_product_id');
    }
}
