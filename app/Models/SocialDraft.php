<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workspace Social Admin V1 — ledger editoriale interno di bozze Social
 * (draft → reviewed → approved → scheduled). Distinto e mai collegato a
 * SocialPublication (ledger di delivery/provider, invariato da questa
 * missione). Nessuna chiamata esterna, nessun invio: questo modello
 * rappresenta solo l'intenzione editoriale interna, mai un tentativo di
 * pubblicazione.
 */
class SocialDraft extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SCHEDULED = 'scheduled';

    /**
     * Stati puramente informativi per una futura fase provider (mai scritti
     * da questa V1, mai raggiungibili dalla UI): presenti qui solo perché
     * possano essere letti/mostrati in audit senza un cast/enum che li
     * rifiuti, mai perché questa V1 li produca.
     */
    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const CHANNEL_FACEBOOK = 'facebook';

    public const CHANNEL_LINKEDIN = 'linkedin';

    /**
     * @var array<string, string> canale => etichetta leggibile
     */
    public const CHANNELS = [
        self::CHANNEL_FACEBOOK => 'Facebook',
        self::CHANNEL_LINKEDIN => 'LinkedIn',
    ];

    protected $fillable = [
        'article_id',
        'channel',
        'status',
        'copy',
        'destination_url',
        'use_utm',
        'utm_campaign',
        'scheduled_at',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'use_utm' => 'boolean',
        'scheduled_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
