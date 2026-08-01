<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCatalogNode extends Model
{
    protected $fillable = [
        'provider', 'supplier_category_id', 'parent_id', 'category_id', 'name', 'node_type',
        'depth', 'source_url', 'path', 'path_hash', 'is_leaf', 'crawl_status', 'next_page',
        'next_product_offset', 'max_page', 'products_seen', 'variants_seen', 'last_discovered_at', 'last_crawled_at',
        'last_error', 'active',
    ];

    protected $casts = [
        'path' => 'array',
        'depth' => 'integer',
        'is_leaf' => 'boolean',
        'next_page' => 'integer',
        'next_product_offset' => 'integer',
        'max_page' => 'integer',
        'products_seen' => 'integer',
        'variants_seen' => 'integer',
        'last_discovered_at' => 'datetime',
        'last_crawled_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplierProducts()
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function assignedSupplierProducts()
    {
        return $this->belongsToMany(
            SupplierProduct::class,
            'supplier_catalog_assignments',
            'supplier_catalog_node_id',
            'supplier_product_id',
        )->withTimestamps();
    }

    public function pathLabel(): string
    {
        return implode(' › ', $this->path ?: [$this->name]);
    }
}
