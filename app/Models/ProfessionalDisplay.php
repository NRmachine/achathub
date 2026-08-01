<?php

namespace App\Models;

use App\Support\OptimizedAsset;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ProfessionalDisplay extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'wholesale_price_ht', 'vat_rate', 'image', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['wholesale_price_ht' => 'decimal:2', 'vat_rate' => 'decimal:2', 'active' => 'boolean'];
    }

    protected function image(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => OptimizedAsset::image($value));
    }

    public function items()
    {
        return $this->hasMany(ProfessionalDisplayItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(ProfessionalProduct::class, 'professional_display_items')
            ->withPivot(['quantity', 'unit_price_ht'])
            ->withTimestamps();
    }

    public function getPriceTtcAttribute(): float
    {
        return round((float) $this->wholesale_price_ht * (1 + (float) $this->vat_rate / 100), 2);
    }
}
