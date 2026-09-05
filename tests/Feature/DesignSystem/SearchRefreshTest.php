<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere G — Search Visual Adoption (Prompt 161).
 *
 * Congela, PRIMA di qualunque modifica di markup/CSS, i comportamenti di
 * /ricerca non già coperti in dettaglio da SearchControllerTest: i tre
 * stati della vista, robots noindex, escaping della query, e il
 * contratto del form (metodo/azione/nomi dei campi).
 */
class SearchRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Onde gravitazionali osservate',
            'slug' => 'articolo-'.uniqid(),
            'excerpt' => 'Sommario.',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['slug' => 'fisica'], ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]);
    }

    public function test_initial_state_has_a_single_h1_and_the_soft_empty_state(): void
    {
        $html = $this->get(route('ricerca'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('Inizia una ricerca', $html);
    }

    public function test_results_state_lists_matching_articles(): void
    {
        $article = $this->article(['title' => 'Onde gravitazionali rilevate di nuovo']);

        $html = $this->get(route('ricerca', ['q' => 'gravitazionali']))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('articolo', $article->slug).'"', $html);
        $this->assertStringContainsString($article->title, $html);
    }

    public function test_no_results_state_offers_a_reset_link(): void
    {
        $html = $this->get(route('ricerca', ['q' => 'querystringchemainoncorrisponde']))->assertOk()->getContent();

        $this->assertStringContainsString('Nessun risultato trovato', $html);
        $this->assertStringContainsString('href="'.route('ricerca').'"', $html);
    }

    public function test_search_page_is_never_indexable(): void
    {
        $html = $this->get(route('ricerca'))->assertOk()->getContent();

        $this->assertStringContainsString('name="robots" content="noindex,follow"', $html);
    }

    public function test_query_is_escaped_in_title_and_heading(): void
    {
        $html = $this->get(route('ricerca', ['q' => '<script>alert(1)</script>']))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_search_form_method_action_and_field_names_are_unchanged(): void
    {
        $html = $this->get(route('ricerca'))->assertOk()->getContent();

        preg_match('/<form method="GET" action="[^"]*"[^>]*>.*?<\/form>/s', $html, $formMatch);
        $form = $formMatch[0] ?? '';
        $this->assertNotSame('', $form, 'Form di ricerca non trovato.');

        $this->assertStringContainsString('action="'.route('ricerca').'"', $form);
        $this->assertStringContainsString('name="q"', $form);
        $this->assertStringContainsString('name="categoria"', $form);
        $this->assertStringContainsString('name="autore"', $form);
        $this->assertStringContainsString('name="da"', $form);
        $this->assertStringContainsString('name="a"', $form);
    }
}
