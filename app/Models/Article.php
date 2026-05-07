<?php
/**
 * Il Laboratorio — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 * @link      https://www.illaboratorio.it
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'user_id', 'title', 'slug', 'excerpt', 'body',
        'category', 'cover_image', 'status', 'featured',
        'read_minutes', 'views', 'published_at',
        'verification_status', 'verification_notes',
        'verified_at', 'verified_by', 'primary_sources',
    ];

    protected $casts = [
        'featured'          => 'boolean',
        'published_at'      => 'datetime',
        'verified_at'       => 'datetime',
    ];

    // Etichette leggibili per lo stato di verifica
    public static array $verificationLabels = [
        'unverified'    => 'Non verificato',
        'in_progress'   => 'In verifica',
        'verified'      => 'Verificato',
        'needs_update'  => 'Aggiornamento necessario',
    ];

    // Colori badge per lo stato di verifica
    public static array $verificationColors = [
        'unverified'    => '#ef4444',
        'in_progress'   => '#f59e0b',
        'verified'      => '#22c55e',
        'needs_update'  => '#6366f1',
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
        return $q->where('status', 'published')->orderByDesc('published_at');
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
        return $this->read_minutes . ' min di lettura';
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
}
