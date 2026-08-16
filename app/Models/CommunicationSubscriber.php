<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationSubscriber extends Model
{
    use HasFactory;

    protected $table = 'comm_subscribers';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_COMPLAINED = 'complained';

    protected $fillable = [
        'email', 'status', 'token', 'unsubscribe_token', 'source',
        'confirmed_at', 'unsubscribed_at', 'last_bounced_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
        'last_bounced_at' => 'datetime',
    ];

    // ── Relazioni ─────────────────────────────────────────────

    public function sends(): HasMany
    {
        return $this->hasMany(CommunicationSend::class, 'subscriber_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CommunicationDelivery::class, 'subscriber_id');
    }

    public function pathSubscriptions(): HasMany
    {
        return $this->hasMany(ContentClusterSubscriber::class, 'subscriber_id');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeConfirmed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Unica definizione di "può ricevere un invio adesso" — usata da
     * CommunicationDeliveryService::attemptSend() sia prima di creare un
     * claim sia di nuovo subito prima dell'invio reale (il consenso può
     * essere revocato tra le due cose). Solo 'confirmed' è eleggibile:
     * pending (mai confermato), unsubscribed, bounced, complained non lo
     * sono mai, indipendentemente dal motivo.
     */
    public function isEligibleForDelivery(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'In attesa di conferma',
            self::STATUS_CONFIRMED => 'Confermato',
            self::STATUS_UNSUBSCRIBED => 'Disiscritto',
            self::STATUS_BOUNCED => 'Rimbalzato',
            self::STATUS_COMPLAINED => 'Segnalato come spam',
        ];
    }
}
