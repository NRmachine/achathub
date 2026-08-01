<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturnItem extends Model
{
    protected $table = 'return_request_items';

    protected $fillable = ['return_request_id', 'order_item_id', 'quantity'];

    public function returnRequest()
    {
        return $this->belongsTo(ProductReturn::class, 'return_request_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
