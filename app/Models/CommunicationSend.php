<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommunicationSend extends Model
{
    use HasFactory;

    protected $table = 'comm_sends';

    public const STATUS_QUEUED = 'queued';

    /**
     * Claim in corso — transizione atomica queued->sending guardata da
     * WHERE status='queued' (stesso pattern già collaudato in
     * CommunicationDeliveryService::attemptSend()): la garanzia che due
     * worker concorrenti sulla STESSA riga non possano mai processarla
     * entrambi. Nessuna migration necessaria: `status` è una colonna
     * string semplice, senza vincolo CHECK — questo valore era già
     * rappresentabile dallo schema originale.
     */
    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_FAILED = 'failed';

    /**
     * Campagna annullata mentre questa riga era ancora 'queued' (mai
     * 'sending' — vedi CampaignDeliveryOrchestrator, la cancellazione
     * mid-flight non interrompe un claim già in corso, lo lascia
     * concludere). Distinto da 'failed': qui non è mai stato tentato
     * alcun invio, reale o fake.
     */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid', 'campaign_id', 'subscriber_id', 'status',
        'provider_message_id', 'queued_at', 'sent_at', 'failed_at',
        'failure_reason', 'attempts',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommunicationSend $send) {
            if (blank($send->uuid)) {
                $send->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CommunicationSubscriber::class, 'subscriber_id');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function statusOptions(): array
    {
        return [
            self::STATUS_QUEUED => 'In coda',
            self::STATUS_SENDING => 'Invio in corso',
            self::STATUS_SENT => 'Inviata',
            self::STATUS_DELIVERED => 'Consegnata',
            self::STATUS_BOUNCED => 'Rimbalzata',
            self::STATUS_FAILED => 'Fallita',
            self::STATUS_CANCELLED => 'Annullata',
        ];
    }
}
