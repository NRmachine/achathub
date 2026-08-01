<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('blocked');
            $table->timestamp('last_admin_update_at')->nullable()->after('admin_notes');
            $table->timestamp('terms_accepted_at')->nullable()->after('last_admin_update_at');
            $table->string('privacy_version')->nullable()->after('terms_accepted_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->index()->after('active');
            $table->unsignedSmallInteger('featured_order')->default(0)->after('featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['featured', 'featured_order']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['admin_notes', 'last_admin_update_at', 'terms_accepted_at', 'privacy_version']));
    }
};
