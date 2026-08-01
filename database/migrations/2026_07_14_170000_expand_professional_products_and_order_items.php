<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professional_products', function (Blueprint $table) {
            $table->string('category')->default('Accessoires')->after('name')->index();
            $table->unsignedInteger('minimum_order_quantity')->default(5)->after('wholesale_price_ht');
            $table->unsignedInteger('stock')->default(100)->after('minimum_order_quantity');
            $table->text('description')->nullable()->after('image');
        });

        Schema::table('professional_order_items', function (Blueprint $table) {
            $table->foreignId('professional_product_id')->nullable()->after('professional_display_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('professional_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_product_id');
        });

        Schema::table('professional_products', function (Blueprint $table) {
            $table->dropColumn(['category', 'minimum_order_quantity', 'stock', 'description']);
        });
    }
};
