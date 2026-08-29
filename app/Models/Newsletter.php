<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    protected $table = 'newsletter';

    public const SOURCES = ['popup', 'homepage', 'article', 'sidebar'];

    protected $fillable = ['email', 'confirmed', 'token', 'unsubscribe_token', 'source'];

    protected $casts = ['confirmed' => 'boolean'];

    public static function subscribe(string $email, ?string $source = null): static
    {
        $subscriptionState = [
            'confirmed' => false,
            'token' => Str::random(64),
            'unsubscribe_token' => Str::random(32),
        ];
        $subscriber = static::firstOrCreate(
            ['email' => $email],
            $subscriptionState+[
                'source' => in_array($source, self::SOURCES, true) ? $source : null,
            ],
        );

        if (! $subscriber->wasRecentlyCreated) {
            $subscriber->update($subscriptionState);
        }

        return $subscriber;
    }
}
