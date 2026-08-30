<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Measurement Closeout (Missione 2) — riga del log append-only degli eventi
 * editoriali di continuità sotto contratto canonico
 * (App\Services\Telemetry\EditorialEventContract).
 *
 * Append-only per costruzione: nessun timestamp automatico (l'istante
 * dell'evento è occurred_at, deciso dal producer, non dall'ORM) e nessun
 * updated_at — un evento non viene mai modificato dopo la creazione.
 *
 * Nessun accessor "de-pseudonimizzante": session_key resta un digest e non
 * esiste alcun metodo qui che provi a risalire alla sessione. Nessuna
 * relazione verso User: gli eventi descrivono traffico pubblico anonimo, e
 * il traffico interno redazionale non viene proprio registrato (vedi
 * EditorialContinuityRecorder).
 */
class EditorialContinuityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_name',
        'schema_version',
        'session_key',
        'article_id',
        'target_article_id',
        'content_cluster_id',
        'transition_type',
        'source_channel',
        'context_position',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'schema_version' => 'integer',
            'context_position' => 'integer',
        ];
    }

    public function article()
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function targetArticle()
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }

    public function contentCluster()
    {
        return $this->belongsTo(ContentCluster::class, 'content_cluster_id');
    }
}
