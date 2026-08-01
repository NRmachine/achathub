<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_catalog_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_product_id')->constrained('supplier_products')->cascadeOnDelete();
            $table->foreignId('supplier_catalog_node_id')->constrained('supplier_catalog_nodes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['supplier_product_id', 'supplier_catalog_node_id'], 'supplier_catalog_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_catalog_assignments');
    }
};
