<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPublication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_RETRYABLE = 'retryable';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'article_id',
        'channel',
        'event_key',
        'status',
        'attempt_count',
        'remote_id',
        'remote_url',
        'last_error_class',
        'last_error_message',
        'last_attempted_at',
        'succeeded_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'last_attempted_at' => 'datetime',
        'succeeded_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
