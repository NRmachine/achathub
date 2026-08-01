<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierProduct extends Model
{
    protected $fillable = [
        'provider', 'supplier_product_id', 'supplier_variant_id', 'supplier_reference', 'ean',
        'brand', 'source_category', 'supplier_path', 'name', 'variant_name', 'description', 'supplier_url',
        'image', 'images', 'purchase_price', 'minimum_order_quantity', 'stock_divisor', 'supplier_stock',
        'available', 'product_id', 'suggested_product_id', 'match_method', 'match_score',
        'suggested_category_id', 'supplier_catalog_node_id', 'sync_stock', 'active', 'last_seen_at', 'last_synced_at', 'last_error',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'images' => 'array',
        'supplier_path' => 'array',
        'minimum_order_quantity' => 'integer',
        'stock_divisor' => 'integer',
        'supplier_stock' => 'integer',
        'available' => 'boolean',
        'sync_stock' => 'boolean',
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function suggestedProduct()
    {
        return $this->belongsTo(Product::class, 'suggested_product_id');
    }

    public function suggestedCategory()
    {
        return $this->belongsTo(Category::class, 'suggested_category_id');
    }

    public function catalogNode()
    {
        return $this->belongsTo(SupplierCatalogNode::class, 'supplier_catalog_node_id');
    }

    public function catalogNodes()
    {
        return $this->belongsToMany(
            SupplierCatalogNode::class,
            'supplier_catalog_assignments',
            'supplier_product_id',
            'supplier_catalog_node_id',
        )->withTimestamps();
    }

    public function stockChanges()
    {
        return $this->hasMany(SupplierStockChange::class);
    }

    public function sellableStock(): int
    {
        return intdiv($this->supplier_stock, max(1, $this->stock_divisor));
    }

    public function acquisitionCost(): float
    {
        return round((float) $this->purchase_price * max(1, $this->stock_divisor), 2);
    }
}
