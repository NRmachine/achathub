<?php

namespace App\Console\Commands;

use App\Services\Suppliers\SupplierCategoryService;
use Illuminate\Console\Command;

class CategorizeSupplierProducts extends Command
{
    protected $signature = 'supplier:categorize';

    protected $description = 'Crée les catégories AchatHub et classe les produits du fournisseur';

    public function handle(SupplierCategoryService $service): int
    {
        $run = $service->categorize();
        $this->info($run->message);

        return $run->status === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
