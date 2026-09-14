<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): modello interno
 * "Cosa sappiamo davvero" — la scomposizione strutturata (Domanda /
 * Consenso / Incertezza / Cosa manca) che il pilot non pubblico
 * docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md (missione B-40) ha
 * definito come template ma non ha mai reso in schema reale — quel
 * documento resta esplicitamente in NO-GO per una route/pagina pubblica
 * (nessuna migration finché una decisione editoriale umana esplicita non
 * arriva, si legga la missione B-45): questo cantiere costruisce SOLO il
 * modello interno che 39-44 estenderanno (campi/validazioni aggiuntive,
 * anteprima non indicizzabile, componente di consenso/incertezza, gate
 * di pubblicazione) — MAI una route pubblica, MAI un URL raggiungibile
 * fuori da `/admin`.
 *
 * Deliberatamente distinto da:
 * - Article::verification_status/primary_sources — quello è un flag
 *   binario per-articolo ("è stato controllato sì/no"), non una
 *   scomposizione di cosa è certo/incerto/mancante;
 *   TrustKnowledgeStatement non sostituisce né duplica quel workflow.
 * - <x-article.primary-sources>/<x-kairus.trust-panel> — presentazione
 *   pubblica di fonti/aggiornamenti già esistenti, mai toccata qui.
 *
 * Collegamento opzionale (mai obbligatorio) a un Concept e/o a un
 * ContentCluster esistente, per dare un contesto di navigazione interna
 * senza inventare una tassonomia parallela.
 */
class TrustKnowledgeStatement extends Model
{
    use HasFactory;

    protected $fillable = [
        'domanda',
        'consenso',
        'incertezza',
        'cosa_manca',
        'last_checked_at',
        'last_checked_by',
        'concept_id',
        'content_cluster_id',
        'created_by',
    ];

    protected $casts = [
        'last_checked_at' => 'date',
    ];

    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }

    public function contentCluster()
    {
        return $this->belongsTo(ContentCluster::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Nessuna nozione di "verificato di recente" inventata qui: un
     * controllo mai dichiarato resta esplicitamente "mai controllato",
     * mai una data indovinata da created_at/updated_at.
     */
    public function hasBeenChecked(): bool
    {
        return $this->last_checked_at !== null;
    }
}
