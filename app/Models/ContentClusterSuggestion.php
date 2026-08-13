<?php

namespace App\Models;

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
        'reasons' => 'array',
        'suggested_primary' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

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
