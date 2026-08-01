<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_displays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('wholesale_price_ht', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(20);
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('professional_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('wholesale_price_ht', 10, 2);
            $table->string('image')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('professional_display_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_display_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_ht', 10, 2);
            $table->timestamps();
            $table->unique(['professional_display_id', 'professional_product_id'], 'display_product_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_display_items');
        Schema::dropIfExists('professional_products');
        Schema::dropIfExists('professional_displays');
    }
};
