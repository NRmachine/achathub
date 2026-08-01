<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('ean');
            $table->string('source_category')->nullable()->after('brand');
            $table->text('description')->nullable()->after('variant_name');
            $table->json('images')->nullable()->after('image');
            $table->unsignedInteger('minimum_order_quantity')->default(1)->after('purchase_price');
            $table->unsignedInteger('stock_divisor')->default(1)->after('minimum_order_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn([
                'brand',
                'source_category',
                'description',
                'images',
                'minimum_order_quantity',
                'stock_divisor',
            ]);
        });
    }
};
