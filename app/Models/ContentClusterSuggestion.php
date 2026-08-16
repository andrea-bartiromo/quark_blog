<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ContentClusterSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'article_id',
        'content_cluster_id',
        'status',
        'confidence',
        'reasons',
        'evidence_hash',
        'suggested_primary',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'confidence' => 'integer',
        'suggested_primary' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    protected function reasons(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? [] : json_decode($value, true, 512, JSON_THROW_ON_ERROR),
            set: fn ($value) => is_string($value) ? $value : json_encode($value ?? [], JSON_THROW_ON_ERROR),
        );
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function contentCluster()
    {
        return $this->belongsTo(ContentCluster::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
