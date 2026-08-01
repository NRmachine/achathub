<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderStatusEvent extends Model
{
    protected $fillable = ['order_id', 'status', 'message', 'happened_at'];

    protected $casts = ['happened_at' => 'datetime'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
