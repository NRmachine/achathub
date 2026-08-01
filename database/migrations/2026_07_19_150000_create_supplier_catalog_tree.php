<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_name_unique');
            $table->index('name');
        });

        Schema::create('supplier_catalog_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('lcd_phone')->index();
            $table->string('supplier_category_id', 80);
            $table->foreignId('parent_id')->nullable()->constrained('supplier_catalog_nodes')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('node_type', 40)->default('category')->index();
            $table->unsignedSmallInteger('depth')->default(0)->index();
            $table->text('source_url');
            $table->json('path');
            $table->string('path_hash', 64)->index();
            $table->boolean('is_leaf')->default(false)->index();
            $table->string('crawl_status', 30)->default('pending')->index();
            $table->unsignedInteger('next_page')->default(1);
            $table->unsignedInteger('next_product_offset')->default(0);
            $table->unsignedInteger('max_page')->nullable();
            $table->unsignedInteger('products_seen')->default(0);
            $table->unsignedInteger('variants_seen')->default(0);
            $table->timestamp('last_discovered_at')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->text('last_error')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();

            $table->unique(['provider', 'supplier_category_id'], 'supplier_catalog_provider_category_unique');
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->foreignId('supplier_catalog_node_id')->nullable()->after('suggested_category_id')->constrained('supplier_catalog_nodes')->nullOnDelete();
            $table->json('supplier_path')->nullable()->after('source_category');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_catalog_node_id');
            $table->dropColumn('supplier_path');
        });

        Schema::dropIfExists('supplier_catalog_nodes');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_name_index');
            $table->unique('name');
        });
    }
};
