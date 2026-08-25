<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InternalLinking\InternalLinkAuditRow;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\View\View;

/**
 * Missione 42 (secondo batch autonomo KAIRUS, Fase E — Editorial Quality &
 * Readiness): "orphan article detection". InternalLinkAuditService esiste
 * già (Internal Linking V2, batch precedente) e calcola già la definizione
 * corretta di "orfano" — un articolo PUBBLICATO che non riceve alcun
 * collegamento interno da nessun altro articolo del sito
 * (InternalLinkAuditRow::isOrphan()) — distinta da "senza Concept"
 * (Missione 27) e "senza Percorso" (già coperto da contenuti_isolati):
 * un articolo può appartenere a un Concept e a un Percorso e restare
 * comunque irraggiungibile dalla normale navigazione da-articolo-ad-articolo.
 *
 * Era raggiungibile solo via CLI (`content:internal-link-audit`). Questo
 * controller aggiunge solo la superficie admin mancante, riusando
 * integralmente InternalLinkAuditService::audit() — nessuna nuova regola
 * di dominio, nessun ricalcolo.
 *
 * Deliberatamente NON wired nello snapshot di
 * EditorialOperationsDashboardService: l'audit scansiona l'intero corpus
 * (tutti gli stati) e ne analizza il body per costruire il grafo dei link
 * — stesso costo di calcolo per cui la Missione 35 ha tenuto standalone
 * l'Editorial Quality Gate invece di aggiungerlo alla dashboard V1, mai
 * ricalcolato a ogni caricamento del command center.
 */
class InternalLinkAuditController extends Controller
{
    public function __construct(
        private readonly InternalLinkAuditService $auditService,
    ) {}

    public function index(): View
    {
        $report = $this->auditService->audit();

        // Stessa lista "ARTICOLI DA VERIFICARE" già prodotta dal comando CLI
        // (InternalLinkAuditCommand::renderTextReport()): solo le righe con
        // almeno un'anomalia, mai un elenco completo che nasconderebbe il
        // segnale in mezzo al rumore degli articoli già a posto.
        $flagged = collect($report->rows)
            ->filter(fn (InternalLinkAuditRow $row) => $row->hasBrokenOutgoingLinks()
                || $row->countByClassification('self') > 0
                || $row->countByClassification('unpublished') > 0
                || $row->hasAmbiguousAnchor
                || $row->isOrphan())
            ->values();

        return view('admin.internal-link-audit.index', [
            'report' => $report,
            'flagged' => $flagged,
        ]);
    }
}
