<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('lcd_phone')->index();
            $table->string('mode', 30);
            $table->string('status', 30)->default('running')->index();
            $table->unsignedInteger('pages_scanned')->default(0);
            $table->unsignedInteger('products_seen')->default(0);
            $table->unsignedInteger('variants_seen')->default(0);
            $table->unsignedInteger('mapped_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('out_of_stock_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_products', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('lcd_phone')->index();
            $table->string('supplier_product_id', 80);
            $table->string('supplier_variant_id', 80)->default('0');
            $table->string('supplier_reference')->nullable()->index();
            $table->string('ean', 32)->nullable()->index();
            $table->string('name');
            $table->string('variant_name')->nullable();
            $table->text('supplier_url');
            $table->text('image')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->unsignedInteger('supplier_stock')->default(0);
            $table->boolean('available')->default(false)->index();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('suggested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('match_method', 40)->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->boolean('sync_stock')->default(false)->index();
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'supplier_product_id', 'supplier_variant_id'], 'supplier_variant_unique');
        });

        Schema::create('supplier_stock_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained('supplier_products')->cascadeOnDelete();
            $table->unsignedInteger('old_stock');
            $table->unsignedInteger('new_stock');
            $table->integer('difference');
            $table->timestamp('observed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_stock_changes');
        Schema::dropIfExists('supplier_products');
        Schema::dropIfExists('supplier_sync_runs');
    }
};
