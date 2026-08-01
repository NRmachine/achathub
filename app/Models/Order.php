<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['number', 'access_token', 'user_id', 'guest_email', 'status', 'subtotal', 'shipping', 'shipping_method', 'estimated_delivery_date', 'total', 'payment_method', 'payment_status', 'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city', 'shipping_postal_code', 'carrier', 'tracking_number', 'shipped_at', 'delivered_at', 'notes'];

    protected $casts = ['subtotal' => 'decimal:2', 'shipping' => 'decimal:2', 'total' => 'decimal:2', 'estimated_delivery_date' => 'date', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusEvents()
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('happened_at');
    }

    public function returns()
    {
        return $this->hasMany(ProductReturn::class);
    }
}
