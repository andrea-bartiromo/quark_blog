<?php

namespace Tests\Feature\Admin\Communication;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPreviewRecipientSearchTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function campaign(): CommunicationCampaign
    {
        return CommunicationCampaign::factory()->draft()->create([
            'subject' => 'Oggetto',
            'content' => ['body' => 'Corpo.'],
        ]);
    }

    public function test_search_by_partial_email_finds_matching_confirmed_subscribers(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'mario.rossi@example.com']);
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'luigi.bianchi@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=mario');

        $response->assertOk();
        $response->assertSee('mario.rossi@example.com');
        $response->assertDontSee('luigi.bianchi@example.com');
    }

    public function test_search_only_matches_confirmed_subscribers(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->create([
            'email' => 'pending.mario@example.com',
            'status' => CommunicationSubscriber::STATUS_PENDING,
        ]);
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'confirmed.mario@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=mario');

        $response->assertOk();
        $response->assertSee('confirmed.mario@example.com');
        $response->assertDontSee('pending.mario@example.com');
    }

    public function test_no_match_shows_a_clear_empty_state_not_an_error(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'someone@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=nessuna-corrispondenza-xyz');

        $response->assertOk();
        $response->assertSee('Nessun iscritto confermato trovato');
    }

    public function test_literal_percent_in_the_search_term_is_not_treated_as_a_wildcard(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'contains%percent@example.com']);
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'other@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q='.urlencode('%percent'));

        $response->assertOk();
        $response->assertSee('contains%percent@example.com');
        $response->assertDontSee('other@example.com');
    }

    public function test_results_are_paginated_never_loading_every_confirmed_subscriber_at_once(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->count(25)->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign));

        $response->assertOk();
        // Pagina 1 di 2 con 25 iscritti e pageSize=20.
        $response->assertSee('Pagina 1 di 2');
        $response->assertSee('25 iscritto/i confermato/i');
    }

    public function test_second_page_link_shows_the_remaining_subscribers(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->count(25)->create();

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?page=2');

        $response->assertOk();
        $response->assertSee('Pagina 2 di 2');
    }

    public function test_search_query_is_capped_at_email_column_length_without_erroring(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->create();

        $veryLongQuery = str_repeat('a', 5000);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q='.$veryLongQuery);

        $response->assertOk();
    }

    public function test_search_requires_editor_authorization_same_as_the_base_preview(): void
    {
        $campaign = $this->campaign();
        $author = User::factory()->create(['role' => 'author']);

        $response = $this->actingAs($author)
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=test');

        $response->assertRedirect(route('redazione.dashboard'));
    }

    public function test_search_never_mutates_subscriber_or_delivery_state(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->count(3)->create();

        $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q=example');

        $this->assertDatabaseCount('comm_sends', 0);
        $this->assertDatabaseCount('communication_deliveries', 0);
    }

    public function test_whitespace_only_search_behaves_like_no_search(): void
    {
        $campaign = $this->campaign();
        CommunicationSubscriber::factory()->confirmed()->create(['email' => 'someone@example.com']);

        $response = $this->actingAs($this->editor())
            ->get(route('admin.comunicazione.campaigns.preview', $campaign).'?q='.urlencode('   '));

        $response->assertOk();
        $response->assertSee('someone@example.com');
    }
}
