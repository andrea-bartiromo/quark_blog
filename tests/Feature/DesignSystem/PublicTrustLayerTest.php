<?php

namespace Tests\Feature\DesignSystem;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere H — Public Trust Layer (Prompt 185).
 *
 * Congela il comportamento per presenza/assenza/incompletezza di ogni
 * dato di fiducia realmente disponibile sulla pagina articolo oggi:
 * bio/social autore, crediti cover, e la conferma che nessun dato NON
 * disponibile (data di aggiornamento, revisioni, metodologia,
 * disclosure) venga mai mostrato o inventato.
 */
class PublicTrustLayerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['slug' => 'fisica'], ['name' => 'Fisica', 'is_active' => true, 'sort_order' => 0]);
    }

    private function article(User $author, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $author->id,
            'title' => 'Articolo di prova',
            'slug' => 'articolo-'.uniqid(),
            'excerpt' => 'Sommario.',
            'body' => '<p>Corpo.</p>',
            'category' => 'fisica',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'read_minutes' => 4,
        ], $overrides));
    }

    public function test_author_bio_and_social_render_when_present(): void
    {
        $author = User::factory()->create([
            'role' => 'editor',
            'name' => 'Autrice Completa',
            'bio' => 'Fisica teorica, scrive di cosmologia e meccanica quantistica.',
            // Salvato come handle (stesso formato già in uso in
            // autore.blade.php: "@nomeutente"), mai un URL completo —
            // la trasformazione in URL avviene solo in fase di rendering.
            'twitter' => '@autricecompleta',
            'linkedin' => 'https://linkedin.com/in/autricecompleta',
        ]);
        $article = $this->article($author);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Fisica teorica, scrive di cosmologia e meccanica quantistica.', $html);
        $this->assertStringContainsString('href="https://twitter.com/autricecompleta"', $html);
        $this->assertStringContainsString('href="https://linkedin.com/in/autricecompleta"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_author_minimal_renders_without_empty_boxes(): void
    {
        $author = User::factory()->create(['role' => 'editor', 'name' => 'Autore Minimo', 'bio' => null, 'twitter' => null, 'linkedin' => null]);
        $article = $this->article($author);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Autore Minimo', $html);
        // Nessun blocco vuoto: se la bio non c'è, il suo contenitore non deve comparire.
        $this->assertStringNotContainsString('kairus-author-card__bio', $html);
    }

    public function test_only_the_social_links_that_are_filled_appear(): void
    {
        $author = User::factory()->create(['role' => 'editor', 'name' => 'Solo Twitter', 'twitter' => '@solotwitter', 'linkedin' => null]);
        $article = $this->article($author);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('href="https://twitter.com/solotwitter"', $html);
        // "linkedin.com" compare comunque nel footer/share-card
        // sitewide (LinkedIn di Kairus, non dell'autore) — la verifica
        // corretta è che l'URL personale dell'autore non compaia.
        $this->assertStringNotContainsString('href="https://linkedin.com/in/', $html);
    }

    public function test_cover_credits_render_when_present(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $article = $this->article($author, [
            'cover_image' => 'articles/covers/prova.webp',
            'cover_caption' => 'Una didascalia di prova.',
            'cover_credit' => 'Credito di prova',
            'cover_source' => 'Fonte di prova',
            'cover_license' => 'CC BY 4.0',
        ]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Una didascalia di prova.', $html);
        $this->assertStringContainsString('Credito di prova', $html);
        $this->assertStringContainsString('Fonte di prova', $html);
        $this->assertStringContainsString('CC BY 4.0', $html);
    }

    public function test_no_update_date_is_ever_shown(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $article = $this->article($author);
        // updated_at diverso da published_at (com'è sempre nella pratica:
        // ogni save successivo lo tocca) — non deve MAI apparire come
        // "aggiornato il" da nessuna parte della pagina.
        $article->forceFill(['updated_at' => now()])->saveQuietly();

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('Aggiornato il', $html);
        $this->assertStringNotContainsString('dateModified', $html);
    }

    public function test_no_revision_methodology_or_disclosure_claims_appear(): void
    {
        $author = User::factory()->create(['role' => 'editor']);
        $article = $this->article($author);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('/metodologia', $html);
        $this->assertStringNotContainsString('Revisionato', $html);
        $this->assertStringNotContainsString('Sponsorizzato', $html);
    }

    public function test_person_structured_data_stays_minimal(): void
    {
        $author = User::factory()->create(['role' => 'editor', 'name' => 'Autrice JSON-LD', 'bio' => 'Una bio reale.']);
        $article = $this->article($author);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        // Decodifica reale del JSON-LD invece di un regex fragile sul
        // testo pretty-printed: individua il nodo NewsArticle nel
        // @graph e ne isola il campo "author".
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $scriptMatch);
        $this->assertNotSame('', $scriptMatch[1] ?? '', 'JSON-LD non trovato.');
        $data = json_decode($scriptMatch[1], true);
        $newsArticle = collect($data['@graph'])->firstWhere('@type', 'NewsArticle');
        $this->assertNotNull($newsArticle, 'Nodo NewsArticle non trovato.');
        $person = $newsArticle['author'];

        $this->assertSame('Person', $person['@type']);
        $this->assertArrayNotHasKey('sameAs', $person);
        $this->assertArrayNotHasKey('jobTitle', $person);
        $this->assertArrayNotHasKey('description', $person, 'La bio non deve entrare nel Person minimale.');
        $this->assertSame(['@type', 'name', 'url'], array_keys($person), 'Il Person deve restare minimale: solo @type/name/url.');
    }

    // ---- Fallback completi e nessun blocco vuoto (Prompt 204-206) ----

    /**
     * Combinazione completa: autore con bio+social, cover con tutti i
     * crediti, fonti presenti — tutto insieme sulla stessa pagina, senza
     * che un blocco interferisca con un altro.
     */
    public function test_full_combination_of_trust_elements_renders_together(): void
    {
        $author = User::factory()->create([
            'role' => 'editor',
            'name' => 'Autrice Combinazione Completa',
            'bio' => 'Bio completa di prova.',
            'twitter' => '@combinazione',
            'linkedin' => 'https://linkedin.com/in/combinazione',
        ]);
        $article = $this->article($author, [
            'body' => "<p>Corpo.</p>\n---\nFonte combinata: https://example.com/combinata",
            'cover_caption' => 'Didascalia combinata.',
            'cover_credit' => 'Credito combinato',
            'cover_source' => 'Fonte cover combinata',
            'cover_license' => 'CC BY 4.0',
        ]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringContainsString('Bio completa di prova.', $html);
        $this->assertStringContainsString('href="https://twitter.com/combinazione"', $html);
        $this->assertStringContainsString('href="https://linkedin.com/in/combinazione"', $html);
        $this->assertStringContainsString('Didascalia combinata.', $html);
        $this->assertStringContainsString('Credito combinato', $html);
        $this->assertStringContainsString('CC BY 4.0', $html);
        $this->assertStringContainsString('Fonte combinata: https://example.com/combinata', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    /**
     * Nessun dato di fiducia disponibile: nessun pannello Fonti, nessuna
     * bio, nessun social, nessun blocco crediti cover — e soprattutto
     * nessuna intestazione o pannello orfano lasciato vuoto.
     */
    public function test_no_trust_blocks_are_orphaned_when_no_trust_data_exists(): void
    {
        $author = User::factory()->create(['role' => 'editor', 'name' => 'Autore Senza Fiducia Estesa', 'bio' => null, 'twitter' => null, 'linkedin' => null]);
        $article = $this->article($author, [
            'body' => '<p>Corpo senza delimitatore fonti.</p>',
            'cover_caption' => null,
            'cover_credit' => null,
            'cover_source' => null,
            'cover_license' => null,
        ]);

        $html = $this->get(route('articolo', $article->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('kairus-trust-panel', $html);
        $this->assertStringNotContainsString('kairus-author-card__bio', $html);
        $this->assertStringNotContainsString('kairus-author-card__social', $html);
        $this->assertStringNotContainsString('cover-info', $html);
        // L'autore minimo resta comunque presente (nome + link profilo).
        $this->assertStringContainsString('Autore Senza Fiducia Estesa', $html);
    }
}
