<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professional_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('reseller_request_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('Nouvelle');
            $table->string('payment_status')->default('En attente');
            $table->string('payment_method');
            $table->decimal('subtotal_ht', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total_ttc', 12, 2);
            $table->string('contact_name');
            $table->string('phone', 40);
            $table->string('address', 500);
            $table->string('city', 120);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('professional_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professional_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_display_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('price_ht', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('vat_rate', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_order_items');
        Schema::dropIfExists('professional_orders');
    }
};
