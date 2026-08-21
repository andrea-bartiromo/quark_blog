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
        static::created(fn (Article $article) => app(ProjectEditorialLinkService::class)->linkToDefaultProject($article));

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

            ArticleSlugRedirect::where('old_slug', $article->slug)->delete();
        });

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

    public static array $verificationLabels = [
        'unverified' => 'Non verificato',
        'in_progress' => 'In verifica',
        'verified' => 'Verificato',
        'needs_update' => 'Aggiornamento necessario',
    ];

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

    /**
     * Categorie editoriali aggiuntive. `category` resta la categoria
     * principale e la fonte di verita per badge, breadcrumb e compatibilita
     * con il codice storico; questa relazione contiene solo le secondarie.
     */
    public function secondaryCategories()
    {
        return $this->belongsToMany(Category::class, 'article_category')
            ->withTimestamps();
    }

    public function linkSuggestions()
    {
        return $this->hasMany(ArticleLinkSuggestion::class, 'source_article_id');
    }

    public function proposedLinkSuggestions()
    {
        $temporalEligibility = app(InternalLinkTemporalEligibility::class);

        return $this->linkSuggestions()
            ->proposed()
            ->with('targetArticle:id,title,slug,status,published_at')
            ->orderByDesc('confidence_score')
            ->get()
            ->filter(fn (ArticleLinkSuggestion $s) => $s->targetArticle !== null && $temporalEligibility->isTargetSafeForSource($this, $s->targetArticle))
            ->take(ArticleLinkSuggestion::MAX_PROPOSED_RESULTS)
            ->values();
    }

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

    public function scopeScheduledSafeAsLinkTargetFor(Builder $q, self $source): Builder
    {
        return $q->where('status', self::STATUS_SCHEDULED)
            ->whereNotNull('published_at')
            ->where('published_at', '<', $source->published_at);
    }

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

    public function isScheduleOverdue(): bool
    {
        return $this->isScheduled() && $this->published_at !== null && $this->published_at->isPast();
    }

    public function publishedAtForEditors(): ?Carbon
    {
        return $this->published_at?->clone()->timezone(self::EDITORIAL_TIMEZONE);
    }

    public static function scheduledAtFromEditorialInput(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", self::EDITORIAL_TIMEZONE)->utc();
    }

    public function getReadTimeAttribute(): string
    {
        return $this->read_minutes.' min di lettura';
    }

    public static function calculateReadMinutes(?string $body): int
    {
        $text = html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $tokens = preg_split('/[\s\x{00A0}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $wordCount = count($tokens);

        return max(1, (int) round($wordCount / 200));
    }

    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

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
        return static::published()
            ->byCategory($this->category)
            ->where('id', '!=', $this->id)
            ->limit($limit)
            ->get();
    }

    public static function robotsOptions(): array
    {
        return ['index,follow', 'noindex,follow', 'index,nofollow', 'noindex,nofollow'];
    }

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

    public function metaOgImage(): ?string
    {
        return filled($this->og_image) ? $this->og_image : $this->cover_image;
    }

    public function metaTwitterTitle(): string
    {
        return filled($this->twitter_title) ? $this->twitter_title : $this->metaTitle();
    }

    public function metaTwitterDescription(): string
    {
        return filled($this->twitter_description) ? $this->twitter_description : $this->metaDescription();
    }

    public function metaTwitterImage(): ?string
    {
        return filled($this->twitter_image) ? $this->twitter_image : $this->metaOgImage();
    }
}
