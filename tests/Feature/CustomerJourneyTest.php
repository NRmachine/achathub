<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_login_from_checkout_returns_to_checkout_with_cart_intact(): void
    {
        $product = $this->product();
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->withSession(['cart' => [$product->id => 2]])->post(route('login.store'), [
            'email' => $customer->email,
            'password' => 'password',
            'redirect_to' => 'checkout',
        ]);

        $response->assertRedirect(route('checkout.index'));
        $this->assertAuthenticatedAs($customer);
        $this->assertSame([$product->id => 2], session('cart'));
    }

    public function test_customer_registration_from_checkout_returns_to_checkout_with_cart_intact(): void
    {
        Notification::fake();
        $product = $this->product();

        $response = $this->withSession(['cart' => [$product->id => 1]])->post(route('register.store'), [
            'name' => 'Nouvelle Cliente',
            'email' => 'nouvelle@example.test',
            'phone' => '06 10 20 30 40',
            'password' => 'MotDePasse2026',
            'password_confirmation' => 'MotDePasse2026',
            'terms' => '1',
            'redirect_to' => 'checkout',
        ]);

        $response->assertRedirect(route('checkout.index'));
        $this->assertAuthenticated();
        $this->assertSame([$product->id => 1], session('cart'));
    }

    public function test_checkout_prefills_the_saved_customer_address(): void
    {
        $product = $this->product();
        $customer = User::factory()->create(['role' => 'customer']);
        $customer->addresses()->create([
            'label' => 'Livraison',
            'name' => 'Client Prérempli',
            'phone' => '06 11 22 33 44',
            'address' => '12 rue du Commerce',
            'postal_code' => '75001',
            'city' => 'Paris',
            'default' => true,
        ]);

        $this->actingAs($customer)
            ->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Client Prérempli')
            ->assertSee('12 rue du Commerce')
            ->assertSee('75001')
            ->assertSee('Votre adresse de livraison habituelle a été préremplie.');
    }

    public function test_customer_can_save_delivery_address_during_checkout(): void
    {
        Notification::fake();
        $product = $this->product();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)
            ->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), [
                'email' => $customer->email,
                'name' => 'Client Fidèle',
                'phone' => '06 12 34 56 78',
                'address' => '8 avenue de la Vente',
                'postal_code' => '69001',
                'city' => 'Lyon',
                'shipping_method' => 'standard',
                'payment_method' => 'livraison',
                'save_address' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $customer->id,
            'address' => '8 avenue de la Vente',
            'postal_code' => '69001',
            'city' => 'Lyon',
            'default' => true,
        ]);
    }

    public function test_cart_rejects_zero_quantity_and_keeps_the_item(): void
    {
        $product = $this->product();

        $this->withSession(['cart' => [$product->id => 2]])
            ->patch(route('cart.update', $product), ['quantity' => 0])
            ->assertSessionHasErrors('quantity');

        $this->assertSame([$product->id => 2], session('cart'));
    }

    public function test_guest_checkout_explains_that_an_account_is_optional(): void
    {
        $product = $this->product();

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Vous pouvez commander sans compte.')
            ->assertSee('Livraison et paiement')
            ->assertSee(route('login', ['redirect_to' => 'checkout']), false);
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(
            ['slug' => 'parcours-client'],
            ['name' => 'Parcours client'],
        );

        return Product::create([
            'category_id' => $category->id,
            'sku' => 'JOURNEY-'.str()->random(6),
            'name' => 'Produit parcours client',
            'slug' => 'produit-parcours-'.str()->random(8),
            'price' => 24.90,
            'stock' => 10,
            'active' => true,
        ]);
    }
}
