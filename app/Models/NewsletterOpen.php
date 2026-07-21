<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterOpen extends Model
{
    protected $table = 'newsletter_opens';

    protected $fillable = [
        'newsletter_id',
        'email',
        'ip_hash',
        'user_agent',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
    ];
}
