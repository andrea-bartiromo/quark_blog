<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContentClusterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_content_cluster_admin(): void
    {
        $this->get(route('admin.content-clusters.index'))->assertRedirect(route('login'));
    }

    public function test_editor_can_create_cluster_with_membership_pillar_and_ordering(): void
    {
        $editor = $this->editor();
        $first = $this->article($editor, 'Primo articolo');
        $second = $this->article($editor, 'Secondo articolo', Article::STATUS_SCHEDULED);

        $response = $this->actingAs($editor)->post(route('admin.content-clusters.store'), [
            'name' => 'IA spiegata',
            'slug' => '',
            'is_active' => '1',
            'sort_order' => 2,
            'pillar_article_id' => $first->id,
            'membership_ids' => [$second->id, $first->id],
            'memberships' => [
                $second->id => ['position' => 50],
                $first->id => ['position' => 10, 'is_primary' => '1'],
            ],
        ]);

        $cluster = ContentCluster::where('slug', 'ia-spiegata')->firstOrFail();
        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $this->assertTrue($cluster->is_active);
        $this->assertSame($first->id, $cluster->pillar_article_id);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $cluster->id, 'article_id' => $first->id, 'position' => 10, 'is_primary' => true]);
        $this->assertDatabaseHas('article_content_cluster', ['content_cluster_id' => $cluster->id, 'article_id' => $second->id, 'position' => 20]);
    }

    public function test_form_submits_metadata_only_for_selected_memberships(): void
    {
        $editor = $this->editor();
        $selected = $this->article($editor, 'Selezionato');
        $unselected = $this->article($editor, 'Non selezionato');
        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $selected->id, 'position' => 10]], null);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk();
        $response->assertSee('name="membership_ids[]" value="'.$selected->id.'"', false);
        $response->assertSee('name="memberships['.$selected->id.'][position]" value="10"', false);
        $response->assertDontSee('name="memberships['.$unselected->id.'][position]"', false);
        $response->assertDontSee('name="memberships[0][article_id]"', false);
    }

    public function test_failed_validation_rehydrates_membership_primary_ordering_and_pillar(): void
    {
        $editor = $this->editor();
        $member = $this->article($editor, 'Stato da preservare');
        $other = $this->article($editor, 'Pillar non membro');

        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $member->id, 'position' => 10]], null);

        $response = $this->actingAs($editor)
            ->from(route('admin.content-clusters.edit', $cluster))
            ->put(route('admin.content-clusters.memberships.update', $cluster), [
                'pillar_article_id' => $other->id,
                'membership_ids' => [$member->id],
                'memberships' => [
                    $member->id => ['position' => 70, 'is_primary' => '1'],
                ],
            ]);

        $response->assertSessionHasErrors('pillar_article_id');

        $form = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));
        $form->assertSee('name="membership_ids[]" value="'.$member->id.'"', false);
        $form->assertSee('name="memberships['.$member->id.'][position]" value="70"', false);
        $form->assertSee('name="memberships['.$member->id.'][is_primary]" value="1" checked', false);
    }

    public function test_pillar_must_be_a_member_and_failed_create_is_atomic(): void
    {
        $editor = $this->editor();
        $pillar = $this->article($editor, 'Pillar');

        $this->actingAs($editor)->from(route('admin.content-clusters.create'))->post(route('admin.content-clusters.store'), [
            'name' => 'Spazio',
            'pillar_article_id' => $pillar->id,
            'membership_ids' => [],
        ])->assertSessionHasErrors('pillar_article_id');

        $this->assertDatabaseMissing('content_clusters', ['slug' => 'spazio']);
    }

    public function test_update_cannot_remove_the_current_pillar_membership_and_is_atomic(): void
    {
        $editor = $this->editor();
        $pillar = $this->article($editor, 'Pillar stabile');
        $cluster = ContentCluster::factory()->create(['name' => 'Originale', 'slug' => 'originale']);
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $pillar->id]], $pillar->id);

        $this->actingAs($editor)
            ->from(route('admin.content-clusters.edit', $cluster))
            ->put(route('admin.content-clusters.update', $cluster), [
                'name' => 'Nome che non deve restare',
                'slug' => 'originale',
                'pillar_article_id' => $pillar->id,
                'membership_ids' => [],
            ])
            ->assertSessionHasErrors('pillar_article_id');

        $cluster->refresh();
        $this->assertSame('Originale', $cluster->name);
        $this->assertSame($pillar->id, $cluster->pillar_article_id);
        $this->assertDatabaseHas('article_content_cluster', [
            'content_cluster_id' => $cluster->id,
            'article_id' => $pillar->id,
        ]);
    }

    public function test_primary_cluster_is_unique_per_article_and_moves_transactionally(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Articolo condiviso');
        $a = ContentCluster::factory()->create(['name' => 'A', 'slug' => 'a']);
        $b = ContentCluster::factory()->create(['name' => 'B', 'slug' => 'b']);
        $service = app(ContentClusterMembershipService::class);

        $service->sync($a, [['article_id' => $article->id, 'position' => 1, 'is_primary' => true]], null);
        $service->sync($b, [['article_id' => $article->id, 'position' => 1, 'is_primary' => true]], null);

        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $a->id, 'is_primary' => false]);
        $this->assertDatabaseHas('article_content_cluster', ['article_id' => $article->id, 'content_cluster_id' => $b->id, 'is_primary' => true]);
        $this->assertSame(1, DB::table('article_content_cluster')->where('article_id', $article->id)->where('is_primary', true)->count());
    }

    public function test_removing_primary_membership_leaves_no_primary_for_that_article(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Primary rimovibile');
        $cluster = ContentCluster::factory()->create();
        $service = app(ContentClusterMembershipService::class);

        $service->sync($cluster, [['article_id' => $article->id, 'is_primary' => true]], null);
        $service->sync($cluster, [], null);

        $this->assertDatabaseMissing('article_content_cluster', [
            'article_id' => $article->id,
            'content_cluster_id' => $cluster->id,
        ]);
        $this->assertSame(0, DB::table('article_content_cluster')->where('article_id', $article->id)->where('is_primary', true)->count());
    }

    public function test_ordering_is_deterministic_for_duplicate_missing_and_removed_positions(): void
    {
        $editor = $this->editor();
        $first = $this->article($editor, 'Ordine A');
        $second = $this->article($editor, 'Ordine B', Article::STATUS_SCHEDULED);
        $third = $this->article($editor, 'Ordine C');
        $cluster = ContentCluster::factory()->create();
        $service = app(ContentClusterMembershipService::class);

        $service->sync($cluster, [
            ['article_id' => $second->id, 'position' => 5],
            ['article_id' => $first->id, 'position' => 5],
            ['article_id' => $third->id],
        ], null);

        $this->assertSame([
            $first->id => 10,
            $second->id => 20,
            $third->id => 30,
        ], DB::table('article_content_cluster')
            ->where('content_cluster_id', $cluster->id)
            ->orderBy('position')
            ->pluck('position', 'article_id')
            ->all());

        $service->sync($cluster, [
            ['article_id' => $third->id, 'position' => 1],
            ['article_id' => $first->id, 'position' => 2],
        ], null);

        $this->assertDatabaseMissing('article_content_cluster', [
            'content_cluster_id' => $cluster->id,
            'article_id' => $second->id,
        ]);
        $this->assertSame([
            $third->id => 10,
            $first->id => 20,
        ], DB::table('article_content_cluster')
            ->where('content_cluster_id', $cluster->id)
            ->orderBy('position')
            ->pluck('position', 'article_id')
            ->all());
    }

    public function test_deleting_article_cascades_membership_and_nulls_pillar(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Pillar eliminabile');
        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [['article_id' => $article->id, 'is_primary' => false]], $article->id);

        $article->delete();

        $this->assertDatabaseMissing('article_content_cluster', ['article_id' => $article->id]);
        $this->assertNull($cluster->fresh()->pillar_article_id);
    }

    public function test_slug_is_unique_but_current_slug_is_valid_on_update(): void
    {
        $editor = $this->editor();
        $existing = ContentCluster::factory()->create(['name' => 'A', 'slug' => 'same']);
        ContentCluster::factory()->create(['name' => 'B', 'slug' => 'other']);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $existing), ['name' => 'A2', 'slug' => 'same'])->assertRedirect();
        $this->assertSame('same', $existing->fresh()->slug);
    }

    public function test_active_and_inactive_scopes_are_distinct_from_article_publication_state(): void
    {
        ContentCluster::factory()->create(['is_active' => true]);
        ContentCluster::factory()->create(['is_active' => false]);

        $this->assertSame(1, ContentCluster::active()->count());
        $this->assertSame(1, ContentCluster::inactive()->count());
    }

    /**
     * Mission 13 — Publication Timeline View. The edit page renders a
     * position-ordered timeline strip showing each member's publish date
     * and any Editorial Order Health flag (Mission 11) — here, a
     * chronological inversion between two members out of date order.
     */
    public function test_edit_page_timeline_shows_publish_dates_and_order_health_flags(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $earlier = $this->article($editor, 'Tappa in posizione bassa', Article::STATUS_PUBLISHED);
        $later = $this->article($editor, 'Tappa in posizione alta', Article::STATUS_PUBLISHED);
        $cluster->articles()->attach($earlier->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($later->id, ['position' => 20, 'is_primary' => false]);
        // earlier position (10) published AFTER later position (20):
        // a chronological inversion, editorial_advisory-level (never blocking).
        $earlier->update(['published_at' => now()]);
        $later->update(['published_at' => now()->subWeek()]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()
            ->assertSee('Timeline di pubblicazione')
            ->assertSee('Posizione 10', false)
            ->assertSee('Posizione 20', false)
            ->assertSee('Fuori ordine cronologico');
    }

    /**
     * Mission 12 — Transition Text Health. The edit form must flag exactly
     * the non-terminal member missing a raccordo, not the terminal one
     * (which legitimately has nothing left to introduce).
     */
    public function test_edit_page_flags_only_the_non_terminal_member_missing_a_transition(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $first = $this->article($editor, 'Prima tappa senza raccordo');
        $last = $this->article($editor, 'Ultima tappa');
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);
        $cluster->articles()->attach($last->id, ['position' => 20, 'is_primary' => false, 'transition_text' => null]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()->assertSee('Manca il raccordo verso la tappa successiva.');

        // Only one such warning: the terminal member is correctly excluded.
        $occurrences = substr_count($response->getContent(), 'Manca il raccordo verso la tappa successiva.');
        $this->assertSame(1, $occurrences);
    }

    /**
     * Missione 17 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "show the effective narrative sequence: A →
     * transition → B. Make broken/missing transitions easy to spot."
     * L'anteprima narrativa è una superficie distinta dalla tabella di
     * modifica (sola lettura, ordine di lettura) e dalla Timeline di
     * pubblicazione (focalizzata sulle date, non sul raccordo).
     */
    public function test_narrative_preview_shows_the_real_transition_text_between_two_steps(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $first = $this->article($editor, 'Capitolo uno');
        $last = $this->article($editor, 'Capitolo due');
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => 'Da qui la storia si sposta sul secondo capitolo.']);
        $cluster->articles()->attach($last->id, ['position' => 20, 'is_primary' => false, 'transition_text' => null]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()
            ->assertSee('Anteprima narrativa')
            ->assertSeeInOrder(['Capitolo uno', 'Da qui la storia si sposta sul secondo capitolo.', 'Capitolo due']);
    }

    public function test_narrative_preview_flags_a_missing_transition_and_excludes_the_terminal_step(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $first = $this->article($editor, 'Tappa senza raccordo nell\'anteprima');
        $last = $this->article($editor, 'Tappa finale nell\'anteprima');
        $cluster->articles()->attach($first->id, ['position' => 10, 'is_primary' => true, 'transition_text' => null]);
        $cluster->articles()->attach($last->id, ['position' => 20, 'is_primary' => false, 'transition_text' => null]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()->assertSee('⚠ Raccordo mancante verso la tappa successiva.', false);

        // Solo la tappa non terminale genera l'avviso nell'anteprima.
        $occurrences = substr_count($response->getContent(), '⚠ Raccordo mancante verso la tappa successiva.');
        $this->assertSame(1, $occurrences);
    }

    /**
     * Missione 20 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "Given scheduled dates, calculate how a path's
     * public prefix is expected to grow over time." Prova la pagina reale,
     * non solo PercorsoPrefixForecastService in isolamento — e che il
     * disclaimer "non è una garanzia" sia effettivamente sulla pagina, non
     * solo nel commento del servizio.
     */
    public function test_edit_page_shows_the_prefix_growth_forecast_for_a_trailing_scheduled_member(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $published = $this->article($editor, 'Tappa già pubblica per la previsione');
        $scheduled = $this->article($editor, 'Tappa programmata per la previsione', Article::STATUS_SCHEDULED);
        $cluster->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($scheduled->id, ['position' => 20, 'is_primary' => false]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()
            ->assertSee('Previsione crescita prefisso pubblico')
            ->assertSee('Tappa programmata per la previsione')
            ->assertSee('Non è una garanzia', false);
    }

    public function test_edit_page_hides_the_prefix_growth_forecast_when_the_next_member_is_a_draft(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $draft = $this->article($editor, 'Bozza subito dopo il prefisso', Article::STATUS_DRAFT);
        $cluster->articles()->attach($draft->id, ['position' => 10, 'is_primary' => true]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()->assertDontSee('Previsione crescita prefisso pubblico');
    }

    /**
     * Missione 15 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "When a complete path gains a hidden/future
     * member, do not auto-reopen. Surface a strong editorial warning."
     * Il fatto era già calcolato (orderHealthForCluster()) ma mai
     * mostrato in modo prominente sulla pagina di modifica — solo come
     * uno tra i flag per-articolo della tabella. Questo test prova la
     * pagina reale, non solo il servizio in isolamento.
     */
    public function test_edit_page_shows_a_prominent_warning_when_a_complete_percorso_gains_a_hidden_member(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $published = $this->article($editor, 'Tappa pubblica', Article::STATUS_PUBLISHED);
        $hidden = $this->article($editor, 'Tappa nascosta aggiunta dopo la conclusione', Article::STATUS_DRAFT);
        $cluster->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($hidden->id, ['position' => 20, 'is_primary' => false]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()->assertSee('Percorso completo con nuove tappe non pubbliche');
    }

    public function test_edit_page_does_not_show_the_hidden_remainder_warning_for_an_updating_percorso(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $published = $this->article($editor, 'Tappa pubblica in aggiornamento', Article::STATUS_PUBLISHED);
        $hidden = $this->article($editor, 'Tappa ancora non pubblica', Article::STATUS_DRAFT);
        $cluster->articles()->attach($published->id, ['position' => 10, 'is_primary' => true]);
        $cluster->articles()->attach($hidden->id, ['position' => 20, 'is_primary' => false]);

        $response = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));

        $response->assertOk()->assertDontSee('Percorso completo con nuove tappe non pubbliche');
    }

    /**
     * Missione 16 (secondo batch autonomo KAIRUS, Fase C — Percorsi
     * Advanced Operations): "unique position validation". Non un rifiuto
     * hard — ContentClusterMembershipService::sync() risolve già
     * posizioni duplicate in modo deterministico (comportamento
     * deliberato, coperto da un test esistente) — ma l'editor deve
     * saperlo, non scoprirlo in silenzio.
     */
    public function test_submitting_duplicate_positions_flashes_a_non_blocking_warning(): void
    {
        $editor = $this->editor();
        $first = $this->article($editor, 'Prima tappa');
        $second = $this->article($editor, 'Seconda tappa');
        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $response = $this->actingAs($editor)
            ->from(route('admin.content-clusters.edit', $cluster))
            ->put(route('admin.content-clusters.memberships.update', $cluster), [
                'membership_ids' => [$first->id, $second->id],
                'memberships' => [
                    $first->id => ['position' => 10],
                    $second->id => ['position' => 10],
                ],
            ]);

        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $response->assertSessionHas('warning');

        $form = $this->actingAs($editor)->get(route('admin.content-clusters.edit', $cluster));
        $form->assertSee('Le posizioni inserite contenevano dei duplicati');
    }

    public function test_submitting_unique_positions_does_not_flash_a_warning(): void
    {
        $editor = $this->editor();
        $first = $this->article($editor, 'Prima tappa unica');
        $second = $this->article($editor, 'Seconda tappa unica');
        $cluster = ContentCluster::factory()->create();
        app(ContentClusterMembershipService::class)->sync($cluster, [
            ['article_id' => $first->id, 'position' => 10],
            ['article_id' => $second->id, 'position' => 20],
        ], null);

        $response = $this->actingAs($editor)
            ->from(route('admin.content-clusters.edit', $cluster))
            ->put(route('admin.content-clusters.memberships.update', $cluster), [
                'membership_ids' => [$first->id, $second->id],
                'memberships' => [
                    $first->id => ['position' => 10],
                    $second->id => ['position' => 30],
                ],
            ]);

        $response->assertRedirect(route('admin.content-clusters.edit', $cluster));
        $response->assertSessionMissing('warning');
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function article(User $user, string $title, string $status = Article::STATUS_PUBLISHED): Article
    {
        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $status === Article::STATUS_SCHEDULED ? now()->addDay() : now()->subDay(),
        ]);
    }
}
