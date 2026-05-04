<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsSuggestion extends Model
{
    protected $fillable = [
        'source_title',
        'source_url',
        'source_name',
        'source_excerpt',
        'category',
        'generated_title',
        'generated_excerpt',
        'generated_body',
        'status',
        'article_id',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
}
