<?php

namespace Tests\Feature;

use App\Models\ProfessionalDisplay;
use App\Models\ProfessionalPreorder;
use App\Models\ProfessionalProduct;
use App\Models\ResellerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfessionalCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_create_an_account_before_applying(): void
    {
        $this->post(route('reseller.store'), $this->applicationData())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reseller_requests', 0);
    }

    public function test_public_professional_page_shows_real_display_offer_and_price(): void
    {
        ProfessionalDisplay::create([
            'name' => 'Petit présentoir comptoir',
            'slug' => 'petit-presentoir-public',
            'description' => 'Sélection prête à vendre.',
            'wholesale_price_ht' => 130.30,
            'vat_rate' => 20,
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->get(route('reseller.index'))
            ->assertOk()
            ->assertSee('Petit présentoir comptoir')
            ->assertSee('130,30 €')
            ->assertSee('Tarifs revendeurs HT')
            ->assertSee('Déjà client Pro ? Se connecter');
    }

    public function test_pending_reseller_cannot_access_wholesale_catalog(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('reseller.store'), $this->applicationData())->assertRedirect(route('reseller.dashboard'));

        $this->assertDatabaseHas('reseller_requests', ['user_id' => $user->id, 'status' => 'En attente']);
        $this->get(route('pro.index'))->assertRedirect(route('reseller.dashboard'));
    }

    public function test_admin_approval_opens_the_professional_catalog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $application = ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'En attente']);

        $this->actingAs($admin)->patch(route('admin.resellers.review', $application), [
            'status' => 'Approuvée',
            'admin_notes' => 'Dossier vérifié',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'reseller']);
        $this->actingAs($user->refresh())
            ->get(route('pro.index'))
            ->assertOk()
            ->assertSee('Besoin d’un conseil ?')
            ->assertSee('Facture Pro');
    }

    public function test_approved_reseller_can_order_a_display(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée', 'approved_at' => now()]);
        $display = ProfessionalDisplay::create([
            'name' => 'Petit présentoir comptoir',
            'slug' => 'petit-presentoir',
            'wholesale_price_ht' => 130.30,
            'vat_rate' => 20,
            'active' => true,
        ]);

        $this->actingAs($user)->post(route('pro.cart.add', $display), ['quantity' => 2])->assertRedirect(route('pro.cart'));
        $this->post(route('pro.order'), [
            'contact_name' => 'Jean Dupont',
            'phone' => '0600000000',
            'address' => '1 rue du Commerce',
            'city' => 'Paris',
            'payment_method' => 'Carte bancaire à la livraison',
        ])->assertRedirect(route('pro.account'));

        $this->assertDatabaseHas('professional_orders', [
            'user_id' => $user->id,
            'subtotal_ht' => 260.60,
            'vat_amount' => 52.12,
            'total_ttc' => 312.72,
            'payment_method' => 'Carte bancaire à la livraison',
        ]);
    }

    public function test_approved_reseller_can_order_wholesale_products_directly(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée', 'approved_at' => now()]);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-DIRECT',
            'name' => 'Câble USB-C grossiste',
            'category' => 'Câbles',
            'wholesale_price_ht' => 4.50,
            'minimum_order_quantity' => 5,
            'stock' => 30,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('pro.cart.products.add', $product), ['quantity' => 10])
            ->assertRedirect(route('pro.cart'));
        $this->get(route('pro.cart'))->assertOk()->assertSee('Câble USB-C grossiste');
        $this->post(route('pro.order'), [
            'contact_name' => 'Jean Dupont',
            'phone' => '0600000000',
            'address' => '1 rue du Commerce',
            'city' => 'Paris',
            'payment_method' => 'Virement bancaire',
        ])->assertRedirect(route('pro.account'));

        $this->assertDatabaseHas('professional_products', ['id' => $product->id, 'stock' => 20]);
        $this->assertDatabaseHas('professional_orders', ['user_id' => $user->id, 'subtotal_ht' => 45, 'vat_amount' => 9, 'total_ttc' => 54]);
        $this->assertDatabaseHas('professional_order_items', [
            'professional_product_id' => $product->id,
            'quantity' => 10,
            'price_ht' => 4.50,
        ]);
    }

    public function test_wholesale_product_minimum_quantity_is_enforced(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-MINIMUM',
            'name' => 'Chargeur grossiste',
            'category' => 'Chargeurs secteur',
            'wholesale_price_ht' => 6,
            'minimum_order_quantity' => 5,
            'stock' => 20,
            'active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('pro.cart.products.add', $product), ['quantity' => 4])
            ->assertSessionHasErrors('quantity');

        $this->assertSame([], session('professional_cart', []));
    }

    public function test_admin_can_manage_wholesale_price_minimum_stock_and_visibility(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-ADMIN',
            'name' => 'Produit à administrer',
            'category' => 'Accessoires',
            'wholesale_price_ht' => 5,
            'minimum_order_quantity' => 5,
            'stock' => 20,
            'active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.professional-products.update', $product), [
            'name' => 'Produit grossiste optimisé',
            'category' => 'Câbles',
            'description' => 'Description commerciale vérifiée dans le catalogue.',
            'wholesale_price_ht' => 4.25,
            'minimum_order_quantity' => 10,
            'stock' => 120,
            'active' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('professional_products', [
            'id' => $product->id,
            'name' => 'Produit grossiste optimisé',
            'category' => 'Câbles',
            'description' => 'Description commerciale vérifiée dans le catalogue.',
            'wholesale_price_ht' => 4.25,
            'minimum_order_quantity' => 10,
            'stock' => 120,
            'active' => false,
        ]);
    }

    public function test_professional_product_page_links_related_products_and_displays(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-FICHE',
            'name' => 'Câble professionnel fiche',
            'category' => 'Câbles',
            'description' => 'Description catalogue vérifiée.',
            'wholesale_price_ht' => 4.50,
            'minimum_order_quantity' => 5,
            'stock' => 30,
            'active' => true,
        ]);
        ProfessionalProduct::create(['sku' => 'PRO-LIE', 'name' => 'Autre câble associé', 'category' => 'Câbles', 'wholesale_price_ht' => 3, 'minimum_order_quantity' => 5, 'stock' => 20, 'active' => true]);
        ProfessionalProduct::create(['sku' => 'PRO-AUTRE', 'name' => 'Produit sans rapport', 'category' => 'Audio', 'wholesale_price_ht' => 8, 'minimum_order_quantity' => 3, 'stock' => 20, 'active' => true]);
        $display = ProfessionalDisplay::create(['name' => 'Présentoir fiche', 'slug' => 'presentoir-fiche', 'wholesale_price_ht' => 99, 'vat_rate' => 20, 'active' => true]);
        $display->products()->attach($product, ['quantity' => 8, 'unit_price_ht' => 4.50]);

        $this->actingAs($user)
            ->get(route('pro.products.show', $product))
            ->assertOk()
            ->assertSee('Description catalogue vérifiée.')
            ->assertSee('22,50 € HT le lot')
            ->assertSee('Présentoir fiche')
            ->assertSee('8 unité(s) incluse(s)')
            ->assertSee('Autre câble associé')
            ->assertDontSee('Produit sans rapport');
    }

    public function test_professional_catalog_filters_availability_and_searches_descriptions(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);
        ProfessionalProduct::create(['sku' => 'PRO-DISPO', 'name' => 'Produit disponible', 'category' => 'Câbles', 'description' => 'Connectique recherchée', 'wholesale_price_ht' => 4, 'minimum_order_quantity' => 5, 'stock' => 20, 'active' => true]);
        ProfessionalProduct::create(['sku' => 'PRO-REASSORT', 'name' => 'Produit à réassortir', 'category' => 'Audio', 'description' => 'Audio recherché', 'wholesale_price_ht' => 9, 'minimum_order_quantity' => 5, 'stock' => 2, 'active' => true]);

        $this->actingAs($user)->get(route('pro.index', ['q' => 'connectique']))
            ->assertOk()->assertSee('Produit disponible')->assertDontSee('Produit à réassortir');
        $this->get(route('pro.index', ['availability' => 'preorder']))
            ->assertOk()->assertSee('Produit à réassortir')->assertDontSee('Produit disponible');
    }

    public function test_reseller_login_redirects_directly_to_professional_catalog(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);

        $this->post(route('professional.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('pro.index'));
    }

    public function test_professional_credentials_are_rejected_by_customer_login(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_credentials_are_rejected_by_professional_login(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->post(route('professional.login.store'), ['email' => $customer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_pending_company_uses_professional_login_to_view_its_validation(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'En attente']);

        $this->post(route('professional.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('reseller.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_professional_account_cannot_open_the_customer_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);

        $this->actingAs($user)
            ->get(route('account.index'))
            ->assertRedirect(route('pro.index'));
    }

    public function test_reseller_can_preorder_a_product_without_quantity_or_cart(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-TEST',
            'name' => 'Chargeur professionnel',
            'category' => 'Chargeurs secteur',
            'wholesale_price_ht' => 5,
            'minimum_order_quantity' => 5,
            'stock' => 20,
            'active' => true,
        ]);

        $this->actingAs($user)->post(route('pro.products.preorder', $product))->assertRedirect(route('pro.account'));
        $this->post(route('pro.products.preorder', $product))->assertRedirect();

        $this->assertDatabaseCount('professional_preorders', 1);
        $this->assertDatabaseHas('professional_preorders', ['user_id' => $user->id, 'professional_product_id' => $product->id, 'status' => 'Nouvelle']);
        $this->assertDatabaseHas('professional_products', ['id' => $product->id, 'stock' => 20]);
        $this->assertDatabaseCount('professional_orders', 0);

        $preorder = ProfessionalPreorder::firstOrFail();
        $this->delete(route('pro.preorders.destroy', $preorder))->assertRedirect();
        $this->assertDatabaseCount('professional_preorders', 0);
    }

    public function test_validated_preorder_is_visible_but_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        $application = ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);
        $product = ProfessionalProduct::create([
            'sku' => 'PRO-VALIDATED',
            'name' => 'Produit professionnel validé',
            'category' => 'Accessoires',
            'wholesale_price_ht' => 8,
            'stock' => 20,
            'active' => true,
        ]);
        $preorder = ProfessionalPreorder::create([
            'number' => 'PRE-VALIDATED',
            'user_id' => $user->id,
            'reseller_request_id' => $application->id,
            'professional_product_id' => $product->id,
            'status' => 'Validée',
        ]);

        $this->actingAs($user)->get(route('pro.account'))->assertOk()->assertSee('Validée');
        $this->delete(route('pro.preorders.destroy', $preorder))->assertStatus(422);
        $this->assertDatabaseHas('professional_preorders', ['id' => $preorder->id, 'status' => 'Validée']);
    }

    public function test_reseller_cannot_delete_another_company_preorder(): void
    {
        $owner = User::factory()->create(['role' => 'reseller']);
        $ownerApplication = ResellerRequest::create($this->applicationData() + ['user_id' => $owner->id, 'status' => 'Approuvée']);
        $other = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create([...$this->applicationData(), 'email' => 'autre@example.com', 'user_id' => $other->id, 'status' => 'Approuvée']);
        $product = ProfessionalProduct::create(['sku' => 'PRO-PRIVATE', 'name' => 'Produit privé', 'category' => 'Accessoires', 'wholesale_price_ht' => 8, 'stock' => 20, 'active' => true]);
        $preorder = ProfessionalPreorder::create(['number' => 'PRE-PRIVATE', 'user_id' => $owner->id, 'reseller_request_id' => $ownerApplication->id, 'professional_product_id' => $product->id, 'status' => 'Nouvelle']);

        $this->actingAs($other)->delete(route('pro.preorders.destroy', $preorder))->assertForbidden();
        $this->assertDatabaseHas('professional_preorders', ['id' => $preorder->id]);
    }

    public function test_professional_pagination_keeps_the_user_on_the_pro_catalog(): void
    {
        $user = User::factory()->create(['role' => 'reseller']);
        ResellerRequest::create($this->applicationData() + ['user_id' => $user->id, 'status' => 'Approuvée']);

        foreach (range(1, 17) as $index) {
            ProfessionalProduct::create([
                'sku' => "PRO-PAGE-{$index}",
                'name' => "Produit professionnel {$index}",
                'category' => 'Accessoires',
                'wholesale_price_ht' => 5,
                'stock' => 20,
                'active' => true,
            ]);
        }

        $this->actingAs($user)
            ->get('/pro')
            ->assertOk()
            ->assertSee('href="/pro?page=2"', false)
            ->assertSee('Afficher plus de produits')
            ->assertDontSee('pagination.next')
            ->assertDontSee('pagination.previous')
            ->assertDontSee('sslip.io/pro?page=2', false);
    }

    public function test_french_business_can_create_a_pending_professional_account(): void
    {
        $this->post(route('professional.register.store'), [
            'company_type' => 'Micro-entreprise',
            'legal_form' => 'Micro-entrepreneur',
            'business_name' => 'Entreprise Test France',
            'commercial_name' => 'Boutique Test',
            'siren' => '123456789',
            'siret' => '12345678900011',
            'activity' => 'Commerce de proximité',
            'manager_first_name' => 'Jean',
            'manager_last_name' => 'Dupont',
            'email' => 'pro-france@example.com',
            'phone' => '0600000000',
            'address' => '1 rue du Commerce',
            'postal_code' => '75001',
            'city' => 'Paris',
            'password' => 'TestEntreprise2026',
            'password_confirmation' => 'TestEntreprise2026',
            'terms' => '1',
        ])->assertRedirect(route('reseller.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'pro-france@example.com', 'role' => 'customer']);
        $this->assertDatabaseHas('reseller_requests', ['siret' => '12345678900011', 'legal_form' => 'Micro-entrepreneur', 'status' => 'En attente']);
    }

    private function applicationData(): array
    {
        return [
            'business_name' => 'Boutique Test',
            'manager_name' => 'Jean Dupont',
            'city' => 'Paris',
            'address' => '1 rue du Commerce',
            'phone' => '0600000000',
            'email' => 'commerce@example.com',
            'business_type' => 'Boutique téléphonie',
            'formula' => 'Achat en gros',
            'display_type' => 'Petit',
            'categories' => 'Accessoires',
        ];
    }
}
