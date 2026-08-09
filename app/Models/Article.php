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
        return $this->linkSuggestions()
            ->proposed()
            ->with('targetArticle:id,title,slug')
            ->orderByDesc('confidence_score')
            ->limit(ArticleLinkSuggestion::MAX_PROPOSED_RESULTS)
            ->get();
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

    // ── Stato ─────────────────────────────────────────────────

    /**
     * @return array<string, string> Valore enum => etichetta leggibile, nell'ordine
     *                                in cui vanno proposte nel select "Stato articolo".
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

    public function incrementViews(): void
    {
        $this->increment('views');
    }

    public function related(int $limit = 3)
    {
        return static::published()
            ->byCategory($this->category)
            ->where('id', '!=', $this->id)
            ->with('author')
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
