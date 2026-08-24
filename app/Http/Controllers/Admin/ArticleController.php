<?php

/**
 * Kairus — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@kairus.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://kairus.it
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Category;
use App\Models\Concept;
use App\Models\User;
use App\Services\ArticleLinkInsertionService;
use App\Services\ArticleLinkSuggestionService;
use App\Services\ArticleRevisionService;
use App\Services\ContentGraph\ConceptSuggestionService;
use App\Services\ContentGraph\ContentGraphService;
use App\Services\EditorialQuality\EditorialQualityChecker;
use App\Services\ImageService;
use App\Services\MediaRetirementService;
use App\Services\MediaService;
use App\Services\PublicMediaSyncService;
use App\Services\ResponsiveImageVariantService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class ArticleController extends Controller
{
    /**
     * Dimensione pagina dell'elenco Articoli admin. Stesso ordine di
     * grandezza di MediaController (24) e più larga della paginazione
     * Campagne (15): la tabella articoli è più densa per riga (poche
     * colonne, nessuna card), quindi tollera più righe per pagina senza
     * diventare illeggibile.
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly ImageService $imageService,
        private readonly MediaService $mediaService,
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly ArticleLinkSuggestionService $linkSuggestionService,
        private readonly ArticleLinkInsertionService $linkInsertionService,
        private readonly EditorialQualityChecker $qualityChecker,
        private readonly MediaRetirementService $mediaRetirementService,
        private readonly ResponsiveImageVariantService $responsiveImageVariants,
        private readonly ContentGraphService $contentGraph,
        private readonly ConceptSuggestionService $conceptSuggestions,
        private readonly ArticleRevisionService $revisionService,
    ) {}

    public function index(Request $request)
    {
        // Sanitizzazione manuale invece di $request->validate(): un valore
        // sconosciuto o non ammesso deve solo essere ignorato, mai
        // interrompere una GET con redirect/422. La ricerca replica inoltre
        // lato server il maxlength=150 della UI, Unicode-safe, cosi una URL
        // costruita manualmente non puo generare LIKE arbitrariamente grandi.
        $searchInput = $request->input('q', '');
        $search = is_string($searchInput)
            ? mb_substr(trim($searchInput), 0, 150)
            : '';

        if ($request->has('q')) {
            $request->query->set('q', $search);
        }

        $status = $request->input('status');
        if (! is_string($status) || ! array_key_exists($status, Article::statusOptions())) {
            $status = null;
        }

        $category = $request->input('category');
        if (! is_string($category) || $category === '' || mb_strlen($category) > 100) {
            $category = null;
        }

        // NON usare $request->input('author'): il middleware globale
        // TrimStrings ha gia' tolto gli spazi da ogni stringa di input PRIMA
        // che il controller la veda, quindi " 1 " arriverebbe qui gia'
        // trasformato nel valido "1" — esattamente l'opposto del contratto
        // "solo un intero positivo canonico" che questo filtro deve
        // applicare. QUERY_STRING (server bag) non e' mai toccato da
        // TrimStrings: e' il valore grezzo cosi' come arrivato nell'URL,
        // usato qui solo per riconoscere e scartare un author con
        // whitespace prima/dopo, che altrimenti sarebbe indistinguibile
        // dallo stesso ID digitato senza spazi (verificato: entrambi
        // producono lo stesso $request->input('author') dopo il trim).
        $rawQueryParams = [];
        parse_str((string) $request->server->get('QUERY_STRING', ''), $rawQueryParams);
        $authorInput = $rawQueryParams['author'] ?? null;
        $authorId = null;

        if (is_string($authorInput) && preg_match('/^[1-9][0-9]*$/D', $authorInput) === 1) {
            $validatedAuthorId = filter_var(
                $authorInput,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $authorId = $validatedAuthorId === false ? null : $validatedAuthorId;
        }

        $query = Article::query()->latest()->with('author');

        if ($search !== '') {
            // LIKE con escaping esplicito di %, _ e del carattere di escape
            // stesso (stesso schema di MediaController::escapeLike()):
            // senza questo, una ricerca letterale come "50%" o "nome_file"
            // verrebbe interpretata come un pattern SQL invece che come
            // testo, restituendo corrispondenze inattese o mancanti.
            $escapedSearch = $this->escapeLike($search);
            $likeTerm = '%'.$escapedSearch.'%';

            $query->where(function (Builder $query) use ($likeTerm) {
                $query
                    ->whereRaw("title LIKE ? ESCAPE '!'", [$likeTerm])
                    ->orWhereRaw("excerpt LIKE ? ESCAPE '!'", [$likeTerm])
                    ->orWhereRaw("body LIKE ? ESCAPE '!'", [$likeTerm]);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($category !== null) {
            $query->where('category', $category);
        }

        if ($authorId !== null) {
            $query->where('user_id', $authorId);
        }

        $articles = $query->paginate(self::PER_PAGE)->withQueryString();

        // Una pagina oltre l'ultima disponibile non rappresenta un vero
        // empty state editoriale. Canonicalizziamo quindi all'ultima pagina
        // valida, preservando la query string gia sanitizzata. Con una sola
        // pagina si torna alla URL senza ?page=1.
        if ($articles->total() > 0 && $articles->currentPage() > $articles->lastPage()) {
            $redirectQuery = $request->query();
            unset($redirectQuery['page']);

            if ($articles->lastPage() > 1) {
                $redirectQuery['page'] = $articles->lastPage();
            }

            return redirect()->route('admin.articles', $redirectQuery);
        }

        // Indicatore "collegamenti ad articoli": limitato ai soli articoli
        // programmati (il caso d'uso richiesto — capire prima della
        // pubblicazione se un pezzo ha già collegato altri articoli
        // Kairus). Il parsing DOM del body ha un costo per riga non
        // trascurabile su liste grandi (vedi PR #141); qui resta limitato
        // al sottoinsieme "programmato" DELLA SOLA PAGINA CORRENTE (al
        // massimo PER_PAGE righe, mai l'intero archivio). Nessuna query
        // aggiuntiva: countArticleLinks() opera sul body già caricato
        // dalla query principale sopra.
        //
        // Conta SOLO i link verso altri articoli Kairus (/articolo/{slug}),
        // non qualunque link interno (homepage/categorie/pagine statiche
        // incluse, come faceva countInternalLinks() usato qui prima —
        // ambiguità rilevata in audit, corretta con decisione prodotto B:
        // "Collegamenti ad articoli"). Stessa definizione, stessa funzione
        // condivisa con App\Services\ArticleLinkSuggestionService (mai due
        // implementazioni divergenti di "collegamento ad articolo") — vedi
        // ArticleLinkInsertionService::linkedArticleSlugsInBody().
        foreach ($articles as $article) {
            if ($article->isScheduled()) {
                $article->article_links_count = $this->linkInsertionService->countArticleLinks((string) $article->body);
            }
        }

        $hasActiveFilters = $search !== '' || $status !== null || $category !== null || $authorId !== null;

        // Il messaggio di stato vuoto deve distinguere "l'archivio è
        // davvero vuoto" da "nessun articolo corrisponde ai filtri
        // scelti": la seconda situazione suggerisce di azzerare i filtri,
        // la prima suggerisce di crearne uno. Interrogato SOLO quando la
        // pagina corrente è vuota — nel caso comune (risultati presenti)
        // non aggiunge alcuna query.
        $articlesExistAtAll = ! $articles->isEmpty() || Article::query()->exists();

        return view('admin.articles', [
            'articles' => $articles,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'authorId' => $authorId,
            'statusOptions' => Article::statusOptions(),
            'categoryOptions' => $this->categoryFilterOptions(),
            'authorOptions' => $this->authorFilterOptions(),
            'hasActiveFilters' => $hasActiveFilters,
            'articlesExistAtAll' => $articlesExistAtAll,
        ]);
    }

    /**
     * Calendario editoriale V1 (articoli pubblicati + programmati). Solo
     * visualizzazione — nessun drag-and-drop, nessuna modifica di data da
     * qui: si continua a modificare la data di pubblicazione dal form
     * dell'articolo, l'unico punto che applica già le regole di validazione
     * dello stato (vedi UpdateArticleRequest / applyBusinessRules()).
     *
     * Non 'Calendario editoriale' come nome per evitare l'omonimia con il
     * concetto già esistente in App\Services\Editorial\* (il piano
     * editoriale dell'Area Progettazione, un dominio completamente
     * diverso) — qui e nelle viste si usa sempre "Calendario articoli".
     *
     * Date sempre confrontate/raggruppate in Europe/Rome
     * (Article::EDITORIAL_TIMEZONE, stessa timezone già usata dal form di
     * modifica): raggruppare per data usando l'istante UTC grezzo
     * sposterebbe di un giorno gli articoli pubblicati vicino alla
     * mezzanotte italiana rispetto a quello che la redazione vede scritto
     * sul form.
     */
    public function calendar(Request $request)
    {
        $viewMode = $request->input('vista');
        if (! in_array($viewMode, ['month', 'week', 'list', 'next4'], true)) {
            $viewMode = 'month';
        }

        $anchor = $this->calendarAnchorDate($request->input('data'));
        $today = now()->timezone(Article::EDITORIAL_TIMEZONE)->startOfDay();

        if ($viewMode === 'next4') {
            // Finestra scorrevole ancorata sempre a oggi (mai al parametro
            // 'data'): serve per pianificazione in avanti ("cosa succede da
            // qui a 4 settimane"), non per navigare nel passato.
            $rangeStart = $today->clone();
            $rangeEnd = $today->clone()->addWeeks(4)->subDay()->endOfDay();
        } elseif ($viewMode === 'week') {
            $rangeStart = $anchor->clone()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $anchor->clone()->endOfWeek(Carbon::SUNDAY);
        } elseif ($viewMode === 'list') {
            $rangeStart = $anchor->clone()->startOfMonth();
            $rangeEnd = $anchor->clone()->endOfMonth();
        } else {
            $rangeStart = $anchor->clone()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $rangeEnd = $anchor->clone()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        }

        $status = $request->input('stato');
        if (! in_array($status, [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED], true)) {
            $status = null;
        }

        $category = $request->input('categoria');
        if (! is_string($category) || $category === '' || mb_strlen($category) > 100) {
            $category = null;
        }

        $rawQueryParams = [];
        parse_str((string) $request->server->get('QUERY_STRING', ''), $rawQueryParams);
        $authorInput = $rawQueryParams['autore'] ?? null;
        $authorId = null;

        if (is_string($authorInput) && preg_match('/^[1-9][0-9]*$/D', $authorInput) === 1) {
            $validatedAuthorId = filter_var($authorInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $authorId = $validatedAuthorId === false ? null : $validatedAuthorId;
        }

        // I confronti Carbon sono su istanti assoluti: convertire in UTC qui
        // non "sposta" le date, serve solo a passare a whereBetween() lo
        // stesso istante che published_at (colonna UTC) confronterà.
        $articles = Article::query()
            ->whereIn('status', $status !== null ? [$status] : [Article::STATUS_PUBLISHED, Article::STATUS_SCHEDULED])
            ->whereBetween('published_at', [$rangeStart->clone()->utc(), $rangeEnd->clone()->utc()])
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($authorId !== null, fn ($query) => $query->where('user_id', $authorId))
            ->with('author')
            ->orderBy('published_at')
            ->get();

        $byDay = $articles->groupBy(fn (Article $article) => $article->publishedAtForEditors()->format('Y-m-d'));

        $todayKey = $today->format('Y-m-d');
        $days = collect();
        $cursor = $rangeStart->clone();

        while ($cursor->lte($rangeEnd)) {
            $key = $cursor->format('Y-m-d');
            $days->push([
                'date' => $cursor->clone(),
                'inFocusedMonth' => $cursor->month === $anchor->month && $cursor->year === $anchor->year,
                'isToday' => $key === $todayKey,
                'articles' => $byDay->get($key, collect()),
            ]);
            $cursor->addDay();
        }

        $monthAnchor = $anchor->clone()->startOfMonth();

        $filterParams = array_filter([
            'stato' => $status,
            'categoria' => $category,
            'autore' => $authorId,
        ], fn ($value) => $value !== null);

        $counts = [
            'published' => $articles->where('status', Article::STATUS_PUBLISHED)->count(),
            'scheduled' => $articles->where('status', Article::STATUS_SCHEDULED)->count(),
        ];

        return view('admin.articles-calendar', [
            'viewMode' => $viewMode,
            'anchor' => $anchor,
            'days' => $days,
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'counts' => $counts,
            'status' => $status,
            'category' => $category,
            'authorId' => $authorId,
            'statusOptions' => Article::statusOptions(),
            'categoryOptions' => $this->categoryFilterOptions(),
            'authorOptions' => $this->authorFilterOptions(),
            'prevUrl' => route('admin.articles.calendar', [
                'vista' => $viewMode,
                'data' => $viewMode === 'week' ? $anchor->clone()->subWeek()->format('Y-m-d') : $monthAnchor->clone()->subMonth()->format('Y-m-d'),
                ...$filterParams,
            ]),
            'nextUrl' => route('admin.articles.calendar', [
                'vista' => $viewMode,
                'data' => $viewMode === 'week' ? $anchor->clone()->addWeek()->format('Y-m-d') : $monthAnchor->clone()->addMonth()->format('Y-m-d'),
                ...$filterParams,
            ]),
            'todayUrl' => route('admin.articles.calendar', ['vista' => $viewMode, ...$filterParams]),
        ]);
    }

    /**
     * Data di ancoraggio del calendario, in Europe/Rome. Un valore 'data'
     * mancante o non in formato Y-m-d ricade silenziosamente su "oggi" —
     * stesso principio già in uso in index() per i filtri (un input
     * malformato viene ignorato, mai un 422/500).
     */
    private function calendarAnchorDate(mixed $input): Carbon
    {
        if (is_string($input) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input) === 1) {
            try {
                return Carbon::createFromFormat('Y-m-d', $input, Article::EDITORIAL_TIMEZONE)->startOfDay();
            } catch (\Throwable) {
                // Formato sintatticamente valido ma data inesistente
                // (es. 2026-02-30): ricade su "oggi" come qualunque altro
                // input malformato.
            }
        }

        return now()->timezone(Article::EDITORIAL_TIMEZONE)->startOfDay();
    }

    /**
     * Categorie realmente presenti tra gli articoli esistenti (non l'intero
     * catalogo config/Category::options()): un filtro deve proporre solo
     * valori che possono davvero restituire risultati. 'category' su
     * articles è una stringa libera senza vincolo FK verso categories
     * (vedi migrazione create_articles_table), quindi una categoria
     * presente sugli articoli ma nel frattempo rinominata/disattivata in
     * Category resta comunque un'opzione valida qui, con la sua etichetta
     * leggibile se ancora risolvibile o lo slug grezzo altrimenti.
     *
     * Query economica: DISTINCT su una colonna indicizzata (indice
     * 'category' già presente dalla migrazione originale), cardinalità
     * tipica nell'ordine delle unità/decine — non cresce con il numero di
     * articoli.
     *
     * @return array<string, string> slug => etichetta leggibile
     */
    private function categoryFilterOptions(): array
    {
        $labels = Category::options(false);

        return Article::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->mapWithKeys(fn (string $slug) => [$slug => $labels[$slug] ?? $slug])
            ->all();
    }

    /**
     * Autori che hanno realmente scritto almeno un articolo (non l'intero
     * elenco utenti): un filtro "Autore" con centinaia di nomi mai apparsi
     * in nessun articolo sarebbe rumore puro per la redazione.
     *
     * @return array<int, string> user_id => nome
     */
    private function authorFilterOptions(): array
    {
        $authorIds = Article::query()->select('user_id')->distinct()->pluck('user_id');

        return User::query()
            ->whereIn('id', $authorIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function create()
    {
        return view('admin.article-form', [
            'article' => null,
            'categories' => Category::options(),
        ]);
    }

    public function store(StoreArticleRequest $request)
    {
        // S9 — stesso finestra di fallimento gia' protetta in update() (Codex,
        // PR #165, round 18): applyBusinessRules() carica la nuova copertina,
        // la sincronizza sulla radice pubblica secondaria e registra il Media
        // PRIMA che Article::create() venga anche solo tentato. Un fallimento
        // di quella create() (DB irraggiungibile, vincolo violato, ecc.)
        // lascerebbe altrimenti un file live, pubblicamente raggiungibile, e
        // la sua riga Media, mai referenziati da alcun articolo.
        $newCoverWasUploaded = $request->hasFile('cover_image_upload')
            && $request->file('cover_image_upload')->isValid();

        try {
            $data = $this->applyBusinessRules(
                $request,
                $request->validated()
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['cover_image_upload' => 'Impossibile pubblicare la nuova copertina. Riprova o contatta l\'assistenza.']);
        }

        $payload = $data + ['user_id' => auth()->id()];

        // S10 — integrazione #221 + #224: il blocco interno (S6 FASE 4)
        // resta invariato e resta l'unica vera garanzia contro la finestra
        // di concorrenza reale sotto vero carico simultaneo (due editor che
        // salvano lo stesso titolo nello stesso istante) — SELECT di
        // controllo + retry-una-sola-volta con slug rigenerato. Il try/catch
        // esterno (S9) non sostituisce questa protezione, la avvolge: se la
        // create() iniziale O il retry falliscono per QUALSIASI motivo (non
        // solo per collisione slug), la copertina appena caricata non deve
        // restare un file live orfano, mai referenziato da alcun articolo.
        try {
            try {
                // S6 FASE 4 — Article::uniqueSlug() (applicato dentro
                // applyBusinessRules(), vedi sopra) copre il caso normale, ma
                // lascia comunque una finestra reale sotto vera concorrenza tra il
                // SELECT di controllo e questa INSERT (due editor che salvano lo
                // stesso titolo nello stesso istante). Il catch-and-retry è quindi
                // l'unica vera garanzia: se la INSERT urta comunque contro il
                // vincolo UNIQUE, si rigenera uno slug (che non può più collidere
                // con la riga appena inserita dall'altro processo) e si ritenta
                // UNA sola volta — mai un retry-loop illimitato.
                Article::create($payload);
            } catch (UniqueConstraintViolationException) {
                $payload['slug'] = Article::uniqueSlug($data['title']);
                Article::create($payload);
            }
        } catch (\Throwable $exception) {
            if ($newCoverWasUploaded && array_key_exists('cover_image', $data)) {
                $this->mediaRetirementService->retireIfUnused(
                    $data['cover_image'],
                    'article_store_failed'
                );
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.articles')
            ->with([
                'success' => 'Articolo creato.',
                // EDITORIAL RESILIENCE: marcatore dedicato, letto SOLO dallo
                // script di pulizia della bozza locale — deve restare
                // distinto dal flash 'success' generico (condiviso da ogni
                // controller admin) per non svuotare la bozza "nuovo
                // articolo" di questo utente quando un'azione qualunque non
                // correlata (upload media, modifica categoria, ecc.) mostra
                // il proprio messaggio di successo sulla stessa superficie.
                'kairus_draft_cleanup_context' => 'new',
            ]);
    }

    public function edit(Request $request, Article $article)
    {
        // Content Graph × Article Editor integration (Mission 05): letture
        // sempre tramite ContentGraphService::conceptsForArticle(), mai una
        // seconda query ad-hoc su ArticleConcept — stessa fonte di verità
        // già usata da Admin\ConceptController::edit() dal lato concetto.
        $conceptLinks = $this->contentGraph->conceptsForArticle($article);
        $linkedConceptIds = $conceptLinks->pluck('concept_id');

        $conceptSearch = $request->input('concept_q');
        $conceptSearch = is_string($conceptSearch) ? mb_substr(trim($conceptSearch), 0, 120) : '';

        $availableConcepts = Concept::query()
            ->when($linkedConceptIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $linkedConceptIds))
            ->when($conceptSearch !== '', function ($query) use ($conceptSearch) {
                $likeTerm = '%'.$this->escapeLike($conceptSearch).'%';
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$likeTerm]);
            })
            ->orderBy('name')
            ->paginate(20, ['*'], 'concept_page')
            ->withQueryString();

        return view('admin.article-form', [
            'article' => $article,
            'categories' => Category::options(),
            'linkSuggestions' => $article->proposedLinkSuggestions(),
            'qualityReport' => $this->qualityChecker->check($article),
            'conceptLinks' => $conceptLinks,
            'availableConcepts' => $availableConcepts,
            'conceptSearch' => $conceptSearch,
            // Mission 20 — Article Editor Concept Suggestions V1: concetti
            // già attivi il cui nome/alias compare nel testo dell'articolo
            // e non è ancora collegato — mai un collegamento automatico,
            // solo un suggerimento accettato tramite l'azione "Collega" già
            // esistente più sotto.
            'conceptSuggestions' => $this->conceptSuggestions->suggestForArticle($article),
        ]);
    }

    /**
     * Collega un concetto del Content Graph a questo articolo. Endpoint
     * gemello di Admin\ConceptController::linkArticle() (stessa validazione,
     * stessa chiamata a ContentGraphService::linkArticle()), solo dal lato
     * articolo: nessuna seconda implementazione della regola di dominio.
     * updateOrCreate() dentro linkArticle() rende un doppio collegamento
     * idempotente (aggiorna relation_type/weight) invece di creare righe
     * duplicate.
     */
    public function linkConcept(Request $request, Article $article, Concept $concept)
    {
        $validated = $request->validate([
            'relation_type' => ['nullable', Rule::in([ArticleConcept::RELATION_PRIMARY, ArticleConcept::RELATION_SUPPORTING])],
            'weight' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $this->contentGraph->linkArticle(
            $article,
            $concept,
            $validated['relation_type'] ?? ArticleConcept::RELATION_SUPPORTING,
            (int) ($validated['weight'] ?? 50),
        );

        return back()->with('success', 'Concetto collegato.');
    }

    public function unlinkConcept(Article $article, Concept $concept)
    {
        ArticleConcept::query()
            ->where('article_id', $article->id)
            ->where('concept_id', $concept->id)
            ->delete();

        return back()->with('success', 'Collegamento rimosso.');
    }

    public function update(
        UpdateArticleRequest $request,
        Article $article
    ) {
        // Codex (PR #165, round 19): calcolato PRIMA di applyBusinessRules()
        // (che comunque non "consuma" il file — la stessa condizione resta
        // verificabile in seguito) per sapere, con certezza, se $data['cover_image']
        // sotto conterrà un disk_name APPENA generato da un nuovo upload, oppure un
        // valore già esistente arrivato da $request->validated() (copertina invariata,
        // o scelta dalla libreria media) — solo il primo caso è sicuro da ritirare in
        // caso di rollback: il secondo può riferirsi a un file già in uso altrove.
        $newCoverWasUploaded = $request->hasFile('cover_image_upload')
            && $request->file('cover_image_upload')->isValid();

        try {
            $data = $this->applyBusinessRules(
                $request,
                $request->validated()
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['cover_image_upload' => 'Impossibile pubblicare la nuova copertina. Riprova o contatta l\'assistenza.']);
        }

        try {
            // Codex (PR #165, P2 round 9): il salvataggio dell'articolo e
            // la conseguente revalidazione/pulizia dei suggerimenti
            // applicati (markAccepted() può a sua volta salvare di nuovo il
            // body se un link non è più sicuro — vedi
            // ArticleLinkSuggestionService) devono avvenire come un'unica
            // unità: senza transazione, un fallimento tra i due update()
            // lascerebbe l'articolo pubblicato con un link ormai non
            // sicuro ancora nel testo, o un suggerimento già marcato
            // superato senza che il body sia stato ripulito.
            DB::transaction(function () use ($article, $data, $request) {
                // EDITORIAL SAFETY: snapshot dello stato ATTUALE prima di
                // applicare $data — vedi ArticleRevisionService per la
                // policy pre-change e il perché è distinta dall'autosave
                // locale.
                $this->revisionService->recordIfChanged($article, $data, $request->user());

                $article->update($data);

                $this->linkSuggestionService->markAccepted(
                    $article,
                    (array) $request->input('applied_link_suggestions', []),
                    $request->user()->id
                );
            });
        } catch (\Throwable $exception) {
            // Codex (PR #165, round 18): applyBusinessRules() carica la
            // nuova copertina, la sincronizza sulla radice pubblica
            // secondaria e registra il Media PRIMA che questa transazione
            // inizi — un fallimento al suo interno (es. markAccepted())
            // annulla solo l'update() dell'Article, non quei side effect
            // già scritti su filesystem/DB. Senza questa pulizia
            // resterebbero un file orfano e una riga Media senza alcun
            // articolo che la referenzi. retireIfUnused() è già lo
            // strumento esistente per questo esatto scenario.
            //
            // Codex (PR #165, round 19): il ritiro va limitato al caso in
            // cui QUESTA richiesta abbia davvero caricato un nuovo file
            // ($newCoverWasUploaded, calcolato sopra) — array_key_exists()
            // da solo non basta, perché $data['cover_image'] può contenere
            // un valore già esistente arrivato da $request->validated()
            // (copertina invariata, o un asset scelto dalla libreria media
            // senza upload), che potrebbe non avere alcuna relazione con
            // questa richiesta e non deve mai essere ritirato per un suo
            // fallimento.
            if ($newCoverWasUploaded && array_key_exists('cover_image', $data)) {
                $this->mediaRetirementService->retireIfUnused(
                    $data['cover_image'],
                    'article_update_transaction_rolled_back'
                );
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.articles')
            ->with([
                'success' => 'Articolo aggiornato.',
                // EDITORIAL RESILIENCE: create/update reindirizzano entrambi
                // qui (mai a una pagina che "conosce" ancora il form appena
                // inviato), quindi la pulizia della bozza locale corrispon-
                // dente avviene leggendo questo flash — vedi
                // partials/article-autosave-script.blade.php e
                // layouts/admin.blade.php. Marcatore dedicato (non il flash
                // 'success' generico): un'azione admin non correlata non
                // deve mai svuotare la bozza locale di questo articolo.
                'kairus_draft_cleanup_context' => (string) $article->id,
            ]);
    }

    public function destroy(Article $article)
    {
        ActivityLog::record(
            'Articolo eliminato',
            'article',
            $article->id,
            $article->title
        );

        $article->delete();

        return redirect()
            ->route('admin.articles')
            ->with('success', 'Articolo eliminato.');
    }

    private function applyBusinessRules(
        Request $request,
        array $data
    ): array {
        if (
            $request->hasFile('cover_image_upload')
            && $request->file('cover_image_upload')->isValid()
        ) {
            $file = $request->file('cover_image_upload');

            $filename = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();

            /*
             * L'estensione viene ricavata dal MIME rilevato
             * dal server, non dal nome inviato dal browser.
             *
             * Le copertine accettano solo JPG, PNG e WebP.
             */
            $ext = $this->imageService->safeExtension($file);

            $diskName = $this->imageService->buildFileName(
                $file,
                $ext,
                now()->format('YmdHis').'-'.Str::random(6)
            );

            $uploadPath = public_path('assets/img');

            $fullPath = $this->imageService->upload(
                $file,
                $uploadPath,
                $diskName
            );

            /*
             * FASE 5 (missione WebP): un nuovo upload JPG/PNG viene
             * convertito automaticamente in WebP prima di essere
             * pubblicato. Se la conversione non si applica (gia' WebP) o
             * fallisce, si ricade sulla ricodifica/ottimizzazione nello
             * stesso formato che uniforma comunque le copertine e rimuove
             * eventuali contenuti estranei incorporati.
             */
            $webpApplied = false;

            if (config('media.auto_webp_on_upload', true)) {
                $conversion = $this->imageService->autoConvertToWebpIfEligible(
                    $fullPath,
                    $ext,
                    (int) config('media.webp_quality', 82),
                    (int) config('media.webp_max_width', 1600)
                );

                $webpApplied = $conversion['webp_applied'];

                if ($webpApplied) {
                    $fullPath = $conversion['full_path'];
                    $ext = $conversion['ext'];
                    $mimeType = $conversion['mime_type'];
                    $diskName = $this->imageService->changeExtension($diskName, 'webp');
                }
            }

            if (! $webpApplied) {
                $this->imageService->resizeAndCompress(
                    $fullPath,
                    $ext,
                    1600,
                    [
                        'jpg' => 82,
                        'png' => 7,
                        'webp' => 82,
                    ],
                    preserveTransparency: true,
                    alwaysReencode: true,
                    logErrors: true
                );
            }

            /*
             * Replica la copertina nella document root pubblica secondaria
             * (public_html su cPanel), quando configurata: senza questo,
             * asset('assets/img/...') può puntare a un file assente sul
             * dominio pubblico anche se presente in public/assets/img.
             * Eseguito prima della registrazione in Libreria media, cosi
             * che un fallimento qui non lasci un Media senza articolo
             * associato che punta a un file non davvero raggiungibile.
             */
            try {
                $this->publicMediaSync->create($fullPath, $diskName);
            } catch (RuntimeException $exception) {
                $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);

                throw $exception;
            }

            /*
             * FASE 5 (missione S2 responsive images): accessoria e
             * best-effort, mai bloccante per la pubblicazione della
             * copertina principale.
             */
            $this->responsiveImageVariants->generateForUpload($fullPath, $diskName);

            $this->mediaService->register(
                $request->user(),
                $filename,
                $diskName,
                $mimeType,
                filesize($fullPath) ?: 0
            );

            $data['cover_image'] = $diskName;
        }

        unset($data['cover_image_upload']);

        // S6 FASE 4 — Str::slug() da solo non garantiva unicità: due
        // articoli con lo stesso titolo collidevano sulla colonna UNIQUE
        // articles.slug con una QueryException non gestita (500 grezzo).
        // Vedi anche il catch-and-retry in store() per la vera finestra di
        // concorrenza che questo controllo da solo non copre.
        $data['slug'] = Article::uniqueSlug($data['title']);
        $data['featured'] = $request->boolean('featured');

        $data['published_at'] = match ($data['status']) {
            Article::STATUS_PUBLISHED => now(),
            Article::STATUS_SCHEDULED => Article::scheduledAtFromEditorialInput(
                $data['published_date'],
                $data['published_time']
            ),
            default => null,
        };

        unset($data['published_date'], $data['published_time']);

        // array_key_exists, non empty(): un body '0' e' valido (passa la
        // regola 'required' del FormRequest) ma empty('0') e' true in PHP,
        // il che salterebbe silenziosamente il calcolo per quel solo caso.
        if (array_key_exists('body', $data)) {
            $data['read_minutes'] = Article::calculateReadMinutes($data['body']);
        }

        return $data;
    }

    public function updateVerification(
        Request $request,
        Article $article
    ) {
        $validated = $request->validate([
            'verification_status' => [
                'required',
                'in:unverified,in_progress,verified,needs_update',
            ],
            'verification_notes' => 'nullable|max:1000',
            'primary_sources' => 'nullable|max:500',
        ]);

        $data = $validated;

        if ($validated['verification_status'] === 'verified') {
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->user()->name;
        }

        $article->update($data);

        return back()->with(
            'success',
            'Stato verifica aggiornato.'
        );
    }

    public function duplicate(Article $article)
    {
        $newArticle = $article->replicate();

        $newArticle->title = 'Copia di — '.$article->title;
        // S6 FASE 4 — un suffisso time() (granularità di 1 secondo) non
        // garantisce unicità: due doppi-click ravvicinati su "Duplica"
        // dello stesso articolo entro lo stesso secondo collidevano sulla
        // colonna UNIQUE articles.slug con una QueryException non gestita.
        // Article::uniqueSlug() copre il caso normale; il catch-and-retry
        // sotto copre la vera finestra di concorrenza (due richieste
        // simultanee che leggono lo stesso "slug libero" prima che una
        // delle due abbia effettivamente scritto).
        $newArticle->slug = Article::uniqueSlug($newArticle->title);
        $newArticle->status = 'draft';
        $newArticle->featured = false;
        $newArticle->views = 0;
        $newArticle->published_at = null;
        $newArticle->verification_status = 'unverified';

        try {
            $newArticle->push();
        } catch (UniqueConstraintViolationException) {
            $newArticle->slug = Article::uniqueSlug($newArticle->title);
            $newArticle->push();
        }

        ActivityLog::record(
            'Articolo duplicato',
            'article',
            $newArticle->id,
            $newArticle->title
        );

        return redirect()
            ->route('admin.articles.edit', $newArticle)
            ->with(
                'success',
                'Articolo duplicato come bozza.'
            );
    }

    public function quickDraft()
    {
        return view('admin.quick-draft');
    }
}
