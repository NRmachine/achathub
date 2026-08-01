<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'legacy_id', 'sku', 'name', 'slug', 'brand', 'model', 'family', 'subcategory', 'price', 'old_price', 'discount', 'stock', 'rating', 'reviews_count', 'condition', 'tag', 'image', 'images', 'description', 'features', 'active', 'featured', 'featured_order'];

    protected $casts = ['images' => 'array', 'features' => 'array', 'active' => 'boolean', 'featured' => 'boolean', 'price' => 'decimal:2', 'old_price' => 'decimal:2', 'rating' => 'float', 'reviews_count' => 'integer'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
