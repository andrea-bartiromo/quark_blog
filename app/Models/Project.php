<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    public const TYPE_EDITORIAL_SPECIAL = 'editorial_special';

    public const TYPE_ARTICLE_SERIES = 'article_series';

    public const TYPE_AUDIT = 'audit';

    public const TYPE_TECHNICAL_IMPROVEMENT = 'technical_improvement';

    public const TYPE_OTHER = 'other';

    public const STATUS_IDEA = 'idea';

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    /**
     * Tipi di progetto ammessi come "progetto editoriale predefinito"
     * (Blocco F): il collegamento automatico degli articoli si applica
     * solo a questi, mai a progetti tecnici o di altra natura, anche se
     * qualcuno impostasse per errore il flag su un progetto diverso.
     */
    public const DEFAULT_EDITORIAL_ELIGIBLE_TYPES = [
        self::TYPE_EDITORIAL_SPECIAL,
        self::TYPE_ARTICLE_SERIES,
    ];

    protected $fillable = [
        'title', 'slug', 'description', 'objective', 'type',
        'operational_status', 'priority', 'responsible_id',
        'start_date', 'due_date', 'next_action', 'progress',
        'internal_notes', 'archived_at', 'is_default_editorial',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'archived_at' => 'datetime',
        'progress' => 'integer',
        'is_default_editorial' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Project $project) {
            if (blank($project->slug)) {
                $project->slug = static::uniqueSlugFor($project->title, $project->id);
            }

            // Rete di sicurezza a livello di modello: la validazione "vera"
            // vive nel FormRequest (Blocco E), ma un uso diretto di Eloquent
            // (seeder, tinker, codice futuro) non deve mai poter marcare come
            // predefinito un progetto tecnico o di altra natura.
            if ($project->is_default_editorial && ! $project->isEditorialType()) {
                $project->is_default_editorial = false;
            }
        });

        static::saved(function (Project $project) {
            // Al più un progetto alla volta è il predefinito: impostarne uno
            // nuovo spegne automaticamente quello precedente. Usa una query
            // di massa (non Eloquent::save()) per non far scattare di nuovo
            // questi stessi eventi sugli altri progetti.
            if ($project->is_default_editorial) {
                static::query()
                    ->where('id', '!=', $project->id)
                    ->where('is_default_editorial', true)
                    ->update(['is_default_editorial' => false]);
            }
        });
    }

    public function isEditorialType(): bool
    {
        return in_array($this->type, self::DEFAULT_EDITORIAL_ELIGIBLE_TYPES, true);
    }

    /**
     * Genera uno slug univoco appendendo un suffisso numerico in caso di
     * collisione (es. "speciale-enigma" -> "speciale-enigma-2"), invece di
     * lasciare che il vincolo UNIQUE del DB sollevi un errore 500 grezzo
     * alla prima collisione di titolo — scenario tutt'altro che raro
     * (es. un progetto ricorrente ogni anno con lo stesso nome).
     */
    public static function uniqueSlugFor(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn (Builder $q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function prompts(): HasMany
    {
        return $this->hasMany(ProjectPrompt::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ProjectDecision::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ProjectActivityLog::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'project_article')->withTimestamps();
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNotIn('operational_status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function scopeBlocked(Builder $q): Builder
    {
        return $q->where('operational_status', self::STATUS_BLOCKED);
    }

    public function scopeHighPriority(Builder $q): Builder
    {
        return $q->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    public function scopeDefaultEditorial(Builder $q): Builder
    {
        return $q->where('is_default_editorial', true);
    }

    /**
     * Il progetto editoriale predefinito attivo (Blocco F): se esiste, i
     * nuovi articoli vi si collegano automaticamente. Nessun collegamento
     * se non ce n'è uno impostato, o se il progetto è ormai completato o
     * annullato — il flag da solo non "riattiva" un progetto chiuso.
     */
    public static function defaultEditorial(): ?self
    {
        return static::query()->defaultEditorial()->active()->first();
    }

    /**
     * Ordina per severità reale (critical > high > medium > low), non
     * alfabeticamente: 'priority' è una stringa, quindi orderBy('priority')
     * metterebbe "medium" prima di "high" e "critical" per ultimo.
     */
    public function scopeOrderByPrioritySeverity(Builder $q, string $direction = 'desc'): Builder
    {
        $order = [self::PRIORITY_CRITICAL, self::PRIORITY_HIGH, self::PRIORITY_MEDIUM, self::PRIORITY_LOW];
        $order = $direction === 'asc' ? array_reverse($order) : $order;

        $cases = collect($order)
            ->map(fn (string $value, int $index) => "WHEN '{$value}' THEN {$index}")
            ->implode(' ');

        return $q->orderByRaw("CASE priority {$cases} ELSE 99 END");
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function typeOptions(): array
    {
        return [
            self::TYPE_EDITORIAL_SPECIAL => 'Speciale editoriale',
            self::TYPE_ARTICLE_SERIES => 'Serie di articoli',
            self::TYPE_AUDIT => 'Audit',
            self::TYPE_TECHNICAL_IMPROVEMENT => 'Miglioramento tecnico',
            self::TYPE_OTHER => 'Altro',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_IDEA => 'Idea',
            self::STATUS_PLANNED => 'Pianificato',
            self::STATUS_IN_PROGRESS => 'In lavorazione',
            self::STATUS_BLOCKED => 'Bloccato',
            self::STATUS_SUSPENDED => 'Sospeso',
            self::STATUS_COMPLETED => 'Completato',
            self::STATUS_CANCELLED => 'Annullato',
        ];
    }

    public static function priorityOptions(): array
    {
        return [
            self::PRIORITY_LOW => 'Bassa',
            self::PRIORITY_MEDIUM => 'Media',
            self::PRIORITY_HIGH => 'Alta',
            self::PRIORITY_CRITICAL => 'Critica',
        ];
    }
}
