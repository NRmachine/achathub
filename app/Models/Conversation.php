<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['user_id', 'subject', 'status', 'last_message_at', 'admin_read_at', 'user_read_at'];

    protected $casts = ['last_message_at' => 'datetime', 'admin_read_at' => 'datetime', 'user_read_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function messages() { return $this->hasMany(ConversationMessage::class); }
    public function lastMessage() { return $this->hasOne(ConversationMessage::class)->latestOfMany(); }
}
