<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reseller_requests', function (Blueprint $table) {
            $table->string('company_type')->nullable()->after('business_name');
            $table->string('legal_form')->nullable()->after('company_type');
            $table->string('commercial_name')->nullable()->after('legal_form');
            $table->string('siren', 9)->nullable()->unique()->after('commercial_name');
            $table->string('siret', 14)->nullable()->unique()->after('siren');
            $table->string('vat_number')->nullable()->after('siret');
            $table->string('postal_code', 5)->nullable()->after('address');
            $table->string('activity')->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('reseller_requests', function (Blueprint $table) {
            $table->dropUnique(['siren']);
            $table->dropUnique(['siret']);
            $table->dropColumn(['company_type', 'legal_form', 'commercial_name', 'siren', 'siret', 'vat_number', 'postal_code', 'activity']);
        });
    }
};
