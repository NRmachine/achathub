<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalProduct;
use App\Models\SupplierSyncRun;
use App\Models\User;
use App\Services\Suppliers\SupplierCatalogService;
use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class VercelRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_runtime_secures_cookies_and_hides_the_php_version(): void
    {
        $sessionConfig = file_get_contents(base_path('config/session.php'));
        $dockerfile = file_get_contents(base_path('Dockerfile.vercel'));

        $this->assertStringContainsString(
            "'secure' => env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')",
            $sessionConfig,
        );
        $this->assertStringContainsString("'expose_php=Off'", $dockerfile);
    }

    public function test_production_catalog_can_be_bootstrapped_without_overwriting_later_changes(): void
    {
        $this->artisan('achathub:bootstrap-catalog')->assertSuccessful();

        $this->assertSame(30, Product::query()->count());
        $this->assertSame(47, ProfessionalProduct::query()->count());
        $this->assertSame(3, ProfessionalDisplay::query()->count());

        $product = Product::query()->firstOrFail();
        $product->update(['price' => 123.45]);

        $this->artisan('achathub:bootstrap-catalog')->assertSuccessful();

        $this->assertSame('123.45', $product->fresh()->price);
    }

    public function test_vercel_boot_migrates_catalogs_and_creates_the_initial_administrator_once(): void
    {
        $environment = Env::getRepository();
        $environment->set('VERCEL_RUN_MIGRATIONS', 'true');
        $environment->set('ACHATHUB_ADMIN_EMAIL', 'contact@example.com');
        $environment->set('ACHATHUB_ADMIN_PASSWORD', 'Initial-password-123!');

        try {
            $this->artisan('achathub:vercel-boot')->assertSuccessful();

            $administrator = User::query()->where('email', 'contact@example.com')->firstOrFail();
            $this->assertSame('admin', $administrator->role);
            $this->assertTrue($administrator->hasVerifiedEmail());
            $this->assertTrue(Hash::check('Initial-password-123!', $administrator->password));

            $administrator->update(['password' => 'Password-changed-later!']);
            $environment->set('ACHATHUB_ADMIN_PASSWORD', 'Another-environment-password!');

            $this->artisan('achathub:vercel-boot')->assertSuccessful();

            $this->assertTrue(Hash::check('Password-changed-later!', $administrator->fresh()->password));
        } finally {
            foreach (['VERCEL_RUN_MIGRATIONS', 'ACHATHUB_ADMIN_EMAIL', 'ACHATHUB_ADMIN_PASSWORD'] as $key) {
                $environment->clear($key);
            }
        }
    }

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
