<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tentativo di riconferma inviato manualmente da un editor a un
 * iscritto pendente — vedi NewsletterReconfirmationService, unico punto
 * da cui una riga qui viene creata o consumata. Ogni riga è un evento
 * (invio, e se avvenuta, conferma), mai sovrascritta: l'audit trail è
 * l'intera tabella, non un singolo record aggiornato in place.
 */
class NewsletterReconfirmation extends Model
{
    protected $fillable = ['newsletter_id', 'token', 'sent_at', 'expires_at', 'confirmed_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function scopeUnconfirmed(Builder $query): Builder
    {
        return $query->whereNull('confirmed_at');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
