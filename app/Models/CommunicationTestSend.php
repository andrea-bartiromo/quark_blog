<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Traccia separata degli invii di test (Provider Abstraction + Safe
 * Test Send) — mai una riga di comm_sends: nessuna transizione di
 * CampaignStateMachine/SendStateMachine passa mai da qui, e questa
 * tabella non contribuisce in alcun modo a "campagna inviata". Vedi
 * CampaignTestSendService per come viene popolata.
 */
class CommunicationTestSend extends Model
{
    use HasFactory;

    protected $table = 'comm_test_sends';

    public $timestamps = false;

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_TRANSIENT_FAILURE = 'transient_failure';

    public const STATUS_PERMANENT_FAILURE = 'permanent_failure';

    /**
     * Il provider ha lanciato un'eccezione non gestita come
     * DeliveryResult (un errore di programmazione, non una decisione del
     * provider sul messaggio) — distinto dagli stati sopra, che
     * corrispondono 1:1 a DeliveryResult::STATUS_*.
     */
    public const STATUS_EXCEPTION = 'exception';

    protected $fillable = [
        'uuid', 'campaign_id', 'subscriber_id', 'sender_profile_id',
        'status', 'provider_message_id', 'failure_reason',
        'triggered_by', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommunicationTestSend $testSend) {
            if (blank($testSend->uuid)) {
                $testSend->uuid = (string) Str::uuid();
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CommunicationCampaign::class, 'campaign_id');
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CommunicationSubscriber::class, 'subscriber_id');
    }

    public function senderProfile(): BelongsTo
    {
        return $this->belongsTo(CommunicationSenderProfile::class, 'sender_profile_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACCEPTED => 'Accettato dal provider',
            self::STATUS_REJECTED => 'Rifiutato dal provider',
            self::STATUS_TRANSIENT_FAILURE => 'Fallito (transitorio)',
            self::STATUS_PERMANENT_FAILURE => 'Fallito (permanente)',
            self::STATUS_EXCEPTION => 'Eccezione imprevista',
        ];
    }
}
