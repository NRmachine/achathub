<?php

namespace App\Models;

use App\Support\OptimizedAsset;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ProfessionalProduct extends Model
{
    public const VAT_RATE = 20;

    protected $fillable = ['sku', 'name', 'category', 'wholesale_price_ht', 'minimum_order_quantity', 'stock', 'image', 'description', 'active'];

    protected function casts(): array
    {
        return [
            'wholesale_price_ht' => 'decimal:2',
            'minimum_order_quantity' => 'integer',
            'stock' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected function image(): Attribute
    {
        return Attribute::make(get: fn (?string $value): ?string => OptimizedAsset::image($value));
    }

    public function displays()
    {
        return $this->belongsToMany(ProfessionalDisplay::class, 'professional_display_items')
            ->withPivot(['quantity', 'unit_price_ht'])
            ->withTimestamps();
    }

    public function getPriceTtcAttribute(): float
    {
        return round((float) $this->wholesale_price_ht * (1 + self::VAT_RATE / 100), 2);
    }

    public function getMinimumOrderTotalHtAttribute(): float
    {
        return round((float) $this->wholesale_price_ht * $this->minimum_order_quantity, 2);
    }

    public function getMinimumOrderTotalTtcAttribute(): float
    {
        return round($this->minimum_order_total_ht * (1 + self::VAT_RATE / 100), 2);
    }
}
