<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturn extends Model
{
    protected $table = 'return_requests';

    protected $fillable = ['number', 'order_id', 'user_id', 'reason', 'solution', 'return_method', 'status', 'details', 'admin_notes', 'received_at', 'refunded_at'];

    protected $casts = ['received_at' => 'datetime', 'refunded_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(ProductReturnItem::class, 'return_request_id');
    }
}
