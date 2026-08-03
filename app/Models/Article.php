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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
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

    // ── Accessor ──────────────────────────────────────────────

    public function getReadTimeAttribute(): string
    {
        return $this->read_minutes.' min di lettura';
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
