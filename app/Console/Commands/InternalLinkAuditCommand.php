<?php

namespace App\Console\Commands;

use App\Services\InternalLinking\InternalLinkAuditReport;
use App\Services\InternalLinking\InternalLinkAuditRow;
use App\Services\InternalLinking\InternalLinkAuditService;
use Illuminate\Console\Command;

class InternalLinkAuditCommand extends Command
{
    protected $signature = 'content:internal-link-audit
        {--article= : Limita l\'audit a un singolo articolo (ID)}
        {--status= : Limita l\'audit agli articoli con questo stato (draft, review, scheduled, published)}
        {--json : Restituisce il risultato in formato JSON invece del report testuale}';

    protected $description = 'Fotografa lo stato dei collegamenti interni tra articoli (rotti, isolati, opportunità), senza modificare nulla';

    protected $help = <<<'HELP'
        Obiettivo
        ---------
        Internal Linking V2 — audit di collegamento READ-ONLY: nessuna
        scrittura su database, nessun link inserito o rimosso — sicuro da
        eseguire in qualunque momento, anche in produzione.

        Analizza i link /articolo/{slug} realmente presenti nel body di
        ogni articolo (stessa definizione già usata dal badge Admin e dal
        suggeritore, vedi ArticleLinkInsertionService), classificandoli:
          - valid: il target esiste ed è pubblicato;
          - scheduled_safe (V2.1): il target non è ancora pubblico, ma la
            sorgente è essa stessa programmata e il target diventerà
            pubblico PRIMA di lei (vedi InternalLinkTemporalEligibility) —
            non un'anomalia, solo non ancora raggiungibile in questo
            momento;
          - unpublished: il target esiste ma non è pubblico né
            temporalmente sicuro (bozza/revisione/programmato non ancora
            garantito) — un link pubblico non dovrebbe puntarci;
          - redirected: il target non esiste più con questo slug, ma uno
            storico ArticleSlugRedirect lo risolve (funziona, o sarà
            temporalmente sicuro come sopra — in entrambi i casi andrebbe
            comunque aggiornato allo slug corrente);
          - missing: nessun articolo né redirect risolve lo slug — link
            rotto;
          - self: l'articolo collega se stesso.

        Un articolo "isolato" è un articolo PUBBLICATO che non riceve
        alcun collegamento interno da nessun altro articolo del sito
        (incoming links = 0) — calcolato su TUTTO il corpus anche quando
        --article=/--status= limita quali righe vengono mostrate.

        Opzioni
        -------
        --article=   Limita l'audit a un singolo articolo (ID)
        --status=    Limita l'audit agli articoli con questo stato
        --json       Output JSON invece del report testuale
        HELP;

    public function handle(InternalLinkAuditService $auditService): int
    {
        $articleId = $this->option('article') !== null ? (int) $this->option('article') : null;
        $status = $this->option('status');

        $report = $auditService->audit($articleId, $status);

        if ($this->option('json')) {
            $this->line((string) json_encode($this->toJson($report), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->renderTextReport($report);

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function toJson(InternalLinkAuditReport $report): array
    {
        return [
            'summary' => [
                'analyzed' => $report->analyzed,
                'without_links' => $report->withoutOutgoingLinks,
                'with_one_link' => $report->withOneOutgoingLink,
                'with_two_or_more_links' => $report->withTwoOrMoreOutgoingLinks,
                'broken_links' => $report->brokenLinks,
                'self_links' => $report->selfLinks,
                'unpublished_targets' => $report->unpublishedTargets,
                'scheduled_safe_links' => $report->scheduledSafeLinks,
                'redirected_links' => $report->redirectedLinks,
                'articles_with_ambiguous_anchors' => $report->articlesWithAmbiguousAnchors,
                'isolated_articles' => $report->isolatedArticles,
            ],
            'articles' => array_map(fn (InternalLinkAuditRow $row) => [
                'id' => $row->articleId,
                'title' => $row->title,
                'slug' => $row->slug,
                'status' => $row->status,
                'outgoing_links_count' => $row->outgoingDistinctCount,
                'incoming_links_count' => $row->incomingLinksCount,
                'is_orphan' => $row->isOrphan(),
                'has_ambiguous_anchor' => $row->hasAmbiguousAnchor,
                'links' => $row->outgoingLinks,
            ], $report->rows),
            'top_opportunities' => [
                'published_without_incoming_links' => $report->publishedWithoutIncomingLinks,
                'scheduled_without_internal_links' => $report->scheduledWithoutInternalLinks,
                'high_confidence_unused_suggestions' => $report->highConfidenceUnusedSuggestions,
            ],
        ];
    }

    private function renderTextReport(InternalLinkAuditReport $report): void
    {
        $this->newLine();
        $this->line('<fg=cyan;options=bold>INTERNAL LINK AUDIT — KAIRUS</>');
        $this->line('(sola lettura — nessuna modifica applicata)');
        $this->newLine();

        $this->line('<fg=cyan;options=bold>ARTICOLI</>');
        $this->line("  Analizzati: {$report->analyzed}");
        $this->line("  Senza link interni: {$report->withoutOutgoingLinks}");
        $this->line("  Con 1 link: {$report->withOneOutgoingLink}");
        $this->line("  Con 2+ link: {$report->withTwoOrMoreOutgoingLinks}");
        $this->line("  Link rotti: {$report->brokenLinks}");
        $this->line("  Self-link: {$report->selfLinks}");
        $this->line("  Target non pubblicato: {$report->unpublishedTargets}");
        $this->line("  Target scheduled temporalmente sicuro (V2.1): {$report->scheduledSafeLinks}");
        $this->line("  Link reindirizzati (redirect storico): {$report->redirectedLinks}");
        $this->line("  Articoli con anchor ambigui: {$report->articlesWithAmbiguousAnchors}");
        $this->line("  Articoli isolati (pubblicati, zero incoming): {$report->isolatedArticles}");
        $this->newLine();

        $this->line('<fg=cyan;options=bold>TOP OPPORTUNITÀ</>');

        if ($report->publishedWithoutIncomingLinks === []) {
            $this->line('  Pubblicati senza incoming links: nessuno');
        } else {
            $this->line('  Pubblicati senza incoming links:');
            foreach ($report->publishedWithoutIncomingLinks as $a) {
                $this->line("    #{$a['id']} {$a['title']} ({$a['slug']})");
            }
        }

        if ($report->scheduledWithoutInternalLinks === []) {
            $this->line('  Programmati senza internal links: nessuno');
        } else {
            $this->line('  Programmati senza internal links:');
            foreach ($report->scheduledWithoutInternalLinks as $a) {
                $this->line("    #{$a['id']} {$a['title']} ({$a['slug']})");
            }
        }

        if ($report->highConfidenceUnusedSuggestions === []) {
            $this->line('  Anchor candidati ad alta confidenza non utilizzati: nessuno');
        } else {
            $this->line('  Anchor candidati ad alta confidenza non utilizzati:');
            foreach ($report->highConfidenceUnusedSuggestions as $s) {
                $this->line("    #{$s['source']['id']} {$s['source']['title']} → #{$s['target']['id']} {$s['target']['title']} (\"{$s['anchor_text']}\", {$s['confidence_score']}/100)");
            }
        }

        if ($report->rows !== []) {
            $this->newLine();
            $this->line('<fg=cyan;options=bold>ARTICOLI DA VERIFICARE</>');

            $flagged = array_filter(
                $report->rows,
                fn (InternalLinkAuditRow $r) => $r->hasBrokenOutgoingLinks() || $r->countByClassification('self') > 0
                    || $r->countByClassification('unpublished') > 0 || $r->hasAmbiguousAnchor || $r->isOrphan()
            );

            if ($flagged === []) {
                $this->line('  Nessuno — tutti gli articoli analizzati sono senza anomalie rilevate.');
            }

            foreach ($flagged as $row) {
                $reasons = [];

                if ($row->countByClassification('missing') > 0) {
                    $reasons[] = $row->countByClassification('missing').' link rotti';
                }

                if ($row->countByClassification('self') > 0) {
                    $reasons[] = 'self-link';
                }

                if ($row->countByClassification('unpublished') > 0) {
                    $reasons[] = $row->countByClassification('unpublished').' target non pubblicati';
                }

                if ($row->hasAmbiguousAnchor) {
                    $reasons[] = 'anchor ambigui';
                }

                if ($row->isOrphan()) {
                    $reasons[] = 'isolato';
                }

                $this->line("  #{$row->articleId} {$row->title}: ".implode(', ', $reasons));
            }
        }

        $this->newLine();
    }
}
