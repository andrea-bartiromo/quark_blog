<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cronologia append-only del consenso newsletter.
 *
 * Gli eventi non contengono mai l’indirizzo email in chiaro e non vengono
 * rimossi quando un iscritto pending viene eliminato.
 */
class NewsletterConsentEvent extends Model
{
    public const INITIAL_CONFIRMATION_SENT = 'initial_confirmation_sent';
    public const REMINDER_SENT = 'reminder_sent';
    public const CONFIRMED = 'confirmed';
    public const DELETED_UNCONFIRMED = 'deleted_unconfirmed';
    public const SEND_FAILED = 'send_failed';
    public const PROCESS_SKIPPED = 'process_skipped';

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    protected $fillable = [
        'newsletter_id',
        'email_hash',
        'event_type',
        'attempt_number',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
