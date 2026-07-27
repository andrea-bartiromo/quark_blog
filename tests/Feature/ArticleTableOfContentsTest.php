<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Services\TableOfContentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTableOfContentsTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => 'intelligenza-artificiale',
            'status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    private function service(): TableOfContentsService
    {
        return app(TableOfContentsService::class);
    }

    // 1. Nessuna TOC con 0 heading
    public function test_no_toc_items_with_zero_headings(): void
    {
        $result = $this->service()->build('<p>Solo testo, nessun titolo.</p>');

        $this->assertSame([], $result['items']);
    }

    // 2. Nessuna TOC con 1 heading
    public function test_no_toc_items_with_a_single_heading(): void
    {
        $result = $this->service()->build('<h2>Unico titolo</h2><p>Testo.</p>');

        $this->assertSame([], $result['items']);
        // L'id viene comunque assegnato al titolo, anche senza TOC visibile.
        $this->assertStringContainsString('id="unico-titolo"', $result['html']);
    }

    // 3. TOC con più h2
    public function test_toc_with_multiple_h2(): void
    {
        $result = $this->service()->build('<h2>Primo</h2><p>Testo.</p><h2>Secondo</h2><p>Testo.</p>');

        $this->assertCount(2, $result['items']);
        $this->assertSame('primo', $result['items'][0]['id']);
        $this->assertSame('secondo', $result['items'][1]['id']);
        $this->assertSame([], $result['items'][0]['children']);
    }

    // 4. TOC con h2+h3 (annidamento)
    public function test_toc_nests_h3_under_the_preceding_h2(): void
    {
        $html = '<h2>Perché sono importanti</h2><h3>Biodiversità</h3><h3>Agricoltura</h3>';
        $result = $this->service()->build($html);

        $this->assertCount(1, $result['items']);
        $this->assertSame('perche-sono-importanti', $result['items'][0]['id']);
        $this->assertCount(2, $result['items'][0]['children']);
        $this->assertSame('biodiversita', $result['items'][0]['children'][0]['id']);
        $this->assertSame('agricoltura', $result['items'][0]['children'][1]['id']);
        $this->assertSame(3, $result['items'][0]['children'][0]['level']);
    }

    // 5. Id già presente: non va modificato
    public function test_an_existing_id_is_not_modified(): void
    {
        $html = '<h2 id="id-personalizzato">Titolo con id</h2><h2>Altro titolo</h2>';
        $result = $this->service()->build($html);

        $this->assertStringContainsString('<h2 id="id-personalizzato">Titolo con id</h2>', $result['html']);
        $this->assertSame('id-personalizzato', $result['items'][0]['id']);
    }

    // 6. Id generato automaticamente quando assente
    public function test_an_id_is_generated_automatically_when_missing(): void
    {
        $html = '<h2>Come proteggere le api</h2><h2>Altro titolo</h2>';
        $result = $this->service()->build($html);

        $this->assertStringContainsString('id="come-proteggere-le-api"', $result['html']);
        $this->assertSame('come-proteggere-le-api', $result['items'][0]['id']);
    }

    // 7. Slug con caratteri accentati
    public function test_slug_handles_accented_characters(): void
    {
        $html = '<h2>Perché è così importante?</h2><h2>Altro</h2>';
        $result = $this->service()->build($html);

        $this->assertSame('perche-e-cosi-importante', $result['items'][0]['id']);
    }

    // 8. Slug con caratteri speciali
    public function test_slug_strips_special_characters(): void
    {
        $html = '<h2>Domande & Risposte: cosa sapere?!</h2><h2>Altro</h2>';
        $result = $this->service()->build($html);

        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $result['items'][0]['id']);
        $this->assertSame('domande-risposte-cosa-sapere', $result['items'][0]['id']);
    }

    // 9. Nessun id duplicato
    public function test_no_duplicate_ids_when_headings_share_the_same_text(): void
    {
        $html = '<h2>Titolo ripetuto</h2><h2>Titolo ripetuto</h2><h2>Titolo ripetuto</h2>';
        $result = $this->service()->build($html);

        $ids = array_column($result['items'], 'id');
        $this->assertSame(['titolo-ripetuto', 'titolo-ripetuto-2', 'titolo-ripetuto-3'], $ids);
        $this->assertSame($ids, array_unique($ids));
    }

    public function test_auto_generated_id_does_not_collide_with_an_earlier_explicit_id(): void
    {
        $html = '<h2 id="titolo-ripetuto">Primo</h2><h2>Titolo ripetuto</h2>';
        $result = $this->service()->build($html);

        $this->assertSame('titolo-ripetuto', $result['items'][0]['id']);
        $this->assertSame('titolo-ripetuto-2', $result['items'][1]['id']);
    }

    // 10. Rendering corretto sulla pagina pubblica dell'articolo
    public function test_public_page_does_not_render_a_toc_with_a_single_heading(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'body' => '<h2>Unico titolo</h2><p>Testo.</p>',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('aria-label="Indice articolo"', false);
        $response->assertSee('id="unico-titolo"', false);
    }

    public function test_public_page_renders_a_toc_with_two_or_more_headings(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'body' => '<h2>Come proteggere le api</h2><p>Testo.</p><h2>Perché sono importanti</h2><h3>Biodiversità</h3>',
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'aria-label="Indice articolo"'));
        $response->assertSee('href="#come-proteggere-le-api"', false);
        $response->assertSee('href="#perche-sono-importanti"', false);
        $response->assertSee('href="#biodiversita"', false);
        $response->assertSee('<h2 id="come-proteggere-le-api">Come proteggere le api</h2>', false);
        $response->assertSee('<h3 id="biodiversita">Biodiversità</h3>', false);
    }

    public function test_toc_is_not_rendered_for_articles_without_html_headings(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'body' => "Primo paragrafo semplice.\n\nSecondo paragrafo semplice.",
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertDontSee('aria-label="Indice articolo"', false);
    }

    public function test_toc_does_not_include_the_sources_section_heading(): void
    {
        $article = $this->publishedArticle($this->author(), [
            'body' => "<h2>Primo</h2><p>Testo.</p><h2>Secondo</h2>\n---\nFonte: NASA",
        ]);

        $response = $this->get(route('articolo', $article->slug));

        $response->assertOk();
        $response->assertSee('href="#primo"', false);
        $response->assertSee('href="#secondo"', false);
        // "Fonti" e' un h3 generato a parte da $sources, non deve comparire nell'indice.
        $response->assertDontSee('href="#fonti"', false);
    }

    // Il contenuto salvato sul DB non viene mai modificato
    public function test_saved_body_in_the_database_is_never_modified(): void
    {
        $rawBody = '<h2>Titolo senza id</h2><p>Testo.</p><h2>Altro titolo</h2>';
        $article = $this->publishedArticle($this->author(), ['body' => $rawBody]);

        $this->get(route('articolo', $article->slug))->assertOk();

        $this->assertSame($rawBody, $article->fresh()->body);
    }
}
