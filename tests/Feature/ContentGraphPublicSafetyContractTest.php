<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ConceptController;
use App\Http\Controllers\Admin\ConceptQuestionController;
use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Concept;
use App\Models\ConceptQuestion;
use App\Models\User;
use App\Services\ContentGraph\ContentGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mission 06 — Content Graph Public Safety Contract.
 *
 * Il Content Graph (docs/CONTENT_GRAPH_V1.md, PR #302) non ha oggi ALCUN
 * consumer pubblico: ogni route che tocca Concept/ConceptAlias/
 * ArticleConcept/ConceptQuestion vive sotto /admin, dietro la stessa
 * middleware 'editor' (vedi routes/content-graph-admin.php e le due nuove
 * route admin.articles.concepts.link/unlink introdotte dalla Mission 05,
 * PR #328). Questo file non inventa una superficie pubblica che non
 * esiste — verifica e blocca, con test di regressione, le garanzie che
 * QUALUNQUE futuro consumer pubblico dovrà rispettare, così che una
 * violazione futura (una nuova route, un nuovo include Blade, una query
 * che dimentica published()) fallisca qui prima di raggiungere produzione.
 *
 * Molte di queste garanzie hanno già una prima prova in
 * ContentGraphFoundationTest (PR #302): qui si aggiungono gli angoli
 * ancora scoperti (query budget, stato REVIEW come target, published_at
 * futuro con status già 'published', assenza di ANY route pubblica non
 * gated, invarianza degli attributi di pubblicazione dell'Article durante
 * le mutazioni del grafo) organizzati esplicitamente per garanzia.
 */
class ContentGraphPublicSafetyContractTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create();
    }

    // ── Garanzia: nessuna route pubblica tocca il Content Graph ─────

    public function test_no_route_touching_the_content_graph_escapes_the_editor_gate(): void
    {
        $contentGraphControllers = [
            ConceptController::class,
            ConceptQuestionController::class,
        ];

        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction();
            $controllerAction = $action['controller'] ?? null;

            $touchesContentGraph = is_string($controllerAction)
                && collect($contentGraphControllers)->contains(
                    fn (string $class) => str_starts_with($controllerAction, $class.'@')
                );

            // Le due nuove rotte Mission 05 (admin.articles.concepts.*) vivono
            // su Admin\ArticleController, non sui controller Content Graph
            // dedicati: identificate per nome invece che per classe.
            $isArticleConceptRoute = in_array($route->getName(), [
                'admin.articles.concepts.link',
                'admin.articles.concepts.unlink',
            ], true);

            if (! $touchesContentGraph && ! $isArticleConceptRoute) {
                continue;
            }

            $checked++;
            $middleware = $route->gatherMiddleware();

            $this->assertContains(
                'editor',
                $middleware,
                "Route '{$route->uri()}' ({$route->getName()}) tocca il Content Graph ma non è protetta dalla middleware 'editor'."
            );
            $this->assertContains('auth', $middleware);
        }

        // Autoverifica: la mission è priva di significato se l'elenco sopra
        // non intercetta nessuna route reale (es. dopo un refactor dei nomi).
        $this->assertGreaterThanOrEqual(7, $checked);
    }

    public function test_no_route_anywhere_is_reachable_by_a_concept_alias(): void
    {
        $aliasRoutes = collect(Route::getRoutes())->filter(
            fn ($route) => str_contains($route->uri(), 'alias')
        );

        $this->assertCount(0, $aliasRoutes, 'ConceptAlias non deve mai essere raggiungibile tramite una propria route: è solo dato editoriale di ricerca/dedup interno.');
    }

    // ── Garanzia: i concetti in bozza non diventano mai pubblici ────

    public function test_draft_concept_links_are_excluded_from_the_discoverable_read_even_for_a_published_article(): void
    {
        $service = $this->service();
        $draftConcept = Concept::create(['name' => 'In lavorazione']);
        $article = $this->publishedArticle('Articolo pubblico');

        $service->linkArticle($article, $draftConcept, ArticleConcept::RELATION_PRIMARY, 100);

        $this->assertCount(0, $service->discoverableConceptsForArticle($article));
    }

    public function test_inactive_concept_links_are_also_excluded_from_the_discoverable_read(): void
    {
        $service = $this->service();
        $inactiveConcept = Concept::create(['name' => 'Ritirato', 'status' => Concept::STATUS_INACTIVE]);
        $article = $this->publishedArticle('Articolo pubblico due');

        $service->linkArticle($article, $inactiveConcept, ArticleConcept::RELATION_SUPPORTING, 50);

        $this->assertCount(0, $service->discoverableConceptsForArticle($article));
    }

    // ── Garanzia: le domande in bozza restano private ────────────────

    public function test_a_draft_question_never_appears_in_the_answerable_read_even_with_every_other_condition_satisfied(): void
    {
        $service = $this->service();
        $active = Concept::create(['name' => 'Concetto attivo', 'status' => Concept::STATUS_ACTIVE]);
        $published = $this->publishedArticle('Target pubblicato');

        $active->questions()->create([
            'question' => 'Domanda con status draft esplicito',
            'answer_summary' => 'Sommario presente e non vuoto.',
            'status' => ConceptQuestion::STATUS_DRAFT,
            'target_article_id' => $published->id,
        ]);

        $this->assertCount(0, $service->answerableQuestionsForConcept($active));
    }

    public function test_an_inactive_question_never_appears_in_the_answerable_read(): void
    {
        $service = $this->service();
        $active = Concept::create(['name' => 'Concetto attivo due', 'status' => Concept::STATUS_ACTIVE]);
        $published = $this->publishedArticle('Target pubblicato due');

        $active->questions()->create([
            'question' => 'Domanda ritirata',
            'answer_summary' => 'Sommario presente.',
            'status' => ConceptQuestion::STATUS_INACTIVE,
            'target_article_id' => $published->id,
        ]);

        $this->assertCount(0, $service->answerableQuestionsForConcept($active));
    }

    // ── Garanzia: target_article_id non espone mai un articolo non pubblico ──

    public function test_a_question_targeting_an_article_in_review_is_never_answerable(): void
    {
        $service = $this->service();
        $active = Concept::create(['name' => 'Concetto attivo tre', 'status' => Concept::STATUS_ACTIVE]);
        $review = $this->articleWithStatus('In revisione', Article::STATUS_REVIEW, null);

        $active->questions()->create([
            'question' => 'Domanda verso articolo in revisione',
            'answer_summary' => 'Sommario presente.',
            'status' => ConceptQuestion::STATUS_APPROVED,
            'target_article_id' => $review->id,
        ]);

        $this->assertCount(0, $service->answerableQuestionsForConcept($active));
    }

    public function test_a_published_status_with_a_future_published_at_never_counts_as_a_public_target(): void
    {
        // Difesa in profondità: Article::scopePublished() richiede sia
        // status='published' SIA published_at <= now(). Uno stato
        // incoerente (status già 'published' ma data futura, che non
        // dovrebbe mai accadere tramite il flusso editoriale normale) non
        // deve comunque mai risultare "pubblico" per il Content Graph.
        $service = $this->service();
        $active = Concept::create(['name' => 'Concetto attivo quattro', 'status' => Concept::STATUS_ACTIVE]);
        $inconsistent = $this->articleWithStatus('Incoerente', Article::STATUS_PUBLISHED, now()->addDay());

        $active->questions()->create([
            'question' => 'Domanda verso stato incoerente',
            'answer_summary' => 'Sommario presente.',
            'status' => ConceptQuestion::STATUS_APPROVED,
            'target_article_id' => $inconsistent->id,
        ]);
        $service->linkArticle($inconsistent, $active, ArticleConcept::RELATION_PRIMARY, 100);

        $this->assertCount(0, $service->answerableQuestionsForConcept($active));
        $this->assertCount(0, $service->discoverableConceptsForArticle($inconsistent));
    }

    // ── Garanzia: nessun N+1 accidentale nelle letture del grafo ─────

    public function test_concepts_for_article_does_not_grow_its_query_count_with_the_number_of_links(): void
    {
        $article = $this->publishedArticle('Articolo con molti concetti');
        $service = $this->service();

        foreach (range(1, 8) as $i) {
            $concept = Concept::create(['name' => "Concetto $i"]);
            $concept->aliases()->create(['alias' => "alias-$i"]);
            $service->linkArticle($article, $concept);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $links = $service->conceptsForArticle($article);
        // L'accesso a ->concept->name e ->concept->aliases per ogni riga non
        // deve emettere nuove query: entrambe le relazioni sono già eager
        // caricate da conceptsForArticle() (->with(['concept.aliases'])).
        foreach ($links as $link) {
            $link->concept->name;
            $link->concept->aliases->count();
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $links);
        $this->assertLessThanOrEqual(3, $queryCount, 'conceptsForArticle() deve restare O(1) in query indipendentemente dal numero di concetti collegati.');
    }

    public function test_articles_for_concept_does_not_grow_its_query_count_with_the_number_of_links(): void
    {
        $concept = Concept::create(['name' => 'Concetto con molti articoli']);
        $service = $this->service();

        foreach (range(1, 8) as $i) {
            $article = $this->publishedArticle("Articolo $i");
            $service->linkArticle($article, $concept);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $links = $service->articlesForConcept($concept);
        foreach ($links as $link) {
            $link->article->title;
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $links);
        $this->assertLessThanOrEqual(2, $queryCount, 'articlesForConcept() deve restare O(1) in query indipendentemente dal numero di articoli collegati.');
    }

    public function test_answerable_questions_for_concept_does_not_grow_its_query_count_with_the_number_of_questions(): void
    {
        $active = Concept::create(['name' => 'Concetto con molte domande', 'status' => Concept::STATUS_ACTIVE]);
        $service = $this->service();

        foreach (range(1, 8) as $i) {
            $target = $this->publishedArticle("Target $i");
            $active->questions()->create([
                'question' => "Domanda numero $i",
                'answer_summary' => 'Sommario presente.',
                'status' => ConceptQuestion::STATUS_APPROVED,
                'target_article_id' => $target->id,
                'sort_order' => $i,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $questions = $service->answerableQuestionsForConcept($active);
        foreach ($questions as $question) {
            $question->targetArticle->title;
        }
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(8, $questions);
        $this->assertLessThanOrEqual(3, $queryCount, 'answerableQuestionsForConcept() deve restare O(1) in query indipendentemente dal numero di domande.');
    }

    // ── Garanzia: le mutazioni del grafo non toccano mai la pubblicazione ──

    public function test_linking_and_unlinking_a_concept_through_the_service_never_changes_the_articles_own_attributes(): void
    {
        $article = $this->publishedArticle('Articolo invariato dal grafo');
        $concept = Concept::create(['name' => 'Concetto neutro']);
        $before = $article->refresh()->getAttributes();

        $this->service()->linkArticle($article, $concept, ArticleConcept::RELATION_PRIMARY, 90);
        $afterLink = Article::find($article->id)->getAttributes();
        $this->assertSame($before, $afterLink);

        ArticleConcept::where('article_id', $article->id)->where('concept_id', $concept->id)->delete();
        $afterUnlink = Article::find($article->id)->getAttributes();
        $this->assertSame($before, $afterUnlink);
    }

    public function test_linking_a_concept_through_both_admin_http_surfaces_never_changes_the_articles_own_attributes(): void
    {
        $editor = $this->editor();
        $article = $this->publishedArticle('Articolo invariato dalle due superfici admin');
        $concept = Concept::create(['name' => 'Concetto neutro due']);
        $before = $article->refresh()->getAttributes();

        // Superficie 1: lato concetto (admin.concepts.articles.link).
        $this->actingAs($editor)->post(route('admin.concepts.articles.link', [$concept, $article]), [
            'relation_type' => 'primary',
            'weight' => 77,
        ]);
        $this->assertSame($before, Article::find($article->id)->getAttributes());

        $this->actingAs($editor)->delete(route('admin.concepts.articles.unlink', [$concept, $article]));

        // Superficie 2: lato articolo (admin.articles.concepts.link, Mission 05).
        $this->actingAs($editor)->post(route('admin.articles.concepts.link', [$article, $concept]), [
            'relation_type' => 'supporting',
            'weight' => 33,
        ]);
        $this->assertSame($before, Article::find($article->id)->getAttributes());

        $this->actingAs($editor)->delete(route('admin.articles.concepts.unlink', [$article, $concept]));
        $this->assertSame($before, Article::find($article->id)->getAttributes());
    }

    // ── Garanzia: la cancellazione di un concetto non tocca mai un articolo ──

    public function test_deleting_a_concept_leaves_the_linked_articles_attributes_byte_for_byte_unchanged(): void
    {
        $article = $this->publishedArticle('Articolo sopravvive alla cancellazione del concetto');
        $concept = Concept::create(['name' => 'Concetto da cancellare']);
        $concept->aliases()->create(['alias' => 'da cancellare']);
        $concept->questions()->create(['question' => 'Domanda del concetto da cancellare']);
        $this->service()->linkArticle($article, $concept);

        $before = $article->refresh()->getAttributes();

        $concept->delete();

        $this->assertSame($before, Article::find($article->id)->getAttributes());
        $this->assertDatabaseMissing('article_concepts', ['concept_id' => $concept->id]);
    }

    private function service(): ContentGraphService
    {
        return app(ContentGraphService::class);
    }

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function publishedArticle(string $title): Article
    {
        return $this->articleWithStatus($title, Article::STATUS_PUBLISHED, now()->subMinute());
    }

    private function articleWithStatus(string $title, string $status, $publishedAt): Article
    {
        return Article::create([
            'user_id' => $this->author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo articolo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'fisica',
            'status' => $status,
            'read_minutes' => 2,
            'published_at' => $publishedAt,
        ]);
    }
}
