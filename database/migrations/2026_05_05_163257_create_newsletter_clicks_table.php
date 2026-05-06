<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterClick extends Model
{
    protected $fillable = [
        'newsletter_id',
        'article_id',
        'email',
        'ip_hash',
        'user_agent',
        'url',
        'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];
}