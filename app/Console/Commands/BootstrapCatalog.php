<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalProduct;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[Signature('achathub:bootstrap-catalog {--force : Réimporte les catalogues même s’ils existent déjà}')]
#[Description('Initialise les catalogues public et professionnel livrés avec AchatHub')]
class BootstrapCatalog extends Command
{
    public function handle(): int
    {
        try {
            $this->importPublicCatalog();
            $this->importProfessionalCatalog();

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function importPublicCatalog(): void
    {
        if (! $this->option('force') && Product::query()->exists()) {
            $this->components->info('Catalogue public déjà initialisé.');

            return;
        }

        $path = database_path('data/public-catalog.json');
        if (! is_file($path)) {
            throw new RuntimeException("Catalogue public introuvable : {$path}");
        }

        $catalog = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($catalog): void {
            $categoryIds = [];
            foreach ($catalog['categories'] ?? [] as $category) {
                $sourceId = (int) $category['id'];
                unset($category['id'], $category['parent_id']);
                $model = Category::query()->updateOrCreate(['slug' => $category['slug']], $category);
                $categoryIds[$sourceId] = $model->id;
            }

            foreach ($catalog['products'] ?? [] as $product) {
                $sourceCategoryId = (int) $product['category_id'];
                unset($product['category_id']);
                foreach (['images', 'features'] as $column) {
                    if (is_string($product[$column] ?? null)) {
                        $product[$column] = json_decode($product[$column], true) ?: [];
                    }
                }

                Product::query()->updateOrCreate(['sku' => $product['sku']], $product + [
                    'category_id' => $categoryIds[$sourceCategoryId]
                        ?? throw new RuntimeException("Catégorie source inconnue : {$sourceCategoryId}"),
                ]);
            }
        });

        $this->components->info(Product::query()->count().' produits publics initialisés.');
    }

    private function importProfessionalCatalog(): void
    {
        if (! $this->option('force')
            && ProfessionalProduct::query()->exists()
            && ProfessionalDisplay::query()->exists()) {
            $this->components->info('Catalogue professionnel déjà initialisé.');

            return;
        }

        $exitCode = Artisan::call('achathub:import-displays');
        $this->output->write(Artisan::output());

        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException('Le catalogue professionnel n’a pas pu être initialisé.');
        }
    }
}
