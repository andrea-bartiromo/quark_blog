<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\Category;
use App\Services\ArticleContinuationService;
use App\Services\ArticleManualSourcesDetector;
use App\Services\ArticlePathNavigation;
use App\Services\ArticlePrimarySourcesParser;
use App\Services\ArticleRelatedService;
use App\Services\ArticleRevisionTransparencyService;
use App\Services\ArticleViewTrackingService;
use App\Services\ContentGraph\ContentGraphService;
use App\Services\ContinuationAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class ArticleController extends Controller
{
    public function index()
    {
        return view('notizie', [
            'articles' => Article::published()
                ->with('author')
                ->paginate(12),
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $categoryModel = Category::where('slug', $slug)->first();
        $categories = Category::options(false);

        abort_unless($categoryModel || array_key_exists($slug, $categories), 404);

        // Una categoria presente in DB (bozza/programmata futura/disattivata)
        // non è mai raggiungibile dalla sua pagina pubblica — vedi
        // Category::scopePubliclyVisible(). Le categorie legacy solo da
        // config (nessuna riga DB) restano raggiungibili come prima: non
        // sono mai state coperte dalla pianificazione.
        if ($categoryModel && ! $categoryModel->isPubliclyVisible()) {
            abort(404);
        }

        $articles = Article::published()
            ->where(function ($query) use ($slug) {
                $query->where('category', $slug)
                    ->orWhereHas('secondaryCategories', fn ($secondaryQuery) => $secondaryQuery->where('categories.slug', $slug));
            })
            ->orderByDesc('id')
            ->with('author')
            // Sei card sono la misura editoriale della griglia pubblica:
            // abbastanza per scoprire, senza trasformare l'archivio in una
            // lista infinita. withQueryString() conserva eventuali filtri
            // già presenti nei link HTML della navigazione.
            ->paginate(6)
            ->withQueryString();

        // Anche una categoria senza articoli ha una sola pagina valida:
        // ?page=2 (o maggiore) deve essere un vero 404, mai una griglia
        // vuota con HTTP 200.
        if ($articles->currentPage() > $articles->lastPage()) {
            abort(404);
        }

        // I link di navigazione mantengono i parametri della richiesta
        // tranne page. La pagina uno resta il suo URL naturale, senza
        // ?page=1; il canonical invece viene costruito separatamente dalla
        // vista e non eredita parametri di tracking o filtri.
        $paginationQuery = $request->query();
        unset($paginationQuery['page'], $paginationQuery['slug']);

        $pageUrl = static fn (int $page): string => route('categoria', array_merge(
            ['slug' => $slug],
            $paginationQuery,
            $page === 1 ? [] : ['page' => $page],
        ));

        // Blocco "Continua a esplorare" (Cantiere 1, programma 100-cantieri
        // Kairus): tre articoli tra i più letti, mai un duplicato di quelli
        // già mostrati in questa stessa pagina di griglia — altrimenti un
        // lettore che ha appena scorso 6 card vedrebbe una di quelle stesse
        // sei ripetuta subito sotto come "consigliata".
        $mostRead = Article::published()
            ->whereNotIn('id', $articles->pluck('id'))
            ->orderByDesc('views')
            ->limit(3)
            ->get(['title', 'slug', 'category', 'read_minutes']);

        // Chip Argomenti + categorie correlate: SOLO categorie genuinamente
        // pubbliche (Category::publicOptions(), la stessa già usata dal
        // pill-row di notizie.blade.php) — mai la lista di $categories sopra,
        // che include anche bozza/programmata/disattivata per il solo
        // controllo 404. $categoryLabelOptions invece riusa $categories
        // già recuperata (nessuna query aggiuntiva): i badge di "Più letti"
        // devono restare leggibili anche per una categoria nel frattempo
        // disattivata, stessa semantica già in components/sidebar.blade.php.
        $publicCategoryOptions = Category::publicOptions();

        return view('categoria', [
            'slug' => $slug,
            'categoryModel' => $categoryModel,
            'categoryLabel' => $categoryModel?->name ?? $categories[$slug],
            'categoryDescription' => $categoryModel?->description,
            'categoryImage' => $categoryModel?->image,
            'category' => $slug,

            // Discovery multi-categoria: la pagina mostra gli articoli che
            // hanno questa categoria come principale oppure come secondaria.
            // whereHas() usa EXISTS e quindi non duplica le righe anche se
            // un articolo soddisfacesse entrambe le condizioni.
            'articles' => $articles,
            'firstPageUrl' => $pageUrl(1),
            'previousPageUrl' => $articles->onFirstPage() ? null : $pageUrl($articles->currentPage() - 1),
            'nextPageUrl' => $articles->hasMorePages() ? $pageUrl($articles->currentPage() + 1) : null,
            'mostRead' => $mostRead,
            'categoryOptions' => $publicCategoryOptions,
            'categoryLabelOptions' => $categories,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        // Nessun eager load di 'comments': i commenti non vengono mai
        // renderizzati sulla pagina pubblica articolo (solo in moderazione
        // admin, resources/views/admin/comments.blade.php) — caricarli qui
        // era una query sprecata a ogni richiesta.
        $article = Article::published()
            ->where('slug', $slug)
            ->with('author')
            ->first();

        if (! $article) {
            $redirect = ArticleSlugRedirect::where('old_slug', $slug)->first();

            // Stessa definizione di "pubblicamente visibile" usata sopra
            // (published() applica anche il controllo su published_at):
            // 301 solo se l'articolo di destinazione è davvero raggiungibile
            // ora, altrimenti si ricade nel normale 404.
            $target = $redirect ? Article::published()->find($redirect->article_id) : null;

            abort_unless($target, 404);

            return redirect()->route('articolo', $target->slug, 301);
        }

        $sessionKey = 'article_viewed_'.$article->id;

        // Il flag di sessione viene impostato solo quando la view è stata
        // davvero registrata: se restasse impostato anche per traffico
        // interno mai contato, potrebbe in teoria mascherare una
        // successiva view pubblica genuina nella stessa sessione.
        if (! session()->has($sessionKey) && app(ArticleViewTrackingService::class)->recordView($article)) {
            session()->put($sessionKey, true);
        }

        $pathNavigation = app(ArticlePathNavigation::class)->forArticle($article);
        $excludeFromRelated = collect([
            $pathNavigation['previous'] ?? null,
            $pathNavigation['next'] ?? null,
        ])->filter()->pluck('id')->all();

        // "Continua da qui": passa $pathNavigation già calcolato sopra per
        // evitare che il servizio lo ricalcoli (stessa query ripetuta due
        // volte). Quando pathNavigation ha già un "next", il servizio
        // restituisce esattamente quello — in quel caso $showContinuation
        // resta false: il modulo non deve duplicare il blocco "Successivo"
        // già mostrato da path-continuation (vedi
        // articles/partials/continue-reading.blade.php).
        $continuation = app(ArticleContinuationService::class)->forArticle($article, $pathNavigation);
        $showContinuation = $continuation && (! $pathNavigation || ! $pathNavigation['next']);

        // La destinazione della CTA forte non deve ricomparire subito dopo
        // tra i correlati. Precedente/successivo erano gia esclusi sopra;
        // il fallback di categoria di "Continua da qui" va escluso qui,
        // dopo che il candidato effettivo e stato risolto.
        if ($showContinuation) {
            $excludeFromRelated[] = $continuation->id;
        }

        $continuationTargetUrl = null;

        // Second Read Analytics (Growth S2): fail-open per design — un
        // errore qui non deve mai impedire la lettura pubblica
        // dell'articolo, quindi l'intero blocco è avvolto in try/catch
        // anche se ContinuationAnalyticsService già intercetta i propri
        // errori di scrittura internamente.
        try {
            if ($showContinuation) {
                // URL firmato con scadenza: l'unico modo per B di sapere
                // "sono stato raggiunto da Continua da qui su A" senza
                // cookie/sessione cross-articolo, senza poter essere
                // falsificato (firma HMAC + scadenza gestite da Laravel),
                // e senza introdurre un open redirect — lo slug di
                // destinazione è sempre quello della rotta corrente, mai
                // un valore fornito dal client.
                $continuationTargetUrl = URL::temporarySignedRoute(
                    'articolo',
                    now()->addMinutes(30),
                    ['slug' => $continuation->slug, 'cd_src' => $article->id]
                );

                app(ContinuationAnalyticsService::class)->recordImpression($article, $continuation);
            }

            if ($request->hasValidSignature() && $request->filled('cd_src')) {
                $source = Article::published()->find((int) $request->query('cd_src'));

                if ($source && $source->id !== $article->id) {
                    app(ContinuationAnalyticsService::class)->recordSecondReadStart($source, $article);
                }
            }
        } catch (\Throwable $exception) {
            Log::warning('Second Read Analytics: blocco fallito, la pagina articolo continua normalmente.', [
                'article_id' => $article->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        // Category::allOrderedWithVisibilityColumns(), non options(false)
        // + un lookup dedicato: UNA sola query da cui derivare sia la
        // mappa slug=>name (categoryOptions, come prima) sia la
        // visibilità pubblica della categoria di QUESTO articolo — una
        // seconda query qui regredirebbe il budget della pagina articolo
        // (vedi PublicPageQueryBudgetTest). Un articolo già pubblicato
        // può avere una categoria bozza o programmata nel futuro
        // (assegnabile in anticipo, vedi Category::options() vs
        // publicOptions()): il breadcrumb visibile e il suo companion
        // JSON-LD BreadcrumbList non devono mai linkare una pagina
        // categoria che risponderebbe 404 (ArticleController::category())
        // — vedi 'categoryPubliclyVisible' sotto, consumato da
        // articles/partials/breadcrumb.blade.php e
        // articles/partials/structured-data.blade.php.
        $categoryModels = Category::allOrderedWithVisibilityColumns();
        $categoryOptions = $categoryModels->isNotEmpty()
            ? $categoryModels->pluck('name', 'slug')->toArray()
            : config('laboratorio.categories', []);
        $categoryModelForVisibility = $categoryModels->firstWhere('slug', $article->category);
        $categoryPubliclyVisible = $categoryModelForVisibility
            ? $categoryModelForVisibility->isPubliclyVisible()
            : array_key_exists($article->category, $categoryOptions);

        return view('articolo', [
            'article' => $article,

            // Correlati multi-categoria: condivide almeno una categoria
            // principale o secondaria con l'articolo corrente. Le tappe
            // precedente/successiva del Percorso sono già mostrate nel
            // blocco dedicato e vengono escluse per evitare duplicazioni.
            'related' => app(ArticleRelatedService::class)->forArticle($article, excludeIds: $excludeFromRelated),

            // Calcolato una sola volta qui e riusato da articolo.blade.php,
            // articles/partials/structured-data.blade.php e
            // articles/partials/breadcrumb.blade.php (unici consumer di
            // questo partial, vedi grep): prima ciascuno rieseguiva la
            // stessa query "select name, slug from categories" per conto
            // proprio, 4 query identiche per singola pagina articolo.
            'categoryOptions' => $categoryOptions,

            // Un articolo già pubblicato può avere una categoria bozza o
            // programmata nel futuro (assegnabile in anticipo, vedi
            // Category::options() vs publicOptions()): il breadcrumb
            // visibile e il suo companion JSON-LD BreadcrumbList non
            // devono mai linkare una pagina categoria che risponderebbe
            // 404 (ArticleController::category()) — omettono quella voce
            // quando questo è false, mostrando comunque il nome come
            // testo semplice altrove (articleSection) dove non è un link.
            'categoryPubliclyVisible' => $categoryPubliclyVisible,

            'pathNavigation' => $pathNavigation,

            'continuation' => $showContinuation ? $continuation : null,
            'continuationTargetUrl' => $continuationTargetUrl,

            // Mission 24/25 — Content Graph Public Consumer: nessun blocco
            // UI visibile ancora (il catalogo Concetti è popolato solo via
            // CRUD admin, senza seeder/import di massa — la profondità reale
            // per articolo non è garantita, quindi un blocco "Concetti
            // correlati" rischierebbe di apparire vuoto sulla maggior parte
            // degli articoli). Solo dati strutturati (schema.org `about`),
            // che degradano in modo invisibile quando non c'è nulla da
            // mostrare — vedi articles/partials/structured-data.blade.php.
            // Riusa discoverableConceptsForArticle(), l'UNICO contratto di
            // lettura pubblica già certificato da
            // ContentGraphPublicSafetyContractTest: mai un concetto
            // draft/inattivo può comparire qui.
            'discoverableConcepts' => app(ContentGraphService::class)->discoverableConceptsForArticle($article),

            // Trust Layer — fonti primarie pubbliche: presentation-only,
            // legge Article::primary_sources così come salvato dal
            // flusso di verifica editoriale esistente (fuori scope di
            // questa modifica), senza reinterpretarlo come dato
            // strutturato. Vedi App\Services\ArticlePrimarySourcesParser
            // e components/article/primary-sources.blade.php.
            'primarySources' => app(ArticlePrimarySourcesParser::class)->parse($article->primary_sources),

            // Nasconde solo il pannello duplicato: body e primary_sources restano invariati.
            'hasManualSourcesSection' => app(ArticleManualSourcesDetector::class)->hasManualSourcesSection($article->body),

            // Trust Layer — trasparenza revisioni: null a meno che
            // esista una revisione post-pubblicazione con contenuto
            // davvero diverso dallo stato attuale (mai una data
            // "aggiornato" inventata). Vedi
            // App\Services\ArticleRevisionTransparencyService.
            'lastEditorialUpdate' => app(ArticleRevisionTransparencyService::class)->lastEditorialUpdate($article),
        ]);
    }
}
