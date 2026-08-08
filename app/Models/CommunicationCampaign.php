<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunicationCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comm_campaigns';

    public const TYPE_NEWSLETTER = 'newsletter';

    public const TYPE_COMUNICATO = 'comunicato';

    public const TYPE_AGGIORNAMENTO = 'aggiornamento';

    public const TYPE_SPECIALE = 'speciale';

    public const TYPE_NOTIFICA = 'notifica';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'type', 'status', 'title', 'subject', 'preheader', 'description', 'content', 'internal_notes',
        'template_id', 'template_version_id', 'sender_profile_id',
        'scheduled_at', 'sending_started_at', 'completed_at',
        'project_id', 'series_id', 'idempotency_key',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'scheduled_at' => 'datetime',
        'sending_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommunicationCampaign $campaign) {
            if (blank($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function sends(): HasMany
    {
        return $this->hasMany(CommunicationSend::class, 'campaign_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(CommunicationCampaignActivityLog::class, 'campaign_id');
    }

    /**
     * Iscritti raggiunti da questa campagna, attraverso i record di invio
     * (comm_sends) — utile per liste/riepiloghi senza dover passare
     * esplicitamente da sends() in ogni punto che ne ha bisogno.
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(CommunicationSubscriber::class, 'comm_sends', 'campaign_id', 'subscriber_id')
            ->withPivot(['status', 'sent_at'])
            ->withTimestamps();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(CommunicationSenderProfile::class, 'sender_profile_id');
    }

    /**
     * La versione esatta usata al momento dell'associazione — non la
     * versione attiva corrente del template, che può essere cambiata da
     * allora. Questo è ciò che garantisce che una campagna non segua
     * automaticamente le modifiche future al template (Blueprint Sezione 7).
     */
    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateVersion::class, 'template_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function scopeType(Builder $q, string $type): Builder
    {
        return $q->where('type', $type);
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function typeOptions(): array
    {
        return [
            self::TYPE_NEWSLETTER => 'Newsletter',
            self::TYPE_COMUNICATO => 'Comunicato',
            self::TYPE_AGGIORNAMENTO => 'Aggiornamento',
            self::TYPE_SPECIALE => 'Speciale',
            self::TYPE_NOTIFICA => 'Notifica',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Bozza',
            self::STATUS_SCHEDULED => 'Programmata',
            self::STATUS_SENDING => 'Invio in corso',
            self::STATUS_COMPLETED => 'Completata',
            self::STATUS_FAILED => 'Fallita',
            self::STATUS_CANCELLED => 'Annullata',
        ];
    }
}
