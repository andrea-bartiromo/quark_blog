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

namespace App\Models;

use App\Services\ContentClusters\PathContinuationNotifier;
use App\Services\InternalLinking\InternalLinkTemporalEligibility;
use App\Services\ProjectEditorialLinkService;
use App\Services\ProjectTaskSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Article extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEW = 'review';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    // Fuso orario in cui la redazione imposta e legge le date di pubblicazione.
    // Tutte le conversioni Europe/Rome <-> UTC passano da qui: nessun altro
    // punto del codice deve applicare ->timezone() "a mano".
    public const EDITORIAL_TIMEZONE = 'Europe/Rome';

    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'body',
        'category', 'cover_image', 'status', 'featured',
        'read_minutes', 'views', 'published_at',
        'verification_status', 'verification_notes',
        'verified_at', 'verified_by', 'primary_sources',
        'cover_alt', 'cover_caption', 'cover_credit',
        'cover_source', 'cover_source_url', 'cover_license',
        'seo_title', 'seo_description', 'canonical_url', 'robots',
        'og_title', 'og_description', 'og_image',
        'twitter_title', 'twitter_description', 'twitter_image',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    /**
     * Garantisce ovunque (form Admin, approvazione Redazione, comando
     * artisan, tinker, factory...) l'invariante: published_at è NULL per
     * draft/review, valorizzato per scheduled/published. Centralizzare la
     * regola qui evita che un singolo controller dimenticato la violi.
     */
    protected static function booted(): void
    {
        static::saving(function (Article $article) {
            if (in_array($article->status, [self::STATUS_DRAFT, self::STATUS_REVIEW], true)) {
                $article->published_at = null;

                return;
            }

            if ($article->status === self::STATUS_PUBLISHED && is_null($article->published_at)) {
                $article->published_at = now();
            }
        });

        static::saved(fn (Article $article) => app(ProjectTaskSyncService::class)->syncForArticle($article));

        static::deleted(fn (Article $article) => app(ProjectTaskSyncService::class)->invalidateForDeletedArticle($article->id));

        // Piano Editoriale automatico (Blocco F): solo alla creazione, mai
        // sui salvataggi successivi — un collegamento rimosso a mano non
        // deve poter essere ripristinato automaticamente da una modifica.
        static::created(fn (Article $article) => app(ProjectEditorialLinkService::class)->linkToDefaultProject($article));

        // Registra lo slug precedente per un redirect 301 (vedi
        // ArticleSlugRedirect e ArticleController::show()) invece di
        // lasciare che un link esterno o un risultato di ricerca non
        // ancora ricrawlato punti a un 404 dopo una rinomina.
        static::updated(function (Article $article) {
            if (! $article->wasChanged('slug')) {
                return;
            }

            $oldSlug = $article->getOriginal('slug');

            if (blank($oldSlug) || $oldSlug === $article->slug) {
                return;
            }

            ArticleSlugRedirect::updateOrCreate(
                ['old_slug' => $oldSlug],
                ['article_id' => $article->id]
            );

            // Il nuovo slug potrebbe essere stato, in passato, lo slug di
            // un'altra rinomina: un redirect che punterebbe da uno slug
            // ora di nuovo "attivo" verso se stesso non ha senso e
            // andrebbe rimosso, non lasciato a fare da eco.
            ArticleSlugRedirect::where('old_slug', $article->slug)->delete();
        });

        // "Avvisami quando continua" (Percorsi): il punto reale e comune di
        // pubblicazione, valido sia per il publish manuale
        // (Admin\ArticleController::update(), Admin\ReviewController::approve())
        // sia per quello programmato (PublishScheduledArticles) — entrambi
        // passano da Eloquent update()/save(), quindi da qui. created()
        // copre il caso, raro ma possibile dallo stesso form admin, di un
        // articolo creato già con status=published.
        static::created(function (Article $article) {
            if ($article->status === self::STATUS_PUBLISHED) {
                app(PathContinuationNotifier::class)->notifyIfPublished($article);
            }
        });

        static::updated(function (Article $article) {
            if ($article->wasChanged('status') && $article->status === self::STATUS_PUBLISHED) {
                app(PathContinuationNotifier::class)->notifyIfPublished($article);
            }
        });
    }

    // Etichette leggibili per lo stato di verifica
    public static array $verificationLabels = [
        'unverified' => 'Non verificato',
        'in_progress' => 'In verifica',
        'verified' => 'Verificato',
        'needs_update' => 'Aggiornamento necessario',
    ];

    // Colori badge per lo stato di verifica
    public static array $verificationColors = [
        'unverified' => '#ef4444',
        'in_progress' => '#f59e0b',
        'verified' => '#22c55e',
        'needs_update' => '#6366f1',
    ];

    public function getVerificationLabelAttribute(): string
    {
        return static::$verificationLabels[$this->verification_status] ?? 'Sconosciuto';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->where('status', 'approved');
    }

    public function articleViews()
    {
        return $this->hasMany(ArticleView::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_article')->withTimestamps();
    }

    public function contentClusters()
    {
        return $this->belongsToMany(ContentCluster::class, 'article_content_cluster')
            ->withPivot(['position', 'is_primary', 'transition_text'])
            ->withTimestamps();
    }

    public function linkSuggestions()
    {
        return $this->hasMany(ArticleLinkSuggestion::class, 'source_article_id');
    }

    /**
     * Suggerimenti di collegamento interno ancora da rivedere per questo
     * articolo, pronti per il pannello "Collegamenti interni suggeriti" —
     * stessa query condivisa da Admin e Redazione, per evitare che le due
     * aree mostrino elenchi diversi se la query dovesse cambiare.
     */
    public function proposedLinkSuggestions()
    {
        // Codex (PR #165, P2 round 9): la riga resta 'proposed' in DB finché
        // non parte una nuova "Analizza" (o "Inserisci"/il salvataggio la
        // rivalutano) — ma tra quel momento e l'apertura di QUESTA pagina il
        // target può essere diventato non più sicuro (riprogrammato dopo la
        // source, o retrocesso). Senza questo filtro il form la mostrerebbe
        // comunque con l'etichetta "sarà pubblico prima di questo articolo"
        // (ArticleLinkSuggestionController::serializeSuggestions()) — una
        // dichiarazione di sicurezza ormai falsa, prima ancora che la
        // redazione clicchi qualunque cosa. Nessuna scrittura qui: è un
        // filtro di sola lettura per il rendering, lo stato 'proposed' in DB
        // resta quello che era finché un'azione esplicita (Analizza/
        // Inserisci/Salva) non lo aggiorna davvero.
        //
        // Codex, PR #165, P2 round 10: il filtro va applicato PRIMA del
        // limite MAX_PROPOSED_RESULTS, non dopo. Se le righe con punteggio
        // più alto sono proprio quelle diventate non sicure, un
        // ->limit()->get()->filter() le include nella finestra del LIMIT e
        // le scarta dopo — restituendo un pannello vuoto anche quando
        // esistono altri suggerimenti sicuri appena sotto in classifica.
        // Nessun limite SQL qui: il numero di righe 'proposed' per un
        // singolo articolo è comunque piccolo (soglia di punteggio +
        // MAX_PROPOSED_RESULTS già applicati da analyzeForSource()), il
        // taglio ai primi MAX_PROPOSED_RESULTS avviene in PHP DOPO il
        // filtro, sull'ordinamento per punteggio già applicato dalla query.
        $temporalEligibility = app(InternalLinkTemporalEligibility::class);

        return $this->linkSuggestions()
            ->proposed()
            // status/published_at (V2.1): il pannello "Collegamenti interni
            // suggeriti" deve poter mostrare che un target è ancora
            // programmato (mai come se fosse già pubblico senza contesto —
            // vedi ArticleLinkSuggestionController::serializeSuggestions()).
            ->with('targetArticle:id,title,slug,status,published_at')
            ->orderByDesc('confidence_score')
            ->get()
            ->filter(fn (ArticleLinkSuggestion $s) => $s->targetArticle !== null && $temporalEligibility->isTargetSafeForSource($this, $s->targetArticle))
            ->take(ArticleLinkSuggestion::MAX_PROPOSED_RESULTS)
            ->values();
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('featured', true);
    }

    public function scopeByCategory(Builder $q, string $category): Builder
    {
        return $q->where('category', $category);
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_SCHEDULED)
            ->orderBy('published_at');
    }

    /**
     * Internal Linking V2.1 — pre-filtro SQL per SOLO il ramo "scheduled
     * temporalmente sicuro" della candidate selection del suggeritore
     * (App\Services\ArticleLinkSuggestionService::analyzeForSource()):
     * stessa regola di App\Services\InternalLinking\
     * InternalLinkTemporalEligibility::isTargetSafeForSource() ramo B, qui
     * a livello DB per evitare di caricare in memoria candidati che non
     * potrebbero mai essere eleggibili (draft/review, o scheduled con
     * published_at non strettamente precedente a quello di $source).
     *
     * Interrogato SEPARATAMENTE dai candidati pubblicati (scopePublished()),
     * ciascuno con una propria quota LIMIT indipendente (Codex, PR #165, P2
     * round 4 e round 5): un'unica query con ORDER BY/LIMIT condiviso tra i
     * due gruppi non può restare corretta in entrambe le direzioni — un
     * gruppo abbastanza numeroso scalza sempre l'altro dalla finestra dei
     * candidati, qualunque sia il criterio di ordinamento scelto tra i due.
     *
     * Il chiamante invoca questo scope solo quando $source stessa è
     * 'scheduled' con published_at valorizzato (altrimenti il ramo B non si
     * applica mai — vedi isTargetSafeForSource()); lo scope non ripete qui
     * quel controllo. La fonte di verità applicativa resta comunque
     * isTargetSafeForSource(), riapplicata subito dopo il fetch in
     * analyzeForSource() come garanzia definitiva indipendente da questa
     * query (vedi commento lì).
     */
    public function scopeScheduledSafeAsLinkTargetFor(Builder $q, self $source): Builder
    {
        return $q->where('status', self::STATUS_SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<', $source->published_at);
    }

    // ── Stato ─────────────────────────────────────────────────

    /**
     * @return array<string, string> Valore enum => etichetta leggibile, nell'ordine
     *                               in cui vanno proposte nel select "Stato articolo".
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Bozza',
            self::STATUS_REVIEW => 'In revisione',
            self::STATUS_SCHEDULED => 'Programmato',
            self::STATUS_PUBLISHED => 'Pubblicato',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Un articolo 'scheduled' il cui orario è già passato: lo scheduler non
     * è ancora transitato (in ritardo) o è momentaneamente fermo.
     */
    public function isScheduleOverdue(): bool
    {
        return $this->isScheduled() && $this->published_at !== null && $this->published_at->isPast();
    }

    // ── Fuso orario redazionale ──────────────────────────────────

    /**
     * published_at (memorizzato in UTC) convertito nel fuso orario della
     * redazione, per la visualizzazione in badge/banner/form.
     */
    public function publishedAtForEditors(): ?Carbon
    {
        return $this->published_at?->clone()->timezone(self::EDITORIAL_TIMEZONE);
    }

    /**
     * Converte una coppia data/ora inserita dalla redazione (Europe/Rome,
     * ora locale con eventuale ora legale) nell'istante UTC da salvare in
     * published_at. Unico punto del codice che esegue questa conversione.
     */
    public static function scheduledAtFromEditorialInput(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", self::EDITORIAL_TIMEZONE)->utc();
    }

    // ── Accessor ──────────────────────────────────────────────

    public function getReadTimeAttribute(): string
    {
        return $this->read_minutes.' min di lettura';
    }

    /**
     * Minuti di lettura stimati dal corpo dell'articolo: conteggio token
     * separati da spazi sul testo (tag HTML rimossi, entità come &nbsp;
     * decodificate) diviso 200 parole/minuto — velocità di lettura media
     * convenzionale per un lettore adulto in italiano — con arrotondamento
     * all'intero più vicino e minimo di 1 minuto. Non usa str_word_count():
     * tratta l'apostrofo tipografico (') diversamente da quello dritto (')
     * e conta le entità HTML non decodificate (es. &nbsp;) come parole,
     * producendo risultati incoerenti con un semplice split sugli spazi —
     * che è anche l'unica tokenizzazione replicabile in modo affidabile
     * lato client per l'anteprima (partials/article-read-minutes-script).
     * Unico punto del codice che esegue questo calcolo: Admin\ArticleController
     * e Redazione\ArticleController lo richiamano entrambi invece di
     * duplicare la formula (in precedenza usavano due formule diverse e non
     * documentate, con risultati incoerenti tra le due aree).
     */
    public static function calculateReadMinutes(?string $body): int
    {
        $text = html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tokens = preg_split('/[\s\x{00A0}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($tokens);

        return max(1, (int) round($wordCount / 200));
    }

    // ── Mutator ───────────────────────────────────────────────

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    // ── Metodi ────────────────────────────────────────────────

    /**
     * S6 FASE 4 — slug derivato con GARANZIA di unicità: Str::slug($title)
     * da solo (usato prima da Admin\ArticleController::store() e
     * duplicate(), quest'ultimo con un suffisso time() a granularità di
     * 1 secondo) non garantiva nulla — due articoli con lo stesso titolo,
     * o un doppio invio dello stesso form, collidevano sulla colonna UNIQUE
     * articles.slug con una QueryException non gestita (500 grezzo, non un
     * errore leggibile). Qui: prova lo slug "pulito", poi -2/-3/... finché
     * non trova un valore libero. Questo loop da solo NON è sufficiente
     * sotto vera concorrenza (finestra tra il SELECT e l'INSERT) — vedi
     * anche il catch-and-retry attorno alla persistenza effettiva nei
     * chiamanti, che resta l'unica vera garanzia contro la race, con questo
     * metodo come percorso comune per il caso normale (nessuna collisione)
     * e per il retry dopo un urto reale.
     */
    public static function uniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function related(int $limit = 3)
    {
        // Nessun ->with('author'): l'unico consumer di questo metodo
        // (ArticleController::show() -> resources/views/articles/partials/
        // related-articles.blade.php) mostra solo title/excerpt/cover_image/
        // slug, mai l'autore — l'eager load era una query sprecata a ogni
        // caricamento di pagina articolo con almeno un correlato.
        return static::published()
            ->byCategory($this->category)
            ->where('id', '!=', $this->id)
            ->limit($limit)
            ->get();
    }

    /**
     * Combinazioni valide per il meta tag robots (nessun campo composto: il
     * valore memorizzato è già nel formato pronto per l'attributo content).
     * Condiviso dalle FormRequest Admin e Redazione per evitare di
     * duplicare l'elenco in due punti diversi.
     *
     * @return array<int, string>
     */
    public static function robotsOptions(): array
    {
        return ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
    }

    // ── SEO / Meta ────────────────────────────────────────────
    //
    // Ogni metodo restituisce il valore da usare in pagina, applicando la
    // catena di fallback quando il campo editoriale è vuoto. I campi grezzi
    // (es. $article->seo_title) restano quelli salvati sul record — anche se
    // vuoti — cosi il form di modifica può continuare a mostrare il valore
    // realmente memorizzato senza il fallback già applicato.

    public function metaTitle(): string
    {
        return filled($this->seo_title) ? $this->seo_title : $this->title;
    }

    public function metaDescription(): string
    {
        if (filled($this->seo_description)) {
            return $this->seo_description;
        }

        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        $plainBody = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->body)) ?? '');

        return Str::limit($plainBody, 160, '');
    }

    public function metaCanonicalUrl(): string
    {
        return filled($this->canonical_url) ? $this->canonical_url : route('articolo', $this->slug);
    }

    public function metaRobots(): string
    {
        return filled($this->robots) ? $this->robots : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1';
    }

    public function metaOgTitle(): string
    {
        return filled($this->og_title) ? $this->og_title : $this->metaTitle();
    }

    public function metaOgDescription(): string
    {
        return filled($this->og_description) ? $this->og_description : $this->metaDescription();
    }

    public function metaOgImage(): string
    {
        if (filled($this->og_image)) {
            return asset('assets/img/'.$this->og_image);
        }

        if (filled($this->cover_image)) {
            return asset('assets/img/'.$this->cover_image);
        }

        // Fallback raster globale (config('laboratorio.default_share_image')),
        // mai hero-placeholder.svg: Facebook/Twitter/LinkedIn non renderizzano
        // in modo affidabile un SVG come immagine di condivisione.
        return asset(config('laboratorio.default_share_image'));
    }

    public function metaTwitterTitle(): string
    {
        return filled($this->twitter_title) ? $this->twitter_title : $this->metaTitle();
    }

    public function metaTwitterDescription(): string
    {
        return filled($this->twitter_description) ? $this->twitter_description : $this->metaDescription();
    }

    public function metaTwitterImage(): string
    {
        if (filled($this->twitter_image)) {
            return asset('assets/img/'.$this->twitter_image);
        }

        if (filled($this->cover_image)) {
            return asset('assets/img/'.$this->cover_image);
        }

        return asset(config('laboratorio.default_share_image'));
    }
}
