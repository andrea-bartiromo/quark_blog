<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ConceptController;
use App\Http\Controllers\Admin\ConceptQuestionController;
use App\Models\Article;
use App\Models\ArticleConcept;
use App\Models\Category;
use App\Models\Concept;
use App\Models\ConceptAlias;
use App\Models\ConceptQuestion;
use App\Models\ContentCluster;
use App\Services\AnalyticsExclusionService;
use App\Services\ContentGraph\ConceptSuggestionService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Cantiere 91 (programma "100 cantieri Kairus", documentazione pura).
 *
 * docs/ENTITY_CONTRACTS_CONTENT_ENTITIES.md consolida contratti già
 * veri nel codice per Article/Category/ContentCluster/Concept-
 * ConceptQuestion. Questo test verifica che ogni classe/metodo/scope
 * citato esista ancora davvero, e che
 * docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md, corretto nello stesso
 * cantiere per rimuovere lo stato stantio "non è su main", continui a
 * dire il vero: la fondazione Concept/ConceptQuestion è mergiata, ma
 * nessuna route pubblica `/domande/*` esiste — stesso principio già in
 * uso in TrustEditorialProtocolDriftTest per il protocollo Trust.
 */
class EntityContractsDriftTest extends TestCase
{
    public function test_every_class_the_entity_contracts_doc_cites_actually_exists(): void
    {
        $doc = $this->entityContracts();

        foreach ([
            Article::class,
            Category::class,
            ContentCluster::class,
            Concept::class,
            ConceptQuestion::class,
            ArticleConcept::class,
            ConceptAlias::class,
            AnalyticsExclusionService::class,
            ConceptSuggestionService::class,
        ] as $class) {
            $this->assertStringContainsString(
                class_basename($class),
                $doc,
                "Il documento dovrebbe citare la classe '{$class}'."
            );
            $this->assertTrue(class_exists($class), "La classe '{$class}' citata dal documento non esiste.");
        }
    }

    public function test_every_scope_or_method_the_entity_contracts_doc_cites_actually_exists(): void
    {
        $doc = $this->entityContracts();

        $expectations = [
            [Article::class, 'scopePublished'],
            [Category::class, 'scopePubliclyVisible'],
            [Category::class, 'isPubliclyVisible'],
            [Category::class, 'featuredArticleForDisplay'],
            [Category::class, 'featuredArticle'],
            [ContentCluster::class, 'scopePubliclyVisible'],
            [ContentCluster::class, 'isPubliclyVisible'],
            [ContentCluster::class, 'acceptsPathSubscriptions'],
            [ContentCluster::class, 'isUpdating'],
            [ContentCluster::class, 'isComplete'],
            [Concept::class, 'aliases'],
            [Concept::class, 'articleLinks'],
            [Concept::class, 'questions'],
            [ConceptQuestion::class, 'scopePubliclyAnswerable'],
            [ConceptQuestion::class, 'concept'],
            [ConceptQuestion::class, 'targetArticle'],
            [AnalyticsExclusionService::class, 'shouldLoadAnalytics'],
        ];

        foreach ($expectations as [$class, $method]) {
            $this->assertStringContainsString(
                $method.'(',
                $doc,
                "Il documento dovrebbe citare '{$class}::{$method}()'."
            );
            $this->assertTrue(
                method_exists($class, $method),
                "Il metodo '{$class}::{$method}()' citato dal documento non esiste più."
            );
        }
    }

    public function test_the_content_graph_status_constants_the_doc_cites_still_match_the_real_models(): void
    {
        $doc = $this->entityContracts();

        $this->assertSame('draft', Concept::STATUS_DRAFT);
        $this->assertSame('active', Concept::STATUS_ACTIVE);
        $this->assertSame('inactive', Concept::STATUS_INACTIVE);
        $this->assertSame('draft', ConceptQuestion::STATUS_DRAFT);
        $this->assertSame('approved', ConceptQuestion::STATUS_APPROVED);
        $this->assertSame('inactive', ConceptQuestion::STATUS_INACTIVE);

        foreach (['STATUS_DRAFT', 'STATUS_ACTIVE', 'STATUS_INACTIVE'] as $constant) {
            $this->assertStringContainsString($constant, $doc);
        }
        foreach (['STATUS_DRAFT', 'STATUS_APPROVED', 'STATUS_INACTIVE'] as $constant) {
            $this->assertStringContainsString($constant, $doc);
        }
    }

    /**
     * Il finding concreto che ha motivato questo cantiere: il documento
     * "Domande di scienza" affermava che #279 non fosse mergiata,
     * mentre Concept/ConceptQuestion sono su main da tempo. Questo
     * tripwire impedisce che la correzione regredisca silenziosamente.
     */
    public function test_the_domande_di_scienza_doc_no_longer_claims_the_foundation_model_is_unmerged(): void
    {
        $doc = $this->domandeDiScienza();

        $this->assertStringNotContainsString('non e su `main`', $doc);
        $this->assertStringNotContainsString('non è su `main`', $doc);
        $this->assertStringContainsString('FOUNDATION MERGED', $doc);
        $this->assertTrue(class_exists(Concept::class));
        $this->assertTrue(class_exists(ConceptQuestion::class));
        $this->assertTrue(class_exists(ConceptController::class));
        $this->assertTrue(class_exists(ConceptQuestionController::class));
    }

    /**
     * L'altra metà dello stesso fatto: la fondazione è mergiata, ma la
     * missione pubblica ("Domande di scienza": route, hub, SEO) NON lo
     * è. Se una route pubblica `/domande/*` comparisse senza che questo
     * documento venga aggiornato di conseguenza, questo test lo blocca
     * — stesso principio del tripwire su admin.trust-knowledge.decision
     * in TrustEditorialProtocolDriftTest.
     */
    public function test_no_public_domande_route_exists_yet_and_the_doc_still_says_so(): void
    {
        $doc = $this->domandeDiScienza();

        $this->assertStringContainsString('nessuna route pubblica esiste oggi', $doc);
        $this->assertFalse(
            Route::has('domande.show'),
            'Se questa route esiste, la missione pubblica "Domande di scienza" è stata costruita e questo documento va aggiornato di conseguenza.'
        );

        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin/')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'domande/{',
                $route->uri(),
                "La route '{$route->uri()}' sembra implementare la pagina pubblica /domande/{slug} descritta come non ancora costruita."
            );
        }
    }

    private function entityContracts(): string
    {
        $content = file_get_contents(base_path('docs/ENTITY_CONTRACTS_CONTENT_ENTITIES.md'));
        $this->assertIsString($content);

        return $content;
    }

    private function domandeDiScienza(): string
    {
        $content = file_get_contents(base_path('docs/DOMANDE_DI_SCIENZA_FOUNDATION_DESIGN.md'));
        $this->assertIsString($content);

        return $content;
    }
}
