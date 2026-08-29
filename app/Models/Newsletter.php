<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    protected $table = 'newsletter';

    public const SOURCES = ['popup', 'homepage', 'article'];

    protected $fillable = ['email', 'confirmed', 'token', 'unsubscribe_token', 'source'];

    protected $casts = ['confirmed' => 'boolean'];

    public static function subscribe(string $email, ?string $source = null): static
    {
        $subscriber = static::firstOrNew(['email' => $email]);
        $subscriber->confirmed = false;
        $subscriber->token = Str::random(64);
        $subscriber->unsubscribe_token = Str::random(32);

        if (! $subscriber->exists) {
            $subscriber->source = in_array($source, self::SOURCES, true) ? $source : null;
        }

        $subscriber->save();

        return $subscriber;
    }
}
