<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Newsletter extends Model
{
    protected $table    = 'newsletter';
    protected $fillable = ['email', 'confirmed', 'token'];
    protected $casts    = ['confirmed' => 'boolean'];

    public static function subscribe(string $email): static
    {
        return static::updateOrCreate(
            ['email' => $email],
            ['confirmed' => false, 'token' => Str::random(64)]
        );
    }
}
