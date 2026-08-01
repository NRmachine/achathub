<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('professional_preorders')->where('status', 'Acceptée')->update(['status' => 'Validée']);
    }

    public function down(): void
    {
        DB::table('professional_preorders')->where('status', 'Validée')->update(['status' => 'Acceptée']);
    }
};
