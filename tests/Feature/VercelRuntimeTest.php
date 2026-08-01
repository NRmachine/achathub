<?php

namespace Tests\Feature;

use App\Models\SupplierSyncRun;
use App\Services\Suppliers\SupplierCatalogService;
use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VercelRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_cron_rejects_requests_without_the_vercel_secret(): void
    {
        config(['services.vercel.cron_secret' => 'cron-secret-test']);

        $this->getJson(route('internal.cron.supplier'))
            ->assertUnauthorized()
            ->assertJson(['ok' => false]);
    }

    public function test_supplier_cron_can_initialize_the_catalog_with_the_vercel_secret(): void
    {
        config(['services.vercel.cron_secret' => 'cron-secret-test']);
        $run = SupplierSyncRun::create([
            'provider' => 'lcd_phone',
            'mode' => 'catalog_tree',
            'status' => 'success',
            'products_seen' => 12,
            'variants_seen' => 4,
            'error_count' => 0,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $catalog = Mockery::mock(SupplierCatalogService::class);
        $catalog->shouldReceive('refreshTree')->once()->andReturn($run);
        $stock = Mockery::mock(SupplierSyncService::class);
        $stock->shouldNotReceive('syncStock');
        $this->app->instance(SupplierCatalogService::class, $catalog);
        $this->app->instance(SupplierSyncService::class, $stock);

        $this->withHeader('Authorization', 'Bearer cron-secret-test')
            ->getJson(route('internal.cron.supplier'))
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'catalog_run' => ['id' => $run->id, 'status' => 'success'],
                'stock_run' => null,
            ]);
    }
}
