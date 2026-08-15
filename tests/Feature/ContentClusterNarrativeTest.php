<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ContentClusterMembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentClusterNarrativeTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_save_optional_narrative_without_duplicating_existing_path_semantics(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Fondamenti del tema');
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso generico',
            'slug' => 'percorso-generico',
            'short_description' => 'Introduzione esistente.',
        ]);
        app(ContentClusterMembershipService::class)->sync($cluster, [[
            'article_id' => $article->id,
            'position' => 10,
            'is_primary' => true,
        ]], $article->id);

        $this->actingAs($editor)->put(route('admin.content-clusters.update', $cluster), [
            'name' => $cluster->name,
            'slug' => $cluster->slug,
            'short_description' => $cluster->short_description,
            'takeaways' => ['Capire il contesto', '', 'Distinguere i passaggi'],
            'guiding_questions' => ['Da dove nasce il problema?', 'Come cambia nel tempo?'],
            'closing_title' => 'Una mappa da continuare',
            'closing_text' => 'Il percorso resta aperto a nuovi approfondimenti.',
        ])->assertRedirect();

        $cluster->refresh();
        $this->assertSame(['Capire il contesto', 'Distinguere i passaggi'], $cluster->takeaways);
        $this->assertSame(['Da dove nasce il problema?', 'Come cambia nel tempo?'], $cluster->guiding_questions);
        $this->assertSame('Una mappa da continuare', $cluster->closing_title);
        $this->assertSame($article->id, $cluster->pillar_article_id);
        $this->assertTrue((bool) $cluster->articles()->firstOrFail()->pivot->is_primary);
        $this->assertSame(10, (int) $cluster->articles()->firstOrFail()->pivot->position);
    }

    public function test_membership_transition_is_optional_and_preserves_position_primary_and_pillar(): void
    {
        $editor = $this->editor();
        $article = $this->article($editor, 'Prima tappa');
        $cluster = ContentCluster::factory()->create();

        $this->actingAs($editor)->put(route('admin.content-clusters.memberships.update', $cluster), [
            'membership_ids' => [$article->id],
            'pillar_article_id' => $article->id,
            'memberships' => [
                $article->id => [
                    'position' => 30,
                    'is_primary' => '1',
                    'transition_text' => 'Ora possiamo passare alla domanda successiva.',
                ],
            ],
        ])->assertRedirect();

        $cluster->refresh()->load('articles');
        $membership = $cluster->articles->firstOrFail()->pivot;
        $this->assertSame(10, (int) $membership->position);
        $this->assertTrue((bool) $membership->is_primary);
        $this->assertSame('Ora possiamo passare alla domanda successiva.', $membership->transition_text);
        $this->assertSame($article->id, $cluster->pillar_article_id);
    }

    public function test_legacy_path_renders_without_empty_optional_narrative_sections(): void
    {
        $cluster = ContentCluster::factory()->create([
            'name' => 'Percorso legacy',
            'slug' => 'percorso-legacy',
            'short_description' => 'Descrizione legacy.',
            'is_active' => true,
            'takeaways' => null,
            'guiding_questions' => null,
            'closing_title' => null,
            'closing_text' => null,
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertSee('Perché questo percorso');
        $response->assertDontSee('I punti da portare con te');
        $response->assertDontSee('Una mappa fatta di domande');
        $response->assertSee('Fine del percorso');
    }

    public function test_narrative_validation_preserves_old_input(): void
    {
        $editor = $this->editor();
        $cluster = ContentCluster::factory()->create();
        $tooLong = str_repeat('x', 321);

        $this->actingAs($editor)
            ->from(route('admin.content-clusters.edit', $cluster))
            ->put(route('admin.content-clusters.update', $cluster), [
                'name' => $cluster->name,
                'slug' => $cluster->slug,
                'takeaways' => [$tooLong],
                'guiding_questions' => ['Domanda da preservare?'],
            ])
            ->assertSessionHasErrors('takeaways.0');

        $this->actingAs($editor)
            ->get(route('admin.content-clusters.edit', $cluster))
            ->assertSee('Domanda da preservare?');
    }

    public function test_public_narrative_is_generic_and_contains_no_ai_specific_template_copy(): void
    {
        $view = file_get_contents(resource_path('views/content-clusters/show.blade.php'));

        $this->assertStringNotContainsString("slug == 'intelligenza-artificiale'", $view);
        $this->assertStringNotContainsString('GPT-5', $view);
        $this->assertStringNotContainsString('Intelligenza Artificiale', $view);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function article(User $user, string $title): Article
    {
        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => str($title)->slug(),
            'body' => 'Corpo.',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_PUBLISHED,
            'read_minutes' => 1,
            'published_at' => now()->subDay(),
        ]);
    }
}
