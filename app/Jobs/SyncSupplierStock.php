<?php

namespace App\Jobs;

use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSupplierStock implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('supplier');
    }

    public function handle(SupplierSyncService $service): void
    {
        $service->syncStock();
    }
}
