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

    public function safeRemoteUrl(): ?string
    {
        if (! is_string($this->remote_url) || filter_var($this->remote_url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($this->remote_url, PHP_URL_SCHEME)) === 'https'
            ? $this->remote_url
            : null;
    }

    public function sanitizedError(): ?string
    {
        if (! is_string($this->last_error_message) || trim($this->last_error_message) === '') {
            return null;
        }

        $message = preg_replace('/(?:access[_-]?token|token|secret|authorization|api[_-]?key)\s*[=:]\s*[^\s,;]+/i', '$1=[REDACTED]', $this->last_error_message);

        return mb_substr((string) $message, 0, 280);
    }
}
