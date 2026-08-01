<?php

namespace App\Jobs;

use App\Services\Suppliers\SupplierCategoryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CategorizeSupplierProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('supplier');
    }

    public function handle(SupplierCategoryService $service): void
    {
        $service->categorize();
    }
}
