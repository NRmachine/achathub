<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalOrder extends Model
{
    protected $fillable = ['number', 'user_id', 'reseller_request_id', 'status', 'payment_status', 'payment_method', 'subtotal_ht', 'vat_amount', 'total_ttc', 'contact_name', 'phone', 'address', 'city', 'notes'];

    protected function casts(): array
    {
        return ['subtotal_ht' => 'decimal:2', 'vat_amount' => 'decimal:2', 'total_ttc' => 'decimal:2'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resellerRequest()
    {
        return $this->belongsTo(ResellerRequest::class);
    }

    public function items()
    {
        return $this->hasMany(ProfessionalOrderItem::class);
    }
}
