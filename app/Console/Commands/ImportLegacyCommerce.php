<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Signature('achathub:import {--fresh : Supprime le catalogue existant}')]
#[Description('Importe le catalogue AchatHub depuis les données historiques')]
class ImportLegacyCommerce extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = storage_path('app/commerce-legacy.json');
        if (! is_file($path)) {
            $this->error('Fichier introuvable: '.$path);

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if ($this->option('fresh')) {
            Product::query()->delete();
            Category::query()->delete();
        }

        $categoryIds = [];
        foreach (collect($data['products'])->pluck('category')->filter()->unique() as $name) {
            $categoryIds[$name] = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => 'Découvrez les produits '.$name.' sélectionnés par AchatHub.'],
            )->id;
        }

        $bar = $this->output->createProgressBar(count($data['products']));
        foreach ($data['products'] as $item) {
            $sku = (string) ($item['sku'] ?? $item['id']);
            Product::updateOrCreate(['sku' => $sku], [
                'category_id' => $categoryIds[$item['category']],
                'legacy_id' => (string) $item['id'],
                'name' => $item['name'],
                'slug' => Str::slug($item['name'].'-'.$sku),
                'brand' => $item['brand'] ?? null,
                'model' => $item['model'] ?? null,
                'family' => $item['family'] ?? null,
                'subcategory' => $item['subcategory'] ?? null,
                'price' => $item['price'],
                'old_price' => $item['oldPrice'] ?? null,
                'discount' => $item['discount'] ?? 0,
                'stock' => $item['stock'] ?? 0,
                'rating' => $item['rating'] ?? 0,
                'reviews_count' => $item['reviews'] ?? 0,
                'condition' => $item['condition'] ?? 'Neuf',
                'tag' => $item['tag'] ?? null,
                'image' => $item['image'] ?? null,
                'images' => $item['images'] ?? [],
                'description' => $item['description'] ?? null,
                'features' => $item['features'] ?? [],
                'active' => true,
            ]);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        foreach ($data['customers'] ?? [] as $customer) {
            User::updateOrCreate(['email' => strtolower($customer['email'])], [
                'name' => $customer['name'],
                'phone' => $customer['phone'] ?? null,
                'address' => $customer['address'] ?? null,
                'provider' => $customer['provider'] ?? 'email',
                'blocked' => $customer['blocked'] ?? false,
                'password' => Hash::make(Str::random(32)),
                'role' => 'customer',
            ]);
        }

        foreach ($data['supportMessages'] ?? [] as $message) {
            SupportMessage::firstOrCreate(
                ['email' => $message['email'], 'message' => $message['message']],
                ['name' => $message['name'], 'subject' => 'Demande importée', 'status' => ($message['status'] ?? '') === 'Traite' ? 'Traité' : 'Nouveau'],
            );
        }

        $adminEmail = strtolower(trim((string) env('ACHATHUB_ADMIN_EMAIL')));
        $adminPassword = (string) env('ACHATHUB_ADMIN_PASSWORD');
        if ($adminEmail !== '' && $adminPassword !== '') {
            User::updateOrCreate(['email' => $adminEmail], [
                'name' => 'Administration AchatHub',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'provider' => 'email',
            ]);
            $this->info(Product::count().' produits importés. Compte administrateur configuré depuis l’environnement.');
        } else {
            $this->warn(Product::count().' produits importés. Aucun compte administrateur créé : configurez ACHATHUB_ADMIN_EMAIL et ACHATHUB_ADMIN_PASSWORD.');
        }

        return self::SUCCESS;
    }
}
