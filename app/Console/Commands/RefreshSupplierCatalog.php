<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierCatalogService;
use Illuminate\Console\Command;

class RefreshSupplierCatalog extends Command
{
    protected $signature = 'supplier:catalog-tree {--url= : Page LCD Phone utilisée pour lire l’arborescence}';

    protected $description = 'Synchronise l’arborescence exacte des catégories LCD Phone';

    public function handle(SupplierCatalogService $service): int
    {
        $run = $service->refreshTree($this->option('url') ?: null);
        $this->info($run->message);

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
