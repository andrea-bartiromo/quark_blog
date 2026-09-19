<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TuringPageController;
use App\Models\SpecialPage;
use App\Services\ImageService;
use App\Services\PublicMediaSyncService;
use App\Services\Turing\TuringCompletenessReportService;
use App\Services\Turing\TuringConceptMapService;
use App\Services\Turing\TuringInternalBetaReadinessService;
use App\Services\Turing\TuringNavigationMetricsService;
use App\Support\TuringPreviewLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class TuringController extends Controller
{
    private const SLUG = 'turing';

    public function __construct(
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly ImageService $imageService,
        private readonly TuringNavigationMetricsService $navigationMetrics,
    ) {}

    public function edit()
    {
        $page = $this->firstOrCreateTuringPage();
        $navigationMetrics = $this->navigationMetrics->aggregateViews();
        $cards = $this->resolvedCards($page);

        return view('admin.turing-lite', compact('page', 'navigationMetrics', 'cards'));
    }

    /**
     * Cantiere 59 (programma "100 cantieri Kairus"): sola lettura,
     * strumento interno per l'editor — trascrizione della mappa dei
     * concetti dello Speciale già redatta in
     * docs/00_Governance/Architettura_Editoriale_v1.0.docx §4
     * (TuringConceptMapService), non una funzionalità pubblica.
     */
    /**
     * Cantiere 67 (programma "100 cantieri Kairus"): "Report completezza
     * Turing" — sola lettura, strumento interno per l'editor. Aggrega dal
     * vero stato attuale (mai un'istantanea statica) fonti registrate,
     * copertura della mappa concettuale e metriche di navigazione per
     * ciascun capitolo. Vedi il docblock di TuringCompletenessReportService
     * per cosa resta volutamente fuori scope (accessibilità/performance).
     */
    public function completenessReport(TuringCompletenessReportService $completenessReport)
    {
        $report = $completenessReport->build();

        return view('admin.turing-completeness-report', compact('report'));
    }

    /**
     * Cantiere 69 (programma "100 cantieri Kairus"): "Checklist beta
     * interna Turing" — sola lettura, mai una scrittura. Vedi il docblock
     * di TuringInternalBetaReadinessService per le condizioni e cosa resta
     * volutamente fuori scope (nessuna decisione GO/NO-GO qui, nessuna
     * assegnazione owner).
     */
    public function internalBetaReadiness(TuringInternalBetaReadinessService $readiness)
    {
        return view('admin.turing-internal-beta-readiness', [
            'conditions' => $readiness->assess(),
            'allConditionsMet' => $readiness->allConditionsMet(),
        ]);
    }

    public function conceptMap()
    {
        $conceptsByChapter = TuringConceptMapService::conceptsByChapter();

        return view('admin.turing-concept-map', compact('conceptsByChapter'));
    }

    /**
     * Cantiere 63 (programma "100 cantieri Kairus"): "Prototipo non
     * pubblico navigazione Turing" — finché `turing.chapters_public` è
     * false, TuringPageController::index() mostra a chiunque, editor
     * autenticati inclusi, solo la landing "In arrivo"
     * (turing.coming-soon): nessuno può rivedere l'hub reale, né la rete
     * di navigazione fra i 5 capitoli, prima di rendere pubblico lo
     * Speciale. Stesso pattern già stabilito da
     * Admin\ContentClusterController::preview() (Cantiere 48) e
     * Admin\CategoryController::preview() (Cantiere 11): sola lettura,
     * ANCORA dentro il gruppo di rotte auth+editor, mai una route
     * pubblica — riusa la stessa vista pubblica reale (mai una copia),
     * con `previewMode` che aggiunge solo un banner e `noindex,nofollow`
     * come difesa in profondità.
     *
     * Non chiama mai TuringNavigationMetricsService::recordView(): la
     * vista di anteprima non deve mai contaminare le metriche di
     * navigazione reali, stesso principio del marcatore
     * X-Kairus-Internal-Audit già usato dalla route pubblica per gli
     * audit interni (Codex PR #623 P1).
     *
     * Codex (PR #629, P2): ogni vista Turing (hub e capitoli) usa
     * App\Support\TuringPreviewLink per risolvere i propri link interni
     * — con `previewMode=true` restano tutti dentro le rotte di
     * anteprima invece di puntare a /turing/* reale, che con
     * chapters_public=false reindirizzerebbe l'editor fuori
     * dall'anteprima. Qui riscrive anche gli URL delle card/blocchi
     * editoriali (dati, non `route()` diretto nella vista) verso lo
     * stesso capitolo in anteprima.
     */
    public function previewHub(TuringPageController $pageController)
    {
        $data = $this->withPreviewChapterLinks($pageController->buildIndexViewData());

        return view('turing.index', $data + ['previewMode' => true]);
    }

    /**
     * Anteprima di un singolo capitolo (vedi previewHub()): riusa la
     * stessa vista pubblica reale di TuringPublicController, che per
     * questi 5 capitoli non riceve alcun dato dal controller (ogni vista
     * legge da sé SpecialPage::where('slug','turing') — vedi
     * turing/enigma.blade.php) — qui basta passare previewMode=true, la
     * vista stessa risolve i propri link con TuringPreviewLink.
     */
    public function previewChapter(string $chapter)
    {
        abort_unless(in_array($chapter, $this->realChapters(), true), 404);

        return view("turing.$chapter", ['previewMode' => true]);
    }

    /**
     * Riscrive gli URL delle card dell'hub e dei blocchi editoriali
     * (unica parte della rete di navigazione guidata da dati, non da
     * `route()` scritto direttamente nella vista) verso la rotta di
     * anteprima dello stesso capitolo — solo quando l'URL punta
     * esattamente a un capitolo reale, mai per un link esterno o
     * arbitrario che un editor potrebbe aver impostato via CMS.
     */
    private function withPreviewChapterLinks(array $data): array
    {
        $chapters = $this->realChapters();

        $rewrite = function (?string $url) use ($chapters): ?string {
            if (blank($url)) {
                return $url;
            }

            foreach ($chapters as $chapter) {
                if ($url === '/turing/'.$chapter || $url === route('turing.'.$chapter)) {
                    return TuringPreviewLink::chapter($chapter, true);
                }
            }

            return $url;
        };

        $data['cards'] = $data['cards']->map(function (array $card) use ($rewrite) {
            if (array_key_exists('url', $card)) {
                $card['url'] = $rewrite($card['url']);
            }

            return $card;
        });

        $data['editorialBlocks'] = $data['editorialBlocks']->map(function (array $block) use ($rewrite) {
            if (array_key_exists('link_url', $block)) {
                $block['link_url'] = $rewrite($block['link_url']);
            }

            return $block;
        });

        return $data;
    }

    private function realChapters(): array
    {
        return array_values(array_filter(
            TuringNavigationMetricsService::CHAPTERS,
            fn (string $chapter) => $chapter !== 'hub'
        ));
    }

    public function update(Request $request)
    {
        $page = $this->firstOrCreateTuringPage();
        $data = $this->validatedData($request);

        try {
            $data = $this->resolveTopLevelImages($request, $data, $page->content ?? []);

            $page->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active'),
                'content' => $this->contentPayload($request, $data),
            ]);
        } catch (RuntimeException $exception) {
            report($exception);

            return back()->withInput()->withErrors(['content' => 'Impossibile pubblicare una delle immagini caricate. Riprova o contatta l\'assistenza.']);
        }

        return redirect()
            ->route('admin.turing')
            ->with('success', 'Speciale Turing aggiornato.');
    }

    /**
     * Cantiere 58 (programma "100 cantieri Kairus"): sposta la card di un
     * capitolo di una posizione (su/giù) nell'ordine con cui compaiono
     * nell'hub `/turing` — l'ordine di rendering di `turing.blade.php` è
     * già l'ordine dell'array `content.cards` (nessuna colonna "position"
     * separata da tenere sincronizzata). Nessun contenuto editoriale
     * nuovo: sposta solo le card già esistenti, non ne crea né modifica
     * il testo.
     */
    public function moveCard(Request $request, int $index)
    {
        $page = $this->firstOrCreateTuringPage();
        $cards = $this->resolvedCards($page);

        $direction = $request->input('direction');
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (! array_key_exists($index, $cards) || ! array_key_exists($target, $cards)) {
            return back()->withErrors(['cards' => 'Spostamento non valido.']);
        }

        [$cards[$index], $cards[$target]] = [$cards[$target], $cards[$index]];

        $page->update([
            'content' => [...$page->content, 'cards' => array_values($cards)],
        ]);

        return redirect()->route('admin.turing')->withFragment('cards')->with('success', 'Ordine dei capitoli aggiornato.');
    }

    /**
     * Codex (PR #624, P2): quando `content` è ancora vuoto (pagina appena
     * creata da `firstOrCreateTuringPage()`), l'editor mostra le 3 card
     * di route di default — le stesse usate dal rendering pubblico
     * (`TuringPageController::defaultRouteCards()`) quando non esiste
     * ancora un override — con pulsanti di riordino attivi. Prima di
     * questo fix moveCard() leggeva `content['cards']` da solo, trovava
     * un array vuoto e rifiutava ogni spostamento ("Spostamento non
     * valido"): l'editor vedeva pulsanti che non facevano mai nulla.
     * Riusare la stessa risoluzione in edit() e moveCard() li tiene
     * sempre coerenti.
     */
    private function resolvedCards(SpecialPage $page): array
    {
        $cards = $page->content['cards'] ?? [];

        return $cards !== [] ? $cards : TuringPageController::defaultRouteCards();
    }

    private function firstOrCreateTuringPage(): SpecialPage
    {
        return SpecialPage::firstOrCreate(
            ['slug' => self::SLUG],
            [
                'title' => 'Alan Turing',
                'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
                'is_active' => true,
                'content' => [],
            ]
        );
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|max:150',
            'description' => 'nullable|max:500',
            'is_active' => 'nullable|boolean',
            'hero_kicker' => 'nullable|max:120',
            'hero_title' => 'required|max:150',
            'hero_lead' => 'required|max:900',
            'hero_primary_label' => 'nullable|max:80',
            'hero_secondary_label' => 'nullable|max:80',
            'hero_portrait_title' => 'nullable|max:150',
            'hero_portrait_text' => 'nullable|max:220',
            'hero_portrait_initials' => 'nullable|max:12',
            'hero_portrait_years' => 'nullable|max:40',
            'hero_terminal_title' => 'nullable|max:120',
            'hero_terminal_lines' => 'nullable|array',
            'hero_terminal_lines.*' => 'nullable|max:160',
            'hero_background_image' => 'nullable|max:500',
            'hero_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'hero_background_image_remove' => 'nullable|boolean',
            'hero_portrait_image' => 'nullable|max:500',
            'hero_portrait_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'hero_portrait_image_remove' => 'nullable|boolean',
            'home_teaser_kicker' => 'nullable|max:120',
            'home_teaser_title' => 'nullable|max:180',
            'home_teaser_text' => 'nullable|max:700',
            'home_teaser_cta_label' => 'nullable|max:100',
            'home_teaser_terminal_title' => 'nullable|max:120',
            'home_teaser_terminal_lines' => 'nullable|array',
            'home_teaser_terminal_lines.*' => 'nullable|max:160',
            'home_teaser_background_image' => 'nullable|max:500',
            'home_teaser_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'home_teaser_background_image_remove' => 'nullable|boolean',
            'intro_kicker' => 'nullable|max:120',
            'intro_title' => 'required|max:180',
            'intro_text' => 'required|max:900',
            'intro_background_image' => 'nullable|max:500',
            'intro_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'intro_background_image_remove' => 'nullable|boolean',
            'why_kicker' => 'nullable|max:120',
            'why_title' => 'required|max:180',
            'why_text' => 'required|max:1000',
            'why_background_image' => 'nullable|max:500',
            'why_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'why_background_image_remove' => 'nullable|boolean',
            'final_kicker' => 'nullable|max:120',
            'final_title' => 'required|max:180',
            'final_text' => 'required|max:500',
            'final_background_image' => 'nullable|max:500',
            'final_background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'final_background_image_remove' => 'nullable|boolean',
            'cards' => 'nullable|array',
            'cards.*.label' => 'nullable|max:120',
            'cards.*.title' => 'nullable|max:150',
            'cards.*.text' => 'nullable|max:500',
            'cards.*.url' => 'nullable|max:255',
            'cards.*.style' => 'nullable|in:enigma,ai,legacy',
            'cards.*.image' => 'nullable|max:500',
            'cards.*.image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks' => 'nullable|array',
            'editorial_blocks.*.key' => 'nullable|max:80',
            'editorial_blocks.*.enabled' => 'nullable|boolean',
            'editorial_blocks.*.layout' => 'nullable|in:text,image_left,image_right,dark_card,feature_grid',
            'editorial_blocks.*.kicker' => 'nullable|max:120',
            'editorial_blocks.*.title' => 'nullable|max:180',
            'editorial_blocks.*.text' => 'nullable|max:1400',
            'editorial_blocks.*.image' => 'nullable|max:500',
            'editorial_blocks.*.image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks.*.image_remove' => 'nullable|boolean',
            'editorial_blocks.*.background_image' => 'nullable|max:500',
            'editorial_blocks.*.background_image_upload' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:16384',
            'editorial_blocks.*.background_image_remove' => 'nullable|boolean',
            'editorial_blocks.*.link_label' => 'nullable|max:100',
            'editorial_blocks.*.link_url' => 'nullable|max:255',
            'internal_links' => 'nullable|array',
            'decorative_images' => 'nullable|array',
            'why_items' => 'nullable|array',
            'timeline' => 'nullable|array',
            'timeline.*.details' => 'nullable|max:2000',
        ]);
    }

    private function resolveTopLevelImages(Request $request, array $data, array $existingContent): array
    {
        foreach ($this->topLevelImageFields() as $field => $contentPath) {
            if ($request->boolean($field.'_remove')) {
                $data[$field] = null;

                continue;
            }

            if ($uploaded = $this->uploadedImage($request, $field.'_upload')) {
                $data[$field] = $uploaded;

                continue;
            }

            $manual = $request->has($field) ? trim((string) ($data[$field] ?? '')) : null;
            $previous = data_get($existingContent, $contentPath);

            $data[$field] = $manual !== '' ? $manual : $previous;
        }

        return $data;
    }

    private function topLevelImageFields(): array
    {
        return [
            'hero_background_image' => 'hero.background_image',
            'hero_portrait_image' => 'hero.portrait_image',
            'home_teaser_background_image' => 'home_teaser.background_image',
            'intro_background_image' => 'intro.background_image',
            'why_background_image' => 'why.background_image',
            'final_background_image' => 'final.background_image',
        ];
    }

    private function contentPayload(Request $request, array $data): array
    {
        return [
            'hero' => $this->heroPayload($request, $data),
            'home_teaser' => $this->homeTeaserPayload($request, $data),
            'intro' => $this->introPayload($data),
            'cards' => $this->cards($request),
            'editorial_blocks' => $this->editorialBlocks($request),
            'internal_links' => $request->input('internal_links', []),
            'decorative_images' => $request->input('decorative_images', []),
            'why' => $this->whyPayload($request, $data),
            'timeline' => $request->input('timeline', []),
            'final' => $this->finalPayload($data),
        ];
    }

    private function heroPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['hero_kicker'] ?? null,
            'title' => $data['hero_title'],
            'lead' => $data['hero_lead'],
            'primary_label' => $data['hero_primary_label'] ?? 'Esplora Enigma',
            'secondary_label' => $data['hero_secondary_label'] ?? 'Vai all’IA moderna',
            'portrait_title' => $data['hero_portrait_title'] ?? null,
            'portrait_text' => $data['hero_portrait_text'] ?? null,
            'portrait_initials' => $data['hero_portrait_initials'] ?? 'AT',
            'portrait_years' => $data['hero_portrait_years'] ?? '1912 / 1954',
            'terminal_title' => $data['hero_terminal_title'] ?? 'Turing Archive',
            'terminal_lines' => $this->filledLines($request, 'hero_terminal_lines'),
            'background_image' => $data['hero_background_image'] ?? null,
            'portrait_image' => $data['hero_portrait_image'] ?? null,
        ];
    }

    private function homeTeaserPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['home_teaser_kicker'] ?? 'Special Project',
            'title' => $data['home_teaser_title'] ?? 'Alan Turing: l’uomo che ha decifrato il futuro.',
            'text' => $data['home_teaser_text'] ?? 'Una nuova area speciale di Kairus dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.',
            'cta_label' => $data['home_teaser_cta_label'] ?? 'Entra nella Turing Experience',
            'terminal_title' => $data['home_teaser_terminal_title'] ?? 'TURING ARCHIVE',
            'terminal_lines' => $this->filledLines($request, 'home_teaser_terminal_lines'),
            'background_image' => $data['home_teaser_background_image'] ?? null,
        ];
    }

    private function introPayload(array $data): array
    {
        return [
            'kicker' => $data['intro_kicker'] ?? null,
            'title' => $data['intro_title'],
            'text' => $data['intro_text'],
            'background_image' => $data['intro_background_image'] ?? null,
        ];
    }

    private function whyPayload(Request $request, array $data): array
    {
        return [
            'kicker' => $data['why_kicker'] ?? null,
            'title' => $data['why_title'],
            'text' => $data['why_text'],
            'background_image' => $data['why_background_image'] ?? null,
            'items' => $request->input('why_items', []),
        ];
    }

    private function finalPayload(array $data): array
    {
        return [
            'kicker' => $data['final_kicker'] ?? null,
            'title' => $data['final_title'],
            'text' => $data['final_text'],
            'background_image' => $data['final_background_image'] ?? null,
        ];
    }

    private function filledLines(Request $request, string $key): array
    {
        return collect($request->input($key, []))
            ->filter(fn ($line) => filled($line))
            ->values()
            ->all();
    }

    private function cards(Request $request): array
    {
        return collect($request->input('cards', []))
            ->map(function ($item, $index) use ($request) {
                $item['image'] = $this->resolveNestedImage($request, $item, "cards.$index", 'image');

                return $item;
            })
            ->filter(fn ($item) => filled($item['title'] ?? null) || filled($item['image'] ?? null))
            ->values()
            ->all();
    }

    private function editorialBlocks(Request $request): array
    {
        return collect($request->input('editorial_blocks', []))
            ->map(function ($item, $index) use ($request) {
                $item['enabled'] = ! empty($item['enabled']);
                $item['image'] = $this->resolveNestedImage($request, $item, "editorial_blocks.$index", 'image');
                $item['background_image'] = $this->resolveNestedImage($request, $item, "editorial_blocks.$index", 'background_image');

                return $item;
            })
            ->filter(fn ($item) => filled($item['title'] ?? null)
                || filled($item['text'] ?? null)
                || filled($item['image'] ?? null)
                || filled($item['background_image'] ?? null))
            ->values()
            ->all();
    }

    private function resolveNestedImage(Request $request, array $item, string $baseKey, string $field): ?string
    {
        if ($request->boolean($baseKey.'.'.$field.'_remove')) {
            return null;
        }

        $uploaded = $this->uploadedImage($request, $baseKey.'.'.$field.'_upload');
        $manual = trim((string) ($item[$field] ?? ''));

        return $uploaded ?: ($manual !== '' ? $manual : null);
    }

    private function uploadedImage(Request $request, string $key): ?string
    {
        if (! $request->hasFile($key) || ! $request->file($key)->isValid()) {
            return null;
        }

        $file = $request->file($key);
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $diskName = $filename.'-'.date('YmdHis').'-'.substr(md5((string) random_int(1, PHP_INT_MAX)), 0, 6).'.'.$extension;
        $uploadPath = public_path('assets/img');

        // Delega a ImageService (mai $file->move() + ricostruzione manuale
        // del path con DIRECTORY_SEPARATOR): è l'unico punto che normalizza
        // in modo affidabile un $uploadPath potenzialmente misto (public_path()
        // su Windows può restituire "/" e "\" nello stesso path) prima di
        // scrivere il file e restituire il path da usare per il cleanup.
        $fullPath = $this->imageService->upload($file, $uploadPath, $diskName);

        try {
            $this->publicMediaSync->create($fullPath, $diskName);
        } catch (RuntimeException $exception) {
            $this->publicMediaSync->cleanupAfterFailedCreate($fullPath);

            throw $exception;
        }

        return $diskName;
    }
}
