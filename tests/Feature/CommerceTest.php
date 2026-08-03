<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_be_added_to_cart(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'TEST-1', 'name' => 'Chargeur USB-C', 'slug' => 'chargeur-usb-c-test-1', 'price' => 12.99, 'stock' => 10]);

        $this->post(route('cart.add', $product))->assertRedirect();
        $this->assertEquals(1, session('cart')[$product->id]);
    }

    public function test_encrypted_cookie_session_keeps_the_cart_between_real_requests(): void
    {
        config()->set('session.driver', 'cookie');

        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires-cookie']);
        $product = Product::create([
            'category_id' => $category->id,
            'sku' => 'COOKIE-CART-1',
            'name' => 'Produit panier cookie',
            'slug' => 'produit-panier-cookie',
            'price' => 12.90,
            'stock' => 5,
        ]);

        $this->post(route('cart.add', $product))->assertRedirect();
        $this->get(route('cart.index'))->assertOk()->assertSee('Produit panier cookie');
    }

    public function test_product_can_be_added_without_reloading_the_page(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'ASYNC-1', 'name' => 'Câble USB-C', 'slug' => 'cable-usb-c-async', 'price' => 9.99, 'stock' => 10]);

        $this->postJson(route('cart.add', $product))->assertOk()->assertJson([
            'cart_count' => 1,
            'item_quantity' => 1,
            'subtotal' => '9,99 €',
        ]);
        $this->assertSame(1, session('cart')[$product->id]);
    }

    public function test_buy_now_places_the_product_in_cart_and_opens_checkout(): void
    {
        $category = Category::create(['name' => 'Chargeurs', 'slug' => 'chargeurs']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'BUY-1', 'name' => 'Chargeur immédiat', 'slug' => 'chargeur-immediat', 'price' => 29.90, 'stock' => 8]);

        $this->post(route('cart.buy-now', $product), ['quantity' => 2])->assertRedirect(route('checkout.index'));
        $this->assertSame(2, session('cart')[$product->id]);
    }

    public function test_catalog_search_price_filters_and_sort_are_functional(): void
    {
        $category = Category::create(['name' => 'Chargeurs', 'slug' => 'chargeurs']);
        Product::create(['category_id' => $category->id, 'sku' => 'SEARCH-1', 'name' => 'Chargeur rapide', 'slug' => 'chargeur-samsung-s24', 'brand' => 'Samsung', 'model' => 'Galaxy S24', 'price' => 39.90, 'stock' => 8]);
        Product::create(['category_id' => $category->id, 'sku' => 'SEARCH-2', 'name' => 'Chargeur économique', 'slug' => 'chargeur-economique', 'brand' => 'Generic', 'price' => 9.90, 'stock' => 8]);

        $this->get(route('home', ['q' => 'Samsung S24']))->assertOk()->assertSee('Chargeur rapide')->assertDontSee('Chargeur économique');
        $this->get(route('home', ['min_price' => 20, 'sort' => 'price_desc']))->assertOk()->assertSee('Chargeur rapide')->assertDontSee('Chargeur économique');
        $this->get(route('home', ['sort' => 'price_asc']))->assertOk()->assertSeeInOrder(['Chargeur économique', 'Chargeur rapide']);
    }

    public function test_storefront_exposes_simple_account_pro_cart_and_service_shortcuts(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Accès rapides')
            ->assertSee('Stock visible')
            ->assertSee('Facture disponible')
            ->assertSee('Connexion professionnelle')
            ->assertSee('achathub-logo.webp', false)
            ->assertSee('id="ah-page-loader"', false)
            ->assertSee('vendor/bootstrap/bootstrap.min.css', false)
            ->assertSee('vendor/bootstrap-icons/bootstrap-icons.min.css', false)
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertSee('achathub-speed-commerce.css', false);
    }

    public function test_a_cold_production_storefront_uses_the_bundled_catalog_without_database_queries(): void
    {
        Cache::store('file')->forget('storefront.catalog-default.v1');
        Cache::store('file')->forget('storefront.catalog-metadata.v5');
        Cache::store('file')->forget('storefront.navigation.v5');
        Cache::store('file')->forget('storefront.container-warmed.v1');
        app()->detectEnvironment(fn (): string => 'production');

        DB::enableQueryLog();
        $this->get(route('home'))->assertOk()->assertSee('Accessoire de test');

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_only_a_customer_with_a_delivered_purchase_can_publish_a_review(): void
    {
        $category = Category::create(['name' => 'Chargeurs', 'slug' => 'chargeurs']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'REVIEW-1', 'name' => 'Chargeur à noter', 'slug' => 'chargeur-a-noter', 'price' => 20, 'stock' => 8]);
        $customer = User::factory()->create(['role' => 'customer']);
        $review = ['rating' => 5, 'title' => 'Très bon produit', 'comment' => 'Recharge rapide et produit conforme.'];

        $this->actingAs($customer)->post(route('products.reviews.store', $product), $review)->assertForbidden();

        $order = Order::create(['number' => 'AH-REVIEW-1', 'access_token' => str_repeat('r', 48), 'user_id' => $customer->id, 'status' => 'Livrée', 'subtotal' => 20, 'shipping' => 0, 'total' => 20, 'shipping_name' => $customer->name, 'shipping_phone' => '0600000000', 'shipping_address' => '1 rue Test', 'shipping_city' => 'Paris']);
        $order->items()->create(['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'price' => 20, 'quantity' => 1]);

        $this->actingAs($customer)->post(route('products.reviews.store', $product), $review)->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('product_reviews', ['product_id' => $product->id, 'user_id' => $customer->id, 'rating' => 5, 'verified_purchase' => true]);
        $this->assertSame(5.0, $product->fresh()->rating);
        $this->assertSame(1, $product->fresh()->reviews_count);
    }

    public function test_guest_can_checkout_and_receives_a_private_tracking_link(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'ORDER-1', 'name' => 'Chargeur rapide', 'slug' => 'chargeur-rapide-order', 'price' => 25, 'stock' => 5]);

        $response = $this->withSession(['cart' => [$product->id => 2]])->post(route('checkout.store'), [
            'email' => 'invite@example.test', 'name' => 'Client Invité', 'phone' => '0600000000',
            'address' => '10 rue du Test', 'postal_code' => '75001', 'city' => 'Paris',
            'shipping_method' => 'standard', 'payment_method' => 'livraison',
        ]);

        $order = Order::firstOrFail();
        $response->assertRedirect(route('orders.guest.show', ['order' => $order->access_token]));
        $this->assertNull($order->user_id);
        $this->assertSame('invite@example.test', $order->guest_email);
        $this->assertSame('54.90', $order->total);
        $this->assertCount(1, $order->statusEvents);
        $this->assertSame(3, $product->fresh()->stock);
        $this->get(route('orders.guest.show', ['order' => $order->access_token]))->assertOk()->assertSee($order->number);
    }

    public function test_admin_status_update_creates_a_customer_tracking_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create(['number' => 'AH-TEST-STATUS', 'access_token' => str_repeat('a', 48), 'user_id' => $customer->id, 'subtotal' => 20, 'shipping' => 4.90, 'total' => 24.90, 'shipping_name' => $customer->name, 'shipping_phone' => '0600000000', 'shipping_address' => '1 rue Test', 'shipping_city' => 'Paris']);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'Expédiée', 'payment_status' => 'Payé', 'carrier' => 'Colissimo', 'tracking_number' => 'TRACK123'])->assertRedirect();

        $this->assertDatabaseHas('order_status_events', ['order_id' => $order->id, 'status' => 'Expédiée']);
        $this->assertNotNull($order->fresh()->shipped_at);
        $this->assertSame('TRACK123', $order->fresh()->tracking_number);
    }

    public function test_return_is_only_available_for_a_delivered_customer_order(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create(['number' => 'AH-TEST-RETURN', 'access_token' => str_repeat('b', 48), 'user_id' => $customer->id, 'subtotal' => 20, 'shipping' => 0, 'total' => 20, 'shipping_name' => $customer->name, 'shipping_phone' => '0600000000', 'shipping_address' => '1 rue Test', 'shipping_city' => 'Paris']);

        $this->actingAs($customer)->get(route('account.returns.create', $order))->assertStatus(422);
    }

    public function test_admin_area_rejects_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('admin.index'))->assertForbidden();
    }

    public function test_guest_is_sent_to_the_dedicated_admin_login_and_returns_to_the_requested_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->get(route('admin.supplier.index'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))->assertOk()->assertSee('Administration AchatHub');

        $this->withSession(['url.intended' => route('admin.supplier.index', ['status' => 'ready'])])
            ->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.supplier.index', ['status' => 'ready']));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_customer_credentials_cannot_open_the_admin_session(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->post(route('admin.login.store'), [
            'email' => $customer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_reseller_is_redirected_away_from_the_customer_cart(): void
    {
        $reseller = User::factory()->create(['role' => 'reseller']);
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires']);
        $product = Product::create(['category_id' => $category->id, 'sku' => 'PRO-BLOCK-1', 'name' => 'Produit particulier', 'slug' => 'produit-particulier-pro-block', 'price' => 10, 'stock' => 5]);

        $this->actingAs($reseller)->get(route('cart.index'))->assertRedirect(route('pro.index'));
        $this->actingAs($reseller)->postJson(route('cart.add', $product))->assertForbidden();
        $this->actingAs($reseller)->get(route('checkout.index'))->assertRedirect(route('pro.index'));
    }

    public function test_category_menu_uses_real_families_and_filters_the_catalog(): void
    {
        $category = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires', 'active' => true]);
        Product::create(['category_id' => $category->id, 'sku' => 'CABLE-1', 'name' => 'Câble USB-C', 'slug' => 'cable-usb-c', 'family' => 'Câbles', 'price' => 9.99, 'stock' => 10, 'active' => true]);
        Product::create(['category_id' => $category->id, 'sku' => 'CABLE-2', 'name' => 'Câble Lightning', 'slug' => 'cable-lightning', 'family' => 'Câbles > Lightning', 'price' => 11.99, 'stock' => 10, 'active' => true]);
        Product::create(['category_id' => $category->id, 'sku' => 'AUDIO-1', 'name' => 'Écouteurs Bluetooth', 'slug' => 'ecouteurs-bluetooth', 'family' => 'Audio', 'price' => 19.99, 'stock' => 10, 'active' => true]);

        $this->get(route('home'))->assertOk()->assertSee('Toutes les catégories')->assertSee('Câbles')->assertSee('Audio')->assertSee('mobile-shop-nav', false);
        $this->get(route('home', ['category' => 'accessoires', 'family' => 'Câbles']))
            ->assertOk()->assertSee('Câble USB-C')->assertSee('Câble Lightning')->assertDontSee('Écouteurs Bluetooth');
    }

    public function test_parent_category_includes_products_from_its_subcategories(): void
    {
        $root = Category::create(['name' => 'Accessoires', 'slug' => 'accessoires', 'active' => true]);
        $subcategory = Category::create(['parent_id' => $root->id, 'name' => 'Coques téléphone', 'slug' => 'accessoires-coques-telephone', 'active' => true]);
        Product::create(['category_id' => $subcategory->id, 'sku' => 'CASE-TREE-1', 'name' => 'Coque iPhone renforcée', 'slug' => 'coque-iphone-renforcee-tree', 'price' => 14.99, 'stock' => 8, 'active' => true]);

        $this->get(route('home', ['category' => $root->slug]))
            ->assertOk()
            ->assertSee('Coque iPhone renforcée')
            ->assertSee('Coques téléphone');

        $this->get(route('home', ['category' => $subcategory->slug]))
            ->assertOk()
            ->assertSee('Coque iPhone renforcée')
            ->assertSeeInOrder(['Accessoires', 'Coques téléphone']);
    }
}
