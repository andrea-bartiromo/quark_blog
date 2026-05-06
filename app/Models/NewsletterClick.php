<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterClick extends Model
{
    protected $table = 'newsletter_clicks';

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

   public function article()
{
    return $this->belongsTo(\App\Models\Article::class);
}
}