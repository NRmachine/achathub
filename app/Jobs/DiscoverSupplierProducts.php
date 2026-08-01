<?php

namespace App\Jobs;

use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DiscoverSupplierProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public array $urls, public int $pages = 1, public int $limit = 25)
    {
        $this->onQueue('supplier');
    }

    public function handle(SupplierSyncService $service): void
    {
        $service->discover($this->urls, $this->pages, $this->limit);
    }
}
