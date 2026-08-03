<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagementAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_and_admin_can_exchange_private_messages(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer)->post(route('messages.store'), ['body' => 'Bonjour, où est ma commande ?'])->assertRedirect();
        $conversation = Conversation::where('user_id', $customer->id)->firstOrFail();
        $this->assertDatabaseHas('conversation_messages', ['conversation_id' => $conversation->id, 'sender_id' => $customer->id]);

        $this->actingAs($admin)->post(route('admin.conversations.store', $conversation), ['body' => 'Nous vérifions votre livraison.'])->assertRedirect();
        $this->actingAs($customer)->get(route('messages.index'))->assertOk()->assertSee('Nous vérifions votre livraison.');
    }

    public function test_conversation_is_not_exposed_through_a_customer_identifier(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        Conversation::create(['user_id' => $owner->id, 'subject' => 'Privé']);

        $response = $this->actingAs($other)->get(route('messages.index'));
        $response->assertOk();
        $this->assertDatabaseHas('conversations', ['user_id' => $other->id]);
    }

    public function test_admin_can_update_profile_without_touching_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer', 'password' => bcrypt('SecretBefore')]);
        $password = $customer->password;

        $this->actingAs($admin)->patch(route('admin.customers.update', $customer), [
            'name' => 'Client Modifié', 'email' => $customer->email, 'phone' => '0601020304',
            'address' => '10 rue Exemple', 'role' => 'customer', 'admin_notes' => 'Client vérifié',
        ])->assertRedirect();

        $this->assertSame($password, $customer->fresh()->password);
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'name' => 'Client Modifié', 'admin_notes' => 'Client vérifié']);
    }

    public function test_user_can_submit_and_follow_a_data_rights_request(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->post(route('data-rights.store'), ['type' => 'Accès', 'message' => 'Je souhaite une copie.'])->assertRedirect();
        $this->assertDatabaseHas('data_rights_requests', ['user_id' => $customer->id, 'type' => 'Accès', 'status' => 'Nouvelle']);
        $this->get(route('data-rights.index'))->assertOk()->assertSee('Je souhaite une copie.');
    }

    public function test_cookie_choice_and_legal_pages_are_available(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Politique de confidentialité')
            ->assertSee('Durées de conservation')
            ->assertSee('CNIL');
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertSee('Conditions générales')
            ->assertSee('Formulaire type de rétractation')
            ->assertSee('Médiateur à désigner');
        $this->get(route('legal.notice'))
            ->assertOk()
            ->assertSee('Informations à finaliser');
        $this->post(route('cookies.consent'), ['choice' => 'refused'])->assertCookie('cookie_consent', 'refused');
    }

    public function test_security_headers_are_added_to_public_and_private_pages(): void
    {
        $this->get(route('legal.privacy'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get(route('data-rights.index'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_user_can_request_limitation_of_processing(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->post(route('data-rights.store'), [
            'type' => 'Limitation',
            'message' => 'Veuillez geler temporairement les traitements non obligatoires.',
        ])->assertRedirect();

        $this->assertDatabaseHas('data_rights_requests', [
            'user_id' => $customer->id,
            'type' => 'Limitation',
        ]);
    }

    public function test_support_honeypot_rejects_automated_submission(): void
    {
        $this->post(route('support.store'), [
            'name' => 'Robot',
            'email' => 'robot@example.test',
            'subject' => 'Spam',
            'message' => 'Contenu indésirable',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, SupportMessage::query()->count());
    }
}
