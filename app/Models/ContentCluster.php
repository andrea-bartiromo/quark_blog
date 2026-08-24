<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContentCluster extends Model
{
    use HasFactory;

    public const LIFECYCLE_UPDATING = 'updating';

    public const LIFECYCLE_COMPLETE = 'complete';

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'description',
        'cover_image',
        'seo_title',
        'seo_description',
        'pillar_article_id',
        'is_active',
        'publish_at',
        'lifecycle_status',
        'sort_order',
        'takeaways',
        'guiding_questions',
        'closing_title',
        'closing_text',
        'curator_note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'publish_at' => 'datetime',
        'sort_order' => 'integer',
        'pillar_article_id' => 'integer',
        'takeaways' => 'array',
        'guiding_questions' => 'array',
    ];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_content_cluster')
            ->withPivot(['position', 'is_primary', 'transition_text'])
            ->withTimestamps()
            ->orderByPivot('position')
            ->orderBy('articles.title');
    }

    public function pillarArticle()
    {
        return $this->belongsTo(Article::class, 'pillar_article_id');
    }

    public function pathSubscribers()
    {
        return $this->hasMany(ContentClusterSubscriber::class, 'content_cluster_id');
    }

    /**
     * Unica definizione di "questo Percorso accetta nuove iscrizioni
     * email adesso" — usata sia dalla UI pubblica (per mostrare o no il
     * form) sia dal controller di iscrizione (per rifiutare un submit
     * diretto su un Percorso concluso o inattivo, Parti 14/15).
     */
    public function acceptsPathSubscriptions(): bool
    {
        return $this->is_active && $this->isUpdating();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Percorsi Scheduling V1 (docs/PERCORSI_SCHEDULING_V1_SPEC.md): unica
     * source of truth per "questo Percorso è raggiungibile pubblicamente
     * ADESSO", che futuri consumer pubblici (controller, sitemap,
     * ArticlePathNavigation, ecc.) devono riusare invece di replicare le
     * quattro condizioni sotto. Nessuno di questi consumer è ancora
     * stato riscritto per usarla: quel lavoro resta esplicitamente fuori
     * dallo scope di questa PR (integrazione in una missione separata) —
     * qui esiste solo lo schema e questa policy, senza ancora alcun
     * cambiamento nel comportamento pubblico osservabile oggi.
     *
     *   is_active=false                    → mai pubblico;
     *   is_active=true, publish_at=null     → legacy, pubblico subito;
     *   is_active=true, publish_at<=now()   → pubblico;
     *   is_active=true, publish_at>now()    → programmato, non ancora pubblico.
     *
     * lifecycle_status resta ortogonale: descrive la maturità editoriale
     * (in aggiornamento/completo), mai la raggiungibilità pubblica.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            });
    }

    /**
     * Equivalente a scopePubliclyVisible() per un'istanza già caricata in
     * memoria — stessa policy, stesso ordine di condizioni, mai duplicata
     * altrove. Vedi il docblock di scopePubliclyVisible() per il contratto
     * completo.
     */
    public function isPubliclyVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->publish_at === null || ! $this->publish_at->isFuture();
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isUpdating(): bool
    {
        return $this->lifecycle_status === self::LIFECYCLE_UPDATING;
    }

    public function isComplete(): bool
    {
        return ! $this->isUpdating();
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    /**
     * Normalizza sempre a UTC prima della persistenza — indipendentemente
     * dal fuso orario del Carbon (o stringa) assegnato. Senza questo
     * mutator, il cast 'datetime' automatico di Eloquent formatta il
     * valore così com'è (wall-clock del fuso corrente, es. Europe/Rome
     * +02:00 in DST) SENZA convertirlo prima in UTC, producendo un
     * timestamp salvato silenziosamente sbagliato di alcune ore — lo
     * stesso rischio esplicitamente richiamato dalla specifica
     * (docs/PERCORSI_SCHEDULING_V1_SPEC.md: "storage UTC"). Un futuro form
     * admin può quindi passare un istante in Europe/Rome così com'è.
     */
    public function setPublishAtAttribute(mixed $value): void
    {
        $this->attributes['publish_at'] = $value === null
            ? null
            : $this->fromDateTime(Carbon::parse($value)->utc());
    }

    // ── Fuso orario redazionale ──────────────────────────────────

    public const EDITORIAL_TIMEZONE = 'Europe/Rome';

    /**
     * publish_at (memorizzato in UTC) convertito nel fuso orario della
     * redazione, per la visualizzazione nel form admin — stesso pattern di
     * Article::publishedAtForEditors().
     */
    public function publishAtForEditors(): ?Carbon
    {
        return $this->publish_at?->clone()->timezone(self::EDITORIAL_TIMEZONE);
    }

    /**
     * Etichetta sintetica per il badge admin — riflette SOLO la policy di
     * isPubliclyVisible()/scopePubliclyVisible(), mai lifecycle_status (che
     * resta ortogonale, vedi il docblock di scopePubliclyVisible()).
     */
    public function publicVisibilityLabel(): string
    {
        if (! $this->is_active) {
            return 'Inattivo';
        }

        if ($this->publish_at !== null && $this->publish_at->isFuture()) {
            return 'Programmato';
        }

        return 'Pubblico';
    }
}
