<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'address', 'role', 'blocked', 'provider', 'admin_notes', 'last_admin_update_at', 'terms_accepted_at', 'privacy_version'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'blocked' => 'boolean',
            'last_admin_update_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function resellerRequest()
    {
        return $this->hasOne(ResellerRequest::class)->latestOfMany();
    }

    public function professionalOrders()
    {
        return $this->hasMany(ProfessionalOrder::class);
    }

    public function professionalPreorders()
    {
        return $this->hasMany(ProfessionalPreorder::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }

    public function sentConversationMessages()
    {
        return $this->hasMany(ConversationMessage::class, 'sender_id');
    }

    public function dataRightsRequests()
    {
        return $this->hasMany(DataRightsRequest::class);
    }
}
