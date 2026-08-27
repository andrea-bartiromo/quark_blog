<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleContinueReadingUiTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    public function test_renders_the_category_fallback_candidate_when_no_path_exists(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $candidate = $this->article('Prosecuzione suggerita', 'fisica');

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertSee('id="continue-reading-title"', false);
        $response->assertSee('Continua da qui');
        $response->assertSee($candidate->title);
        $response->assertSee(route('articolo', $candidate->slug), false);
    }

    public function test_category_fallback_destination_is_not_duplicated_in_related_articles(): void
    {
        $current = $this->article('Corrente senza duplicati', 'fisica', now()->subDays(3));
        $candidate = $this->article('Prosecuzione unica', 'fisica', now());
        $this->article('Altro correlato', 'fisica', now()->subDay());

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertSee('id="continue-reading-title"', false);

        preg_match_all(
            '/<a\b[^>]*href="[^"]*\/articolo\/'.preg_quote($candidate->slug, '/').'[^"]*"/i',
            $response->getContent(),
            $matches
        );

        $this->assertCount(1, $matches[0], 'La destinazione della CTA non deve ricomparire nei correlati.');
    }

    public function test_does_not_render_when_there_is_no_candidate_at_all(): void
    {
        $current = $this->article('Unico articolo del sito', 'fisica');

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertDontSee('id="continue-reading-title"', false);
        $response->assertDontSee('Continua da qui');
    }

    public function test_does_not_render_when_a_percorso_next_already_exists(): void
    {
        // ArticleContinuationService da' sempre priorita' al Percorso: il
        // candidato coinciderebbe esattamente con quello gia' mostrato da
        // path-continuation ("Successivo") -- il modulo "Continua da qui"
        // deve restare silenzioso per non duplicare la stessa CTA due
        // volte nella stessa pagina.
        $current = $this->article('Corrente', 'fisica');
        $pathNext = $this->article('Tappa successiva del percorso', 'fisica');
        $this->article('Piu recente ma fuori percorso', 'fisica', now());

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $pathNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertSee('Successivo');
        $response->assertSee($pathNext->title);
        $response->assertDontSee('id="continue-reading-title"', false);
        $response->assertDontSee('Continua da qui');

        // Il titolo del next-Percorso non deve comparire una seconda
        // volta in un secondo blocco di navigazione.
        $response->assertSeeInOrder([$pathNext->title], false);
    }

    public function test_renders_the_category_fallback_when_the_percorso_has_no_next(): void
    {
        $first = $this->article('Prima tappa', 'fisica');
        $last = $this->article('Ultima tappa', 'fisica');
        $fallback = $this->article('Affinita categoria', 'fisica');

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => false],
            $last->id => ['position' => 20, 'is_primary' => true],
        ]);

        $response = $this->get(route('articolo', $last->slug));

        $response->assertOk();
        $response->assertSee('id="continue-reading-title"', false);
        $response->assertSee('Continua da qui');
        $response->assertSee($fallback->title);
    }

    public function test_draft_or_scheduled_only_candidate_never_renders(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->articleWithStatus('Bozza', 'fisica', Article::STATUS_DRAFT, null);
        $this->articleWithStatus('Programmato', 'fisica', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertDontSee('id="continue-reading-title"', false);
        $response->assertDontSee('Bozza');
        $response->assertDontSee('Programmato');
    }

    public function test_the_card_carries_a_responsive_image_and_no_extra_javascript_is_required(): void
    {
        $current = $this->article('Corrente', 'fisica');
        $this->article('Prosecuzione suggerita', 'fisica');

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertSee('continue-reading__media', false);
        // La card e' un semplice <a>: nessuno script dedicato necessario.
        $response->assertDontSee('continue-reading.js', false);
    }

    private function article(string $title, string $category, $publishedAt = null): Article
    {
        return $this->articleWithStatus($title, $category, Article::STATUS_PUBLISHED, $publishedAt ?? now()->subMinute());
    }

    private function articleWithStatus(string $title, string $category, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto di prova.',
            'category' => $category,
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt,
        ]);
    }
}
