<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Console\Command;

class DiscoverSupplierProducts extends Command
{
    protected $signature = 'supplier:discover {--url=* : URL de catégorie LCD Phone} {--pages=1 : Nombre maximal de pages} {--limit=25 : Nombre maximal de fiches produit}';

    protected $description = 'Découvre les produits et variantes du catalogue fournisseur LCD Phone';

    public function handle(SupplierSyncService $service): int
    {
        $urls = array_values(array_filter($this->option('url'))) ?: config('suppliers.lcd_phone.category_urls', []);
        $run = $service->discover($urls, max(1, (int) $this->option('pages')), max(1, (int) $this->option('limit')));
        $this->info("Découverte {$run->status}: {$run->products_seen} fiches, {$run->variants_seen} variantes, {$run->error_count} erreur(s).");

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
