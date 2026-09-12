<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    protected $table = 'newsletter';

    public const SOURCES = ['popup', 'homepage', 'article', 'sidebar', 'category'];

    protected $fillable = ['email', 'confirmed', 'token', 'unsubscribe_token', 'source'];

    protected $casts = ['confirmed' => 'boolean'];

    public function reconfirmations(): HasMany
    {
        return $this->hasMany(NewsletterReconfirmation::class);
    }

    public function consentEvents(): HasMany
    {
        return $this->hasMany(NewsletterConsentEvent::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('confirmed', false);
    }

    /**
     * L'unico stato canonico di consenso è newsletter.confirmed.
     * Un'iscrizione attiva non viene mai degradata da una nuova richiesta.
     * Una richiesta pending riparte invece dal giorno 0 con un token nuovo.
     */
    public static function subscribe(string $email, ?string $source = null): static
    {
        $subscriber = static::firstOrCreate(
            ['email' => $email],
            [
                'confirmed' => false,
                'token' => Str::random(64),
                'unsubscribe_token' => Str::random(32),
                'source' => in_array($source, self::SOURCES, true) ? $source : null,
            ],
        );

        if (! $subscriber->wasRecentlyCreated && ! $subscriber->confirmed) {
            $subscriber->update([
                'token' => Str::random(64),
                'unsubscribe_token' => $subscriber->unsubscribe_token ?: Str::random(32),
                                'created_at' => now(),
            ]);

            // I vecchi token non possono riaprire una richiesta di consenso
            // appena riavviata dall'utente.
            $subscriber->reconfirmations()->unconfirmed()->update(['expires_at' => now()]);
        }

        return $subscriber->refresh();
    }
}
