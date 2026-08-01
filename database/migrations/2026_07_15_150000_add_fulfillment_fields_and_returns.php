<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('guest_email')->nullable()->after('user_id');
            $table->string('access_token', 64)->nullable()->unique()->after('number');
            $table->string('shipping_postal_code', 10)->nullable()->after('shipping_city');
            $table->string('shipping_method')->default('standard')->after('shipping');
            $table->date('estimated_delivery_date')->nullable()->after('shipping_method');
            $table->string('carrier')->nullable()->after('estimated_delivery_date');
            $table->string('tracking_number')->nullable()->after('carrier');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });

        Schema::create('order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('message')->nullable();
            $table->timestamp('happened_at');
            $table->timestamps();
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->string('solution');
            $table->string('return_method');
            $table->string('status')->default('Demandé')->index();
            $table->text('details')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_request_items');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('order_status_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['guest_email', 'access_token', 'shipping_postal_code', 'shipping_method', 'estimated_delivery_date', 'carrier', 'tracking_number', 'shipped_at', 'delivered_at']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
