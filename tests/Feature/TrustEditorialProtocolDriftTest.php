<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TrustKnowledgeStatementController;
use App\Models\TrustKnowledgeStatement;
use App\Services\Trust\TrustPilotGateReadinessService;
use App\Services\Trust\TrustPilotPreviewMetricsService;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Admin\TrustPilotPreviewMetricsControllerTest;
use Tests\TestCase;

/**
 * Cantiere 45 (programma "100 cantieri Kairus", documentazione pura —
 * si legga l'addendum Cantiere 45 in
 * docs/TRUST_LAYER_COSA_SAPPIAMO_DAVVERO_PILOT.md).
 *
 * docs/TRUST_LAYER_EDITORIAL_PROTOCOL.md consolida in un protocollo
 * operativo quanto già costruito nei Cantieri 38-43: un documento che
 * nessuno tiene sincronizzato con il codice reale è peggio di nessun
 * documento. Questo test verifica che ogni route e classe che il
 * protocollo cita esista davvero, e che il documento continui a
 * riaffermare esplicitamente che il gate B-45 resta NO-GO e che il passo
 * 6 (Cantiere 44) non è ancora costruito — stesso principio già in uso
 * in ReleaseChecklistDriftTest per deploy.sh/release-checklist.json.
 */
class TrustEditorialProtocolDriftTest extends TestCase
{
    public function test_every_route_the_protocol_cites_actually_resolves(): void
    {
        $protocol = $this->protocol();

        foreach ([
            'admin.trust-knowledge.create',
            'admin.trust-knowledge.store',
            'admin.trust-knowledge.edit',
            'admin.trust-knowledge.preview',
            'admin.trust-knowledge.gate-readiness',
        ] as $routeName) {
            $this->assertStringContainsString(
                $routeName,
                $protocol,
                "Il protocollo dovrebbe citare la route '{$routeName}'."
            );
            $this->assertTrue(
                Route::has($routeName),
                "La route '{$routeName}' citata dal protocollo non è registrata."
            );
        }
    }

    public function test_every_class_the_protocol_cites_actually_exists(): void
    {
        $protocol = $this->protocol();

        foreach ([
            TrustKnowledgeStatement::class,
            TrustKnowledgeStatementController::class,
            TrustPilotPreviewMetricsService::class,
            TrustPilotGateReadinessService::class,
        ] as $class) {
            $this->assertStringContainsString(
                class_basename($class),
                $protocol,
                "Il protocollo dovrebbe citare la classe '{$class}'."
            );
            $this->assertTrue(
                class_exists($class),
                "La classe '{$class}' citata dal protocollo non esiste."
            );
        }
    }

    /**
     * Codex (PR #603): il protocollo cita esplicitamente questo test come
     * prova strutturale della garanzia privacy-first al passo 3 — se
     * rinominato o rimosso, il documento punterebbe a una verifica
     * inesistente senza che nessuna suite se ne accorga.
     */
    public function test_the_specific_test_method_the_protocol_cites_as_evidence_still_exists(): void
    {
        $protocol = $this->protocol();
        $class = TrustPilotPreviewMetricsControllerTest::class;
        $method = 'test_the_preview_views_table_has_no_visitor_identifying_column';

        $this->assertStringContainsString(
            class_basename($class).'::'.$method,
            $protocol,
            "Il protocollo dovrebbe citare '".class_basename($class)."::{$method}'."
        );
        $this->assertTrue(class_exists($class), "La classe '{$class}' citata dal protocollo non esiste.");
        $this->assertTrue(
            method_exists($class, $method),
            "Il metodo '{$method}' citato dal protocollo non esiste più su {$class}."
        );
    }

    public function test_every_file_the_protocol_cites_actually_exists_on_disk(): void
    {
        $protocol = $this->protocol();

        foreach ([
            'resources/views/components/trust-knowledge-summary.blade.php',
            'resources/views/components/article/primary-sources.blade.php',
        ] as $relativePath) {
            $this->assertStringContainsString(
                $relativePath,
                $protocol,
                "Il protocollo dovrebbe citare il percorso '{$relativePath}'."
            );
            $this->assertFileExists(
                base_path($relativePath),
                "Il file '{$relativePath}' citato dal protocollo non esiste."
            );
        }
    }

    /**
     * Tripwire: il protocollo non deve mai lasciar intendere che il gate
     * B-45 sia superato, né che il Cantiere 44 (decisione GO/NO-GO) sia
     * già stato costruito — entrambi richiedono una decisione umana
     * esplicita ancora non presa.
     */
    public function test_the_protocol_still_states_the_gate_is_not_go_and_step_six_is_unbuilt(): void
    {
        $protocol = $this->protocol();

        $this->assertStringContainsString('NO-GO', $protocol);
        $this->assertStringContainsString('non esiste ancora nel', $protocol);

        // Il passo 6 (Cantiere 44) non deve avere una route reale citata
        // come se esistesse già — a differenza dei passi 1-5.
        $this->assertStringNotContainsString('admin.trust-knowledge.decision', $protocol);
        $this->assertFalse(
            Route::has('admin.trust-knowledge.decision'),
            'Se questa route esiste, il Cantiere 44 è stato costruito e questo protocollo va aggiornato di conseguenza (con la sua stessa escalation).'
        );
    }

    private function protocol(): string
    {
        $content = file_get_contents(base_path('docs/TRUST_LAYER_EDITORIAL_PROTOCOL.md'));
        $this->assertIsString($content);

        return $content;
    }
}
