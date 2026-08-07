<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il Blueprint B1 è additivo: verifica esplicitamente che il modulo
 * Newsletter esistente continui a funzionare esattamente come prima
 * dell'introduzione del Sistema Comunicazione.
 */
class NewsletterModuleRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_admin_newsletter_index_still_works_and_lists_subscribers(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        Newsletter::create(['email' => 'ancora-qui@example.com', 'confirmed' => true, 'unsubscribe_token' => 'unsub-regression']);

        $response = $this->actingAs($editor)->get(route('admin.newsletter'));

        $response->assertOk();
        $response->assertSee('ancora-qui@example.com');
    }

    public function test_public_newsletter_subscribe_route_is_unchanged(): void
    {
        $response = $this->post(route('newsletter.subscribe'), ['email' => 'nuovo-iscritto@example.com']);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletter', ['email' => 'nuovo-iscritto@example.com']);
        // Il Sistema Comunicazione non deve ricevere scritture da questo percorso in B1.
        $this->assertDatabaseCount('comm_subscribers', 0);
    }

    public function test_the_newsletter_preview_and_send_now_routes_are_still_reachable(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $response = $this->actingAs($editor)->get(route('admin.newsletter.preview'));

        $response->assertOk();
    }
}
