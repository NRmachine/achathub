<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'active', 'supplier_managed'];

    protected $casts = ['active' => 'boolean', 'supplier_managed' => 'boolean'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class, 'suggested_category_id');
    }

    public function catalogIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $frontier = self::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = [...$ids, ...$frontier];
        }

        return array_values(array_unique($ids));
    }
}
