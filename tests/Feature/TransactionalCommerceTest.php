<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProfessionalOrder;
use App\Models\ResellerRequest;
use App\Models\User;
use App\Notifications\AchatHubTransactionalNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class TransactionalCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_sends_welcome_and_verification_messages(): void
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'name' => 'Client AchatHub',
            'email' => 'client@achathub.test',
            'phone' => '0600000000',
            'password' => 'MotDePasse2026',
            'password_confirmation' => 'MotDePasse2026',
            'terms' => '1',
        ])->assertRedirect(route('account.index'));

        $user = User::where('email', 'client@achathub.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertSentTo($user, AchatHubTransactionalNotification::class);
    }

    public function test_customer_can_verify_email_from_signed_link(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect(route('account.index'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_link_is_available_for_customer_and_professional_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_professional_invoice_is_private_and_printable(): void
    {
        $owner = User::factory()->create(['role' => 'reseller']);
        $other = User::factory()->create(['role' => 'reseller']);
        $application = ResellerRequest::create([
            'user_id' => $owner->id,
            'business_name' => 'Commerce Démonstration',
            'manager_name' => $owner->name,
            'city' => 'Paris',
            'address' => '1 rue du Commerce',
            'phone' => '0600000000',
            'email' => $owner->email,
            'business_type' => 'Téléphonie',
            'formula' => 'Achat en gros',
            'display_type' => 'Moyen',
            'status' => 'Approuvée',
        ]);
        ResellerRequest::create([
            'user_id' => $other->id,
            'business_name' => 'Autre Commerce',
            'manager_name' => $other->name,
            'city' => 'Lyon',
            'address' => '2 rue du Test',
            'phone' => '0611111111',
            'email' => $other->email,
            'business_type' => 'Commerce',
            'formula' => 'Achat en gros',
            'display_type' => 'Petit',
            'status' => 'Approuvée',
        ]);
        $order = ProfessionalOrder::create([
            'number' => 'PRO-INVOICE-1',
            'user_id' => $owner->id,
            'reseller_request_id' => $application->id,
            'status' => 'Confirmée',
            'payment_status' => 'Payé',
            'payment_method' => 'Virement bancaire',
            'subtotal_ht' => 100,
            'vat_amount' => 20,
            'total_ttc' => 120,
            'contact_name' => $owner->name,
            'phone' => '0600000000',
            'address' => '1 rue du Commerce',
            'city' => 'Paris',
        ]);
        $order->items()->create([
            'name' => 'Présentoir AchatHub',
            'price_ht' => 100,
            'quantity' => 1,
            'vat_rate' => 20,
        ]);

        $this->actingAs($owner)->get(route('pro.invoice', $order))
            ->assertOk()
            ->assertSee('Facture')
            ->assertSee('PRO-INVOICE-1');
        $this->actingAs($other)->get(route('pro.invoice', $order))->assertForbidden();
    }

    public function test_admin_dashboard_exposes_sales_readiness_indicators(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Order::create([
            'number' => 'AH-DASHBOARD-1',
            'access_token' => str_repeat('d', 48),
            'status' => 'Nouvelle',
            'payment_status' => 'En attente',
            'subtotal' => 20,
            'shipping' => 0,
            'total' => 20,
            'shipping_name' => 'Client Test',
            'shipping_phone' => '0600000000',
            'shipping_address' => '1 rue Test',
            'shipping_city' => 'Paris',
        ]);

        $this->actingAs($admin)->get(route('admin.index'))
            ->assertOk()
            ->assertSee('Commandes à traiter')
            ->assertSee('Paiements en attente')
            ->assertSee('Stock faible');
    }
}
