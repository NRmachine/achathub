<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierCatalogNode;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Suppliers\LcdPhoneClient;
use App\Services\Suppliers\SupplierCatalogService;
use App\Services\Suppliers\SupplierCategoryService;
use App\Services\Suppliers\SupplierSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupplierAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_lcd_phone_parser_extracts_each_variant_and_its_stock(): void
    {
        $html = <<<'HTML'
        <html><head><meta name="description" content="Protection HD résistante et facile à poser."></head><body><main><h1>Verre trempé HD iPhone</h1>
        <input type="hidden" name="id_product" value="316">
        <div class="product-manufacturer"><span>Mayline</span></div>
        <ul id="js_mfp_gallery"><li data-mfp-src="https://lcd-phone.com/gallery-1.jpg"></li><li data-mfp-src="https://lcd-phone.com/gallery-2.jpg"></li></ul>
        <p>EAN-13 : 6169957565407</p>
        <table class="table_d_c">
          <tr><td><img data-zoom-image="https://lcd-phone.com/a.jpg"></td><td>Apple iPhone 13</td><td>IP-F0013</td><td><span class="availability_value">En stock</span><p class="stock">Stock : 8850</p></td><td class="td_price"><span class="product-price">0,12 € TTC</span></td><td><input type="hidden" name="id_product" value="316"><input type="hidden" name="id_product_attribute" value="87"><input type="text" name="qty" value="50" min="50"></td></tr>
          <tr><td><img src="https://lcd-phone.com/b.jpg"></td><td>Apple iPhone 14</td><td>IP-F0014</td><td><span>Rupture de stock</span></td><td class="td_price"><span class="product-price">0,15 € TTC</span></td><td><input type="hidden" name="id_product" value="316"><input type="hidden" name="id_product_attribute" value="88"></td></tr>
        </table></main></body></html>
        HTML;

        $result = (new LcdPhoneClient)->parseProductPageHtml($html, 'https://lcd-phone.com/fr/protections/316-87-verre.html');

        $this->assertCount(2, $result['variants']);
        $this->assertSame('IP-F0013', $result['variants'][0]['supplier_reference']);
        $this->assertSame(8850, $result['variants'][0]['supplier_stock']);
        $this->assertSame(0.12, $result['variants'][0]['purchase_price']);
        $this->assertSame(50, $result['variants'][0]['minimum_order_quantity']);
        $this->assertSame('Mayline', $result['variants'][0]['brand']);
        $this->assertSame('Protections', $result['variants'][0]['source_category']);
        $this->assertSame('Protection HD résistante et facile à poser.', $result['variants'][0]['description']);
        $this->assertCount(3, $result['variants'][0]['images']);
        $this->assertTrue($result['variants'][0]['available']);
        $this->assertSame(0, $result['variants'][1]['supplier_stock']);
        $this->assertFalse($result['variants'][1]['available']);
    }

    public function test_lcd_phone_parser_reads_the_exact_nested_category_tree(): void
    {
        $html = <<<'HTML'
        <div class="category-tree js-category-tree"><ul>
          <li data-depth="0"><a data-category-id="735" title="Pièces Détachées" href="https://lcd-phone.com/fr/735-pieces-detachees"><span>Pièces Détachées</span></a>
            <div class="category-sub-menu collapse"><ul><li data-depth="1"><a data-category-id="12" title="Apple" href="/fr/12-apple"><span>Apple</span></a>
              <div class="category-sub-menu collapse"><ul><li data-depth="2"><a data-category-id="25" title="iPhone" href="/fr/25-iphone"><span>iPhone</span></a>
                <div class="category-sub-menu collapse"><ul>
                  <li data-depth="3"><a data-category-id="651" title="iPhone 11" href="/fr/651-iphone-11"><span>iPhone 11</span></a></li>
                  <li data-depth="3"><a data-category-id="652" title="iPhone 11 Pro" href="/fr/652-iphone-11-pro"><span>iPhone 11 Pro</span></a></li>
                </ul></div>
              </li></ul></div>
            </li></ul></div>
          </li>
        </ul></div>
        HTML;

        $tree = (new LcdPhoneClient)->parseCatalogTreeHtml($html);
        $iphone11 = $tree[0]['children'][0]['children'][0]['children'][0];

        $this->assertSame('735', $tree[0]['supplier_category_id']);
        $this->assertSame(['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11'], $iphone11['path']);
        $this->assertSame('https://lcd-phone.com/fr/651-iphone-11', $iphone11['url']);
        $this->assertTrue($iphone11['is_leaf']);
    }

    public function test_lcd_phone_category_parser_ignores_global_promotional_products(): void
    {
        $html = <<<'HTML'
        <html><head><link rel="next" href="https://lcd-phone.com/fr/3600-mi-scooter-pro-2?page=2"></head><body>
          <section class="featured-products">
            <article class="product-miniature" data-id-product="316">
              <h5 class="product-name"><a href="/fr/protections/316-verre.html">Verre trempé promotionnel</a></h5>
            </article>
          </section>
          <main><div id="js-product-list">
            <div class="products product-list-wrapper">
              <article class="product-container product-style">
                <a class="product-thumbnail" href="/fr/pieces-detachees/38184-poignee-frein.html"><img src="/38184-home_default/poignee.jpg"></a>
                <h5 class="product-name"><a href="/fr/pieces-detachees/38184-poignee-frein.html" title="Poignée de frein Xiaomi">Poignée de frein Xiaomi</a></h5>
                <form><input name="id_product" value="38184"></form>
              </article>
            </div>
          </div></main>
        </body></html>
        HTML;

        $result = (new LcdPhoneClient)->parseDiscoverPageHtml(
            $html,
            'https://lcd-phone.com/fr/3600-mi-scooter-pro-2',
        );

        $this->assertCount(1, $result['products']);
        $this->assertSame('38184', $result['products'][0]['supplier_product_id']);
        $this->assertSame('Poignée de frein Xiaomi', $result['products'][0]['name']);
        $this->assertSame(2, $result['max_page']);
    }

    public function test_catalog_agent_keeps_shared_product_variants_in_their_exact_model_paths(): void
    {
        $tree = [[
            'supplier_category_id' => '735', 'name' => 'Pièces Détachées', 'url' => 'https://lcd-phone.com/fr/735-pieces-detachees',
            'depth' => 0, 'path' => ['Pièces Détachées'], 'is_leaf' => false, 'children' => [[
                'supplier_category_id' => '12', 'name' => 'Apple', 'url' => 'https://lcd-phone.com/fr/12-apple',
                'depth' => 1, 'path' => ['Pièces Détachées', 'Apple'], 'is_leaf' => false, 'children' => [[
                    'supplier_category_id' => '25', 'name' => 'iPhone', 'url' => 'https://lcd-phone.com/fr/25-iphone',
                    'depth' => 2, 'path' => ['Pièces Détachées', 'Apple', 'iPhone'], 'is_leaf' => false, 'children' => [[
                        'supplier_category_id' => '651', 'name' => 'iPhone 11', 'url' => 'https://lcd-phone.com/fr/651-iphone-11',
                        'depth' => 3, 'path' => ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11'], 'is_leaf' => true, 'children' => [],
                    ], [
                        'supplier_category_id' => '652', 'name' => 'iPhone 11 Pro', 'url' => 'https://lcd-phone.com/fr/652-iphone-11-pro',
                        'depth' => 3, 'path' => ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11 Pro'], 'is_leaf' => true, 'children' => [],
                    ], [
                        'supplier_category_id' => '653', 'name' => 'iPhone 11 Pro Max', 'url' => 'https://lcd-phone.com/fr/653-iphone-11-pro-max',
                        'depth' => 3, 'path' => ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11 Pro Max'], 'is_leaf' => true, 'children' => [],
                    ], [
                        'supplier_category_id' => '55', 'name' => 'iPhone 6', 'url' => 'https://lcd-phone.com/fr/55-iphone-6',
                        'depth' => 3, 'path' => ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 6'], 'is_leaf' => true, 'children' => [],
                    ], [
                        'supplier_category_id' => '1218', 'name' => 'iPhone 13', 'url' => 'https://lcd-phone.com/fr/1218-iphone-13',
                        'depth' => 3, 'path' => ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 13'], 'is_leaf' => true, 'children' => [],
                    ]],
                ]],
            ]],
        ]];
        $client = Mockery::mock(LcdPhoneClient::class);
        $client->shouldReceive('catalogTree')->once()->andReturn($tree);
        $client->shouldReceive('discoverPage')->once()->with('https://lcd-phone.com/fr/651-iphone-11', 1)->andReturn([
            'products' => [['supplier_product_id' => '316', 'name' => 'Verre trempé multi-modèles', 'url' => 'https://lcd-phone.com/fr/protections/316-verre.html', 'image' => null]],
            'max_page' => 1,
        ]);
        $client->shouldReceive('product')->once()->andReturn(['variants' => [[
            'supplier_product_id' => '316', 'supplier_variant_id' => '87', 'supplier_reference' => 'IP-11',
            'brand' => 'Mayline', 'source_category' => 'Protections', 'name' => 'Verre trempé multi-modèles',
            'variant_name' => 'Apple : iP XR/11 (6,1")', 'supplier_url' => 'https://lcd-phone.com/fr/protections/316-verre.html',
            'image' => null, 'images' => [], 'purchase_price' => 0.12, 'minimum_order_quantity' => 50,
            'supplier_stock' => 100, 'available' => true,
        ], [
            'supplier_product_id' => '316', 'supplier_variant_id' => '88', 'supplier_reference' => 'IP-13',
            'brand' => 'Mayline', 'source_category' => 'Protections', 'name' => 'Verre trempé multi-modèles',
            'variant_name' => 'Apple iPhone 13', 'supplier_url' => 'https://lcd-phone.com/fr/protections/316-verre.html',
            'image' => null, 'images' => [], 'purchase_price' => 0.12, 'minimum_order_quantity' => 50,
            'supplier_stock' => 100, 'available' => true,
        ], [
            'supplier_product_id' => '316', 'supplier_variant_id' => '89', 'supplier_reference' => 'IP-11-PRO',
            'brand' => 'Mayline', 'source_category' => 'Protections', 'name' => 'Verre trempé multi-modèles',
            'variant_name' => 'Apple : iP X/XS/11 Pro (5,8")', 'supplier_url' => 'https://lcd-phone.com/fr/protections/316-verre.html',
            'image' => null, 'images' => [], 'purchase_price' => 0.12, 'minimum_order_quantity' => 50,
            'supplier_stock' => 100, 'available' => true,
        ], [
            'supplier_product_id' => '316', 'supplier_variant_id' => '90', 'supplier_reference' => 'IP-11-PRO-MAX',
            'brand' => 'Mayline', 'source_category' => 'Protections', 'name' => 'Verre trempé multi-modèles',
            'variant_name' => 'Apple : iP XS Max/11 Pro Max (6,5")', 'supplier_url' => 'https://lcd-phone.com/fr/protections/316-verre.html',
            'image' => null, 'images' => [], 'purchase_price' => 0.12, 'minimum_order_quantity' => 50,
            'supplier_stock' => 100, 'available' => true,
        ]]]);
        $syncService = new SupplierSyncService($client);
        $service = new SupplierCatalogService($client, $syncService);

        $service->refreshTree();
        $run = $service->crawl(null, 1, 2, 100);

        $this->assertSame('success', $run->status);
        $this->assertSame(8, SupplierCatalogNode::count());
        $iphone11Variant = SupplierProduct::where('supplier_variant_id', '87')->firstOrFail();
        $this->assertSame(
            ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11'],
            $iphone11Variant->supplier_path,
        );
        $this->assertFalse($iphone11Variant->catalogNodes()->where('supplier_category_id', '55')->exists());
        $this->assertSame(
            ['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 13'],
            SupplierProduct::where('supplier_variant_id', '88')->firstOrFail()->supplier_path,
        );
        $proVariant = SupplierProduct::where('supplier_variant_id', '89')->firstOrFail();
        $this->assertSame(['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11 Pro'], $proVariant->supplier_path);
        $this->assertFalse($proVariant->catalogNodes()->where('supplier_category_id', '651')->exists());
        $proMaxVariant = SupplierProduct::where('supplier_variant_id', '90')->firstOrFail();
        $this->assertSame(['Pièces Détachées', 'Apple', 'iPhone', 'iPhone 11 Pro Max'], $proMaxVariant->supplier_path);
        $this->assertFalse($proMaxVariant->catalogNodes()->where('supplier_category_id', '652')->exists());
        $this->assertSame('complete', SupplierCatalogNode::where('supplier_category_id', '651')->value('crawl_status'));
        $this->assertDatabaseHas('categories', ['name' => 'iPhone 11', 'supplier_managed' => true]);
    }

    public function test_stock_sync_updates_only_an_explicitly_linked_product(): void
    {
        $category = Category::create(['name' => 'Protection', 'slug' => 'protection']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'IP-F0013', 'name' => 'Verre iPhone 13', 'slug' => 'verre-iphone-13', 'price' => 9.90, 'stock' => 12]);
        $supplierProduct = SupplierProduct::create([
            'supplier_product_id' => '316', 'supplier_variant_id' => '87', 'supplier_reference' => 'IP-F0013',
            'name' => 'Verre trempé HD', 'variant_name' => 'Apple iPhone 13',
            'supplier_url' => 'https://lcd-phone.com/fr/protections/316-verre.html', 'supplier_stock' => 12,
            'available' => true, 'product_id' => $product->id, 'sync_stock' => true, 'stock_divisor' => 10, 'active' => true,
        ]);
        $client = Mockery::mock(LcdPhoneClient::class);
        $client->shouldReceive('product')->once()->andReturn([
            'variants' => [[
                'supplier_product_id' => '316', 'supplier_variant_id' => '87', 'supplier_reference' => 'IP-F0013',
                'name' => 'Verre trempé HD', 'variant_name' => 'Apple iPhone 13',
                'supplier_url' => $supplierProduct->supplier_url, 'image' => null, 'purchase_price' => 0.12,
                'supplier_stock' => 35, 'available' => true,
            ]],
        ]);

        $run = (new SupplierSyncService($client))->syncStock();

        $this->assertSame('success', $run->status);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertDatabaseHas('supplier_stock_changes', ['supplier_product_id' => $supplierProduct->id, 'old_stock' => 12, 'new_stock' => 35, 'difference' => 23]);
    }

    public function test_unlinked_supplier_stock_never_changes_the_store_catalog(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'LOCAL-1', 'name' => 'Câble local', 'slug' => 'cable-local', 'price' => 12, 'stock' => 7]);
        SupplierProduct::create([
            'supplier_product_id' => '20', 'supplier_variant_id' => '2', 'supplier_reference' => 'OTHER-2',
            'name' => 'Autre câble', 'supplier_url' => 'https://lcd-phone.com/fr/accessoires/20-cable.html',
            'supplier_stock' => 1, 'available' => true, 'sync_stock' => false, 'active' => true,
        ]);
        $client = Mockery::mock(LcdPhoneClient::class);
        $client->shouldReceive('product')->once()->andReturn(['variants' => [[
            'supplier_product_id' => '20', 'supplier_variant_id' => '2', 'supplier_reference' => 'OTHER-2',
            'name' => 'Autre câble', 'variant_name' => null, 'supplier_url' => 'https://lcd-phone.com/fr/accessoires/20-cable.html',
            'image' => null, 'purchase_price' => 2, 'supplier_stock' => 99, 'available' => true,
        ]]]);

        (new SupplierSyncService($client))->syncStock();

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_admin_can_map_a_supplier_reference_to_a_store_sku(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'AH-CABLE-1', 'name' => 'Câble USB-C', 'slug' => 'cable-usb-c-ah', 'price' => 14.90, 'stock' => 4]);
        $supplierProduct = SupplierProduct::create([
            'supplier_product_id' => '22', 'supplier_variant_id' => '5', 'supplier_reference' => 'FOURN-5',
            'name' => 'Câble USB-C', 'supplier_url' => 'https://lcd-phone.com/fr/accessoires/22-cable.html',
            'supplier_stock' => 31, 'available' => true, 'active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.supplier.update', $supplierProduct), [
            'product_sku' => 'AH-CABLE-1', 'sync_stock' => '1', 'active' => '1', 'stock_divisor' => 10,
        ])->assertRedirect();

        $this->assertSame($product->id, $supplierProduct->fresh()->product_id);
        $this->assertTrue($supplierProduct->fresh()->sync_stock);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_admin_can_import_a_supplier_variant_as_a_draft_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $supplierProduct = SupplierProduct::create([
            'supplier_product_id' => '44',
            'supplier_variant_id' => '9',
            'supplier_reference' => 'LCD-CASE-9',
            'ean' => '3700000000001',
            'brand' => 'Mayline',
            'source_category' => 'Protections',
            'name' => 'Coque renforcée',
            'variant_name' => 'Apple iPhone 15',
            'description' => 'Coque antichoc avec angles renforcés.',
            'supplier_url' => 'https://lcd-phone.com/fr/protections/44-coque.html',
            'image' => 'https://lcd-phone.com/coque-1.jpg',
            'images' => ['https://lcd-phone.com/coque-1.jpg', 'https://lcd-phone.com/coque-2.jpg'],
            'purchase_price' => 1,
            'minimum_order_quantity' => 10,
            'stock_divisor' => 10,
            'supplier_stock' => 45,
            'available' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.supplier.import', $supplierProduct))
            ->assertOk()
            ->assertSee('Créer le produit AchatHub')
            ->assertSee('19.99', false);

        $response = $this->actingAs($admin)->post(route('admin.supplier.store-product', $supplierProduct), [
            'category_id' => $category->id,
            'name' => 'Coque renforcée iPhone 15',
            'sku' => 'AH-CASE-15',
            'brand' => 'Mayline',
            'family' => 'Protections',
            'description' => 'Coque antichoc avec angles renforcés.',
            'price' => 19.99,
            'stock_divisor' => 10,
            'sync_stock' => '1',
        ]);

        $product = Product::where('sku', 'AH-CASE-15')->firstOrFail();
        $response->assertRedirect(route('admin.products.edit', $product));
        $this->assertFalse($product->active);
        $this->assertSame(4, $product->stock);
        $this->assertCount(2, $product->images);
        $this->assertSame($product->id, $supplierProduct->fresh()->product_id);
        $this->assertTrue($supplierProduct->fresh()->sync_stock);
    }

    public function test_category_agent_creates_a_stable_hierarchy_and_classifies_supplier_products(): void
    {
        $accessories = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $existingProtection = Category::create(['name' => 'Protections ecran', 'slug' => 'protections-ecran']);
        $linkedProduct = Product::create([
            'category_id' => $accessories->id,
            'sku' => 'AH-COQUE-1',
            'name' => 'Coque transparente iPhone',
            'slug' => 'coque-transparente-iphone',
            'price' => 12.99,
            'stock' => 5,
        ]);
        $products = collect([
            ['id' => '1', 'name' => 'Coque transparente iPhone', 'source' => 'Protections', 'product_id' => $linkedProduct->id],
            ['id' => '2', 'name' => 'Verre trempé HD iPhone', 'source' => 'Mayline'],
            ['id' => '3', 'name' => 'Batterie 1960mAh iPhone 7', 'source' => 'Apple'],
            ['id' => '4', 'name' => 'Écouteurs Bluetooth Ultrapods', 'source' => 'Mayline'],
        ])->map(fn (array $item) => SupplierProduct::create([
            'supplier_product_id' => $item['id'],
            'supplier_variant_id' => '0',
            'name' => $item['name'],
            'source_category' => $item['source'],
            'supplier_url' => 'https://lcd-phone.com/fr/accessoires/'.$item['id'].'-produit.html',
            'supplier_stock' => 10,
            'available' => true,
            'active' => true,
            'product_id' => $item['product_id'] ?? null,
        ]));

        $service = new SupplierCategoryService;
        $firstRun = $service->categorize();
        $categoryCount = Category::count();
        $secondRun = $service->categorize();

        $this->assertSame('success', $firstRun->status);
        $this->assertSame('success', $secondRun->status);
        $this->assertSame($categoryCount, Category::count());
        $this->assertDatabaseHas('categories', ['slug' => 'accessoires-coques-telephone', 'parent_id' => $accessories->id, 'supplier_managed' => true]);
        $this->assertDatabaseHas('categories', ['id' => $existingProtection->id, 'slug' => 'protections-ecran', 'parent_id' => $accessories->id]);
        $this->assertSame(1, Category::where('name', 'Protections ecran')->count());
        $this->assertDatabaseHas('categories', ['slug' => 'pieces-detachees-batteries-telephone']);
        $this->assertDatabaseHas('categories', ['slug' => 'accessoires-ecouteurs-sans-fil']);
        $this->assertNotNull($products[0]->fresh()->suggested_category_id);
        $this->assertSame('accessoires-coques-telephone', $linkedProduct->fresh()->category->slug);
    }
}
