<?php

namespace App\Jobs;

use App\Services\Suppliers\SupplierCatalogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshSupplierCatalog implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 4;

    public array $backoff = [60, 300, 1200];

    public function __construct(public ?string $sourceUrl = null)
    {
        $this->onQueue('supplier');
    }

    public function handle(SupplierCatalogService $service): void
    {
        $service->refreshTree($this->sourceUrl);
    }
}
