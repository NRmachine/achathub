<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerRequest extends Model
{
    protected $fillable = ['user_id', 'business_name', 'company_type', 'legal_form', 'commercial_name', 'siren', 'siret', 'vat_number', 'manager_name', 'city', 'address', 'postal_code', 'phone', 'email', 'business_type', 'activity', 'formula', 'display_type', 'categories', 'message', 'status', 'reviewed_at', 'approved_at', 'reviewed_by', 'admin_notes'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function professionalOrders()
    {
        return $this->hasMany(ProfessionalOrder::class);
    }

    public function professionalPreorders()
    {
        return $this->hasMany(ProfessionalPreorder::class);
    }
}
