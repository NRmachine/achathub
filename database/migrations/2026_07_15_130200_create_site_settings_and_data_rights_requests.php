<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text');
            $table->string('group')->default('general')->index();
            $table->timestamps();
        });

        Schema::create('data_rights_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('message')->nullable();
            $table->string('status')->default('Nouvelle')->index();
            $table->text('admin_response')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('site_settings')->insert([
            ['key' => 'hero_title', 'value' => 'Tout acheter, au meilleur prix.', 'type' => 'text', 'group' => 'boutique', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'hero_text', 'value' => 'Produits utiles, pièces détachées et offres professionnelles sélectionnés par AchatHub.', 'type' => 'textarea', 'group' => 'boutique', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'support_email', 'value' => 'contact@achathub.fr', 'type' => 'email', 'group' => 'contact', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_name', 'value' => 'AchatHub', 'type' => 'text', 'group' => 'legal', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'legal_name', 'value' => 'À compléter dans l’administration', 'type' => 'text', 'group' => 'legal', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_address', 'value' => 'À compléter dans l’administration', 'type' => 'textarea', 'group' => 'legal', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_siret', 'value' => 'À compléter dans l’administration', 'type' => 'text', 'group' => 'legal', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'publication_director', 'value' => 'À compléter dans l’administration', 'type' => 'text', 'group' => 'legal', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_rights_requests');
        Schema::dropIfExists('site_settings');
    }
};
