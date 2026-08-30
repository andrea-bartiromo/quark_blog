<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleSlugRedirect;
use App\Models\Category;
use App\Services\ArticleContinuationService;
use App\Services\ArticlePathNavigation;
use App\Services\ArticleRelatedService;
use App\Services\ArticleViewTrackingService;
use App\Services\ContentGraph\ContentGraphService;
use App\Services\ContinuationAnalyticsService;
use App\Services\Telemetry\EditorialContinuityRecorder;
use App\Services\Telemetry\EditorialEventContract;
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

    public function category(string $slug)
    {
        $categoryModel = Category::where('slug', $slug)->first();
        $categories = Category::options(false);

        abort_unless($categoryModel || array_key_exists($slug, $categories), 404);

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
            'articles' => Article::published()
                ->where(function ($query) use ($slug) {
                    $query->where('category', $slug)
                        ->orWhereHas('secondaryCategories', fn ($secondaryQuery) => $secondaryQuery->where('categories.slug', $slug));
                })
                ->with('author')
                ->paginate(12),
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

        // Measurement Closeout (Missioni 2-4): contratto canonico degli
        // eventi editoriali. Affianca — non sostituisce — gli eventi Growth
        // S2 registrati sopra: quelli restano la fonte di verità del funnel
        // "Continua da qui" per articolo, questi aggiungono la dimensione di
        // SESSIONE senza la quale second-read rate e path continuation rate
        // non sono ricostruibili (vedi la migration).
        //
        // Nessun try/catch qui: EditorialContinuityRecorder è fail-safe per
        // contratto (vedi il suo docblock), quindi riavvolgerlo darebbe una
        // falsa impressione che senza il catch la pagina potrebbe rompersi.
        $recorder = app(EditorialContinuityRecorder::class);
        $recorder->recordArticleView(
            $article,
            $pathNavigation['cluster'] ?? null,
            $pathNavigation['current_index'] ?? null,
        );

        // Un evento "transizione disponibile" per OGNI controllo davvero
        // renderizzato — mai uno per pageview. È la differenza fra il
        // denominatore corretto di Missione 4 ("view in cui la transizione
        // era realmente disponibile") e quello sbagliato che la missione
        // vieta esplicitamente ("tutte le pageview").
        if ($pathNavigation && $pathNavigation['previous'] instanceof Article) {
            $recorder->recordTransitionAvailable(
                $article,
                $pathNavigation['previous'],
                EditorialEventContract::TRANSITION_PREVIOUS,
                $pathNavigation['cluster'],
                $pathNavigation['current_index'],
            );
        }

        if ($pathNavigation && $pathNavigation['next'] instanceof Article) {
            $recorder->recordTransitionAvailable(
                $article,
                $pathNavigation['next'],
                EditorialEventContract::TRANSITION_NEXT,
                $pathNavigation['cluster'],
                $pathNavigation['current_index'],
            );
        }

        // $showContinuation, non $continuation: quando il Percorso ha già un
        // "successivo", il modulo "Continua da qui" NON viene renderizzato
        // (vedi sopra) e contarlo come disponibile falserebbe il
        // denominatore proprio nel modo che la missione vieta.
        if ($showContinuation) {
            $recorder->recordTransitionAvailable(
                $article,
                $continuation,
                EditorialEventContract::TRANSITION_CONTINUA_DA_QUI,
                $pathNavigation['cluster'] ?? null,
                $pathNavigation['current_index'] ?? null,
            );
        }

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
            'categoryOptions' => Category::options(false),

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
        ]);
    }
}
