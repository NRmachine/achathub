<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalPreorder extends Model
{
    protected $fillable = ['number', 'user_id', 'reseller_request_id', 'professional_product_id', 'status', 'message', 'admin_notes', 'reviewed_at', 'reviewed_by'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(ProfessionalProduct::class, 'professional_product_id');
    }

    public function resellerRequest()
    {
        return $this->belongsTo(ResellerRequest::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'Nouvelle' => 'En attente',
            'Acceptée', 'Validée' => 'Validée',
            default => $this->status,
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Acceptée', 'Validée', 'Terminée' => 'text-bg-success',
            'Refusée' => 'text-bg-danger',
            'En cours' => 'text-bg-info',
            default => 'text-bg-warning',
        };
    }

    public function canBeDeletedBy(User $user): bool
    {
        return $this->user_id === $user->id && in_array($this->status, ['Nouvelle', 'En cours'], true);
    }
}
