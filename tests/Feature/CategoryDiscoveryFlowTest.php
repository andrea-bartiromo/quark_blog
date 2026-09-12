<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 1 (programma 100-cantieri Kairus): nuovo flusso UX delle pagine
 * categoria — chip Argomenti, newsletter CTA a metà griglia, blocco finale
 * "Continua a esplorare" (Più letti + categorie correlate). Test di
 * composizione mirati a questo cantiere; il Cantiere 6 aggiunge copertura
 * browser dedicata su ordine visivo e responsive.
 */
class CategoryDiscoveryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function publishedArticle(string $category, array $overrides = []): Article
    {
        return Article::create(array_merge([
            'user_id' => $this->author()->id,
            'title' => 'Articolo di prova '.uniqid(),
            'slug' => 'articolo-di-prova-'.uniqid(),
            'excerpt' => 'Sommario di prova',
            'body' => '<p>Corpo articolo di prova.</p>',
            'category' => $category,
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'read_minutes' => 3,
            'views' => 0,
        ], $overrides));
    }

    /**
     * Il blocco "Continua a esplorare" è l'unica <section
     * class="kairus-continue-exploring"> della pagina: isolarne il markup
     * (fino alla sua chiusura, non fino alla fine del documento) evita che
     * link legittimi altrove nella pagina — es. il footer globale, che
     * elenca TUTTE le categorie incluse quella corrente — producano un
     * falso positivo/negativo nelle asserzioni su questo blocco.
     */
    private function continueExploringSection(string $content): string
    {
        $start = strpos($content, '<section class="kairus-continue-exploring">');
        $this->assertNotFalse($start, 'Blocco "Continua a esplorare" non trovato in pagina.');

        $end = strpos($content, '</section>', $start);
        $this->assertNotFalse($end);

        return substr($content, $start, $end - $start);
    }

    public function test_topic_chips_list_every_public_category_with_the_current_one_marked_active(): void
    {
        $this->publishedArticle('energia');

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertSee('<nav class="public-pill-row" aria-label="Argomenti">', false);
        $response->assertSee('href="'.route('categoria', 'energia').'" class="active" aria-current="page"', false);
        // Un'altra categoria pubblica compare come link non attivo, senza
        // classe/aria-current.
        $response->assertSee('href="'.route('categoria', 'intelligenza-artificiale').'">Intelligenza Artificiale</a>', false);
    }

    public function test_topic_chips_never_include_a_draft_or_scheduled_category(): void
    {
        $this->publishedArticle('energia');

        $draft = Category::create([
            'name' => 'Bozza Nascosta',
            'slug' => 'bozza-nascosta',
            'is_active' => true,
            'status' => Category::STATUS_DRAFT,
        ]);

        $scheduled = Category::create([
            'name' => 'Programmata Futura',
            'slug' => 'programmata-futura',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertDontSee('href="'.route('categoria', $draft->slug).'"', false);
        $response->assertDontSee('href="'.route('categoria', $scheduled->slug).'"', false);
    }

    public function test_newsletter_cta_renders_after_exactly_the_third_card_when_more_articles_follow(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('categoria', 'energia'));
        $content = $response->getContent();

        $response->assertOk();

        $ctaPosition = strpos($content, 'kairus-category-newsletter-slot');
        $this->assertNotFalse($ctaPosition, 'La CTA newsletter non compare in pagina con più di 3 articoli.');

        // Ogni card articolo apre con class="kairus-article-card " seguito
        // da altre classi (vedi components/kairus/article-card.blade.php);
        // lo spazio finale nell'ago di ricerca esclude le sottoclassi BEM
        // dello stesso componente (kairus-article-card__media, __body,
        // __title, __excerpt), che altrimenti farebbero anch'esse match
        // sullo stesso prefisso testuale.
        $cardsBeforeCta = substr_count(substr($content, 0, $ctaPosition), 'class="kairus-article-card ');
        $this->assertSame(3, $cardsBeforeCta, 'La CTA newsletter deve comparire subito dopo esattamente 3 card.');

        $totalCards = substr_count($content, 'class="kairus-article-card ');
        $this->assertSame(4, $totalCards);
    }

    public function test_newsletter_cta_is_absent_when_the_page_has_three_or_fewer_articles(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->publishedArticle('energia', ['published_at' => now()->subMinutes($i)]);
        }

        $response = $this->get(route('categoria', 'energia'));

        $response->assertOk();
        $response->assertDontSee('kairus-category-newsletter-slot', false);
    }

    public function test_continue_exploring_most_read_excludes_articles_already_shown_on_the_page(): void
    {
        $onPage = $this->publishedArticle('energia', ['views' => 500, 'title' => 'Articolo Sulla Pagina']);
        $excluded = $this->publishedArticle('salute', ['views' => 900, 'title' => 'Il Più Letto Di Tutti']);

        $response = $this->get(route('categoria', 'energia'));
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('Continua a esplorare');
        $response->assertSee($excluded->title);

        // L'articolo mostrato nella griglia principale non deve comparire
        // una seconda volta nel blocco Più letti.
        $this->assertStringNotContainsString($onPage->title, $this->continueExploringSection($content));
    }

    public function test_continue_exploring_related_categories_exclude_current_and_non_public_categories(): void
    {
        $this->publishedArticle('energia');

        $scheduled = Category::create([
            'name' => 'Programmata Futura',
            'slug' => 'programmata-futura-2',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addWeek(),
        ]);

        $response = $this->get(route('categoria', 'energia'));
        $content = $response->getContent();

        $response->assertOk();
        $relatedSection = $this->continueExploringSection($content);

        $this->assertStringContainsString(route('categoria', 'intelligenza-artificiale'), $relatedSection);
        $this->assertStringNotContainsString('href="'.route('categoria', 'energia').'"', $relatedSection);
        $this->assertStringNotContainsString(route('categoria', $scheduled->slug), $relatedSection);
    }
}
