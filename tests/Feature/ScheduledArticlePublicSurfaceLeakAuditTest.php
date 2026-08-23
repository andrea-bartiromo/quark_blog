<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Article Calendar V1 — FASE 10 "Public Date Leak Audit". Un articolo
 * 'scheduled' non deve mai comparire come contenuto pubblicamente
 * raggiungibile prima della data programmata, su NESSUNA superficie
 * pubblica. tests/Feature/ScheduledArticleVisibilityTest.php copre già
 * pagina articolo, home, categoria/indice, pagina autore, sitemap, news
 * sitemap e feed RSS: questo file chiude le superfici rimanenti elencate
 * dal brief della missione — correlati, "Continua da qui", Percorsi e
 * ricerca — dove il rischio non è "la pagina dell'articolo programmato è
 * raggiungibile" ma "l'articolo programmato appare come suggerimento o
 * risultato su una pagina di un ALTRO articolo, già pubblico".
 *
 * Tutti e quattro i motori auditati (ArticleRelatedService,
 * ArticleContinuationService, ArticlePathNavigation, ArticleSearchService)
 * risultavano già costruiti sopra Article::published()/scopePublished ad
 * ogni query rilevante (verificato leggendo il codice prima di scrivere
 * questi test) — qui si aggiunge la prova end-to-end mancante, non una
 * correzione: nessun bug di prodotto trovato in questo audit.
 */
class ScheduledArticlePublicSurfaceLeakAuditTest extends TestCase
{
    use RefreshDatabase;

    private function author(): User
    {
        return User::factory()->create(['role' => 'author']);
    }

    private function article(string $title, string $status, $publishedAt, string $category = 'energia'): Article
    {
        return Article::create([
            'user_id' => $this->author()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => 'Corpo articolo.',
            'category' => $category,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }

    public function test_scheduled_article_never_appears_in_related_articles_of_a_public_sibling(): void
    {
        $public = $this->article('Correlato pubblico', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Correlato programmato', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('articolo', $public->slug));

        $response->assertOk();
        $response->assertDontSee('Correlato programmato');
    }

    public function test_scheduled_article_is_never_offered_as_a_continua_da_qui_candidate(): void
    {
        $public = $this->article('Prosecuzione pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        // Unico altro articolo della stessa categoria: se il motore non
        // filtrasse per pubblicazione, sarebbe l'unico candidato possibile.
        $scheduled = $this->article('Prosecuzione programmata', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('articolo', $public->slug));

        $response->assertOk();
        $response->assertDontSee('Prosecuzione programmata');
        $response->assertDontSee('Continua da qui');
    }

    public function test_scheduled_article_never_leaks_as_the_next_step_of_an_active_percorso(): void
    {
        $current = $this->article('Tappa corrente pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduledNext = $this->article('Tappa futura programmata', Article::STATUS_SCHEDULED, now()->addDay());

        $cluster = ContentCluster::factory()->create(['is_active' => true]);
        $cluster->articles()->attach([
            $current->id => ['position' => 10, 'is_primary' => true],
            $scheduledNext->id => ['position' => 20, 'is_primary' => false],
        ]);

        $response = $this->get(route('articolo', $current->slug));

        $response->assertOk();
        $response->assertDontSee('Tappa futura programmata');
        $response->assertDontSee('Successivo');
    }

    public function test_scheduled_article_never_leaks_on_the_public_percorso_page(): void
    {
        $public = $this->article('Tappa percorso pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Tappa percorso programmata', Article::STATUS_SCHEDULED, now()->addDay());

        $cluster = ContentCluster::factory()->create(['is_active' => true, 'slug' => 'percorso-audit-date-leak']);
        $cluster->articles()->attach([
            $public->id => ['position' => 10, 'is_primary' => true],
            $scheduled->id => ['position' => 20, 'is_primary' => false],
        ]);

        $response = $this->get(route('percorsi.show', $cluster->slug));

        $response->assertOk();
        $response->assertDontSee('Tappa percorso programmata');
    }

    public function test_scheduled_article_never_appears_in_search_results(): void
    {
        $public = $this->article('Fisica quantistica pubblica', Article::STATUS_PUBLISHED, now()->subDay());
        $scheduled = $this->article('Fisica quantistica programmata', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('ricerca', ['q' => 'Fisica quantistica']));

        $response->assertOk();
        $response->assertSee('Fisica quantistica pubblica');
        $response->assertDontSee('Fisica quantistica programmata');
    }

    /**
     * Article Calendar V2: la superficie "structured data" esplicitamente
     * richiesta dalla missione. La pagina di un articolo programmato è già
     * provata non raggiungibile (ScheduledArticleVisibilityTest), quindi
     * il suo JSON-LD NewsArticle non può renderizzare — questo test lo
     * verifica direttamente invece di dedurlo, per chiudere anche questa
     * superficie in modo esplicito, non solo per inferenza.
     */
    public function test_scheduled_article_never_exposes_newsarticle_structured_data(): void
    {
        $scheduled = $this->article('Struttura dati programmata', Article::STATUS_SCHEDULED, now()->addDay());

        $response = $this->get(route('articolo', $scheduled->slug));

        $response->assertDontSee('"@type":"NewsArticle"', false);
        $response->assertDontSee('Struttura dati programmata');
    }
}
