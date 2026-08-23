<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot immutabile dei campi editoriali di un Article, scritto da
 * ArticleRevisionService immediatamente prima di applicare un salvataggio
 * esplicito riuscito. Mai scritto dall'autosave locale (vedi
 * partials/article-autosave-script.blade.php, un meccanismo interamente
 * diverso e non correlato).
 */
class ArticleRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'article_id', 'user_id', 'title', 'excerpt', 'body', 'category', 'status', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
