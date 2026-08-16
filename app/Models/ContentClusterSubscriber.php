<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Iscrizione di un subscriber a un singolo Percorso ("Avvisami quando
 * continua") — vedi il docblock della migration per il ragionamento
 * completo su perché è una tabella distinta da comm_sends/
 * communication_deliveries e perché usa un proprio unsubscribe_token.
 */
class ContentClusterSubscriber extends Model
{
    use HasFactory;

    protected $table = 'content_cluster_subscribers';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'subscriber_id', 'content_cluster_id', 'status',
        'unsubscribe_token', 'unsubscribed_at',
    ];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(CommunicationSubscriber::class, 'subscriber_id');
    }

    public function contentCluster(): BelongsTo
    {
        return $this->belongsTo(ContentCluster::class, 'content_cluster_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
