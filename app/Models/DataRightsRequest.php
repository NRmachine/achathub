<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataRightsRequest extends Model
{
    protected $fillable = ['user_id', 'type', 'message', 'status', 'admin_response', 'handled_at', 'handled_by'];
    protected $casts = ['handled_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function handler() { return $this->belongsTo(User::class, 'handled_by'); }
}
