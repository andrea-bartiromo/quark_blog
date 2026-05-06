<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterClick extends Model
{
    protected $fillable = [
        'newsletter_subscriber_id',
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

    public function subscriber()
    {
        return $this->belongsTo(
            NewsletterSubscriber::class,
            'newsletter_subscriber_id'
        );
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}