<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger di consegna GENERICO — vedi la migration
 * 2026_08_15_111755_create_communication_deliveries_table per il
 * ragionamento completo su perché è separato da comm_sends (campagne) e
 * su come delivery_key garantisce l'unicità anche quando notifiable_*
 * è null.
 *
 * Stati (minimo indispensabile, vedi anche
 * App\Services\Communication\CommunicationDeliveryService):
 *
 *   pending  → claim creato (riga esiste, unica per delivery_key), nessun
 *              tentativo di invio ancora avvenuto, oppure un fallimento
 *              esplicitamente ri-accodato tramite retryFailed().
 *   sending  → un tentativo è in corso: transizione atomica pending→sending
 *              (guardata da WHERE status='pending'), è questa la garanzia
 *              che due tentativi concorrenti sulla STESSA riga non possano
 *              entrambi procedere all'invio. Una riga bloccata qui oltre il
 *              tempo atteso NON viene mai auto-ritentata da questo layer:
 *              rappresenta onestamente una finestra di esito incerto
 *              (il processo può essere morto dopo che il provider ha
 *              accettato il messaggio ma prima che qui venga registrato
 *              l'esito) — vedi CommunicationDeliveryService::attemptSend().
 *   sent     → terminale: il side effect (es. Mail::send) è tornato senza
 *              eccezioni. Non è una prova di consegna reale da parte del
 *              provider esterno, solo che il tentativo locale non ha
 *              fallito in modo sincrono.
 *   failed   → il side effect ha lanciato un'eccezione PRIMA di qualunque
 *              ambiguità (nessuna finestra "forse è partita comunque") —
 *              ritentabile solo esplicitamente tramite retryFailed(), mai
 *              automaticamente da questo layer.
 */
class CommunicationDelivery extends Model
{
    use HasFactory;

    protected $table = 'communication_deliveries';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'delivery_key', 'channel', 'notification_type', 'subscriber_id',
        'notifiable_type', 'notifiable_id', 'event_key', 'status',
        'claimed_at', 'sent_at', 'failed_at', 'failure_reason', 'attempts',
        'provider_message_id',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Identità di consegna deterministica, sempre non-NULL: usata come
     * UNIQUE su delivery_key. Stessa combinazione di argomenti produce
     * sempre la stessa chiave — è questo che rende registerDelivery()
     * idempotente per costruzione (vedi CommunicationDeliveryService).
     * Delimitatore ASCII Unit Separator (\x1F, mai presente in un valore
     * applicativo reale) invece di un carattere comune come '|', per
     * eliminare qualunque ambiguità teorica di confine tra campi.
     */
    public static function computeDeliveryKey(
        string $channel,
        string $notificationType,
        int $subscriberId,
        ?string $notifiableType,
        ?int $notifiableId,
        ?string $eventKey
    ): string {
        $parts = [
            $channel,
            $notificationType,
            (string) $subscriberId,
            $notifiableType ?? '',
            $notifiableId !== null ? (string) $notifiableId : '',
            $eventKey ?? '',
        ];

        return hash('sha256', implode("\x1F", $parts));
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CommunicationSubscriber::class, 'subscriber_id');
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }
}
