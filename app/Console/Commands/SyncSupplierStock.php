<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Console\Command;

class SyncSupplierStock extends Command
{
    protected $signature = 'supplier:sync-stock';

    protected $description = 'Contrôle les stocks des produits fournisseur déjà découverts';

    public function handle(SupplierSyncService $service): int
    {
        $run = $service->syncStock();
        $this->info("Stock {$run->status}: {$run->variants_seen} variantes contrôlées, {$run->updated_count} changement(s).");

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
