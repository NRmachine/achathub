<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierCatalogService;
use Illuminate\Console\Command;

class CrawlSupplierCatalog extends Command
{
    protected $signature = 'supplier:crawl-catalog
        {--path= : Chemin exact séparé par >, par exemple Pièces Détachées > Apple > iPhone > iPhone 11}
        {--nodes=1 : Nombre de chemins à traiter}
        {--pages=1 : Nombre de pages par chemin}
        {--products=100 : Nombre maximal de fiches par chemin}';

    protected $description = 'Parcourt les produits LCD Phone en respectant leur chemin de catégorie exact';

    public function handle(SupplierCatalogService $service): int
    {
        $run = $service->crawl(
            $this->option('path') ?: null,
            max(1, (int) $this->option('nodes')),
            max(1, (int) $this->option('pages')),
            max(1, (int) $this->option('products')),
        );
        $this->info("{$run->message} {$run->products_seen} fiche(s), {$run->variants_seen} variante(s).");

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
