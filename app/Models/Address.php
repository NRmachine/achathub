<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['user_id', 'label', 'name', 'phone', 'address', 'city', 'postal_code', 'default'];

    protected $casts = ['default' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
