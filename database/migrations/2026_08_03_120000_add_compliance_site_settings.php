<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = [
        'company_legal_form',
        'company_capital',
        'company_siren',
        'company_rcs',
        'company_vat_number',
        'support_phone',
        'dpo_email',
        'returns_address',
        'host_name',
        'host_address',
        'host_phone',
        'host_website',
        'mediator_name',
        'mediator_address',
        'mediator_website',
    ];

    public function up(): void
    {
        $now = now();
        $missing = 'À renseigner avant l’ouverture commerciale';
        $settings = [
            ['key' => 'company_legal_form', 'value' => $missing, 'type' => 'text', 'group' => 'legal'],
            ['key' => 'company_capital', 'value' => $missing, 'type' => 'text', 'group' => 'legal'],
            ['key' => 'company_siren', 'value' => $missing, 'type' => 'text', 'group' => 'legal'],
            ['key' => 'company_rcs', 'value' => $missing, 'type' => 'text', 'group' => 'legal'],
            ['key' => 'company_vat_number', 'value' => $missing, 'type' => 'text', 'group' => 'legal'],
            ['key' => 'support_phone', 'value' => $missing, 'type' => 'text', 'group' => 'contact'],
            ['key' => 'dpo_email', 'value' => 'contact@achathub.fr', 'type' => 'email', 'group' => 'privacy'],
            ['key' => 'returns_address', 'value' => $missing, 'type' => 'textarea', 'group' => 'legal'],
            ['key' => 'host_name', 'value' => 'Vercel — coordonnées contractuelles à confirmer', 'type' => 'text', 'group' => 'hosting'],
            ['key' => 'host_address', 'value' => $missing, 'type' => 'textarea', 'group' => 'hosting'],
            ['key' => 'host_phone', 'value' => $missing, 'type' => 'text', 'group' => 'hosting'],
            ['key' => 'host_website', 'value' => 'https://vercel.com', 'type' => 'url', 'group' => 'hosting'],
            ['key' => 'mediator_name', 'value' => $missing, 'type' => 'text', 'group' => 'mediation'],
            ['key' => 'mediator_address', 'value' => $missing, 'type' => 'textarea', 'group' => 'mediation'],
            ['key' => 'mediator_website', 'value' => $missing, 'type' => 'url', 'group' => 'mediation'],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [...$setting, 'updated_at' => $now, 'created_at' => $now],
            );
        }
    }

    public function down(): void
    {
        DB::table('site_settings')->whereIn('key', self::KEYS)->delete();
    }
};
