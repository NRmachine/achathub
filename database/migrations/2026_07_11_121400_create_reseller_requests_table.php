<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reseller_requests', function (Blueprint $table) {
            $table->id();
            $table->string('business_name');
            $table->string('manager_name');
            $table->string('city');
            $table->text('address');
            $table->string('phone');
            $table->string('email');
            $table->string('business_type');
            $table->string('formula');
            $table->string('display_type');
            $table->text('categories')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('Nouvelle')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reseller_requests');
    }
};
