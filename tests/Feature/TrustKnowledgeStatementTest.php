<?php

namespace Tests\Feature;

use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\TrustKnowledgeStatement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 38 (programma "100 cantieri Kairus"): modello interno "Cosa
 * sappiamo davvero" — copre solo il contratto del modello (relazioni,
 * hasBeenChecked()), l'autorizzazione è coperta da
 * TrustKnowledgeStatementControllerTest.
 */
class TrustKnowledgeStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_statement_with_no_last_checked_date_has_never_been_checked(): void
    {
        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
        ]);

        $this->assertFalse($statement->hasBeenChecked());
    }

    public function test_a_statement_with_a_declared_date_has_been_checked(): void
    {
        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
            'last_checked_at' => '2026-09-01',
        ]);

        $this->assertTrue($statement->hasBeenChecked());
    }

    public function test_a_statement_can_optionally_link_to_a_concept_and_a_cluster(): void
    {
        $concept = Concept::create(['name' => 'Concetto', 'status' => Concept::STATUS_ACTIVE]);
        $cluster = ContentCluster::create(['name' => 'Percorso', 'slug' => 'percorso-collegato', 'is_active' => true]);

        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
            'concept_id' => $concept->id,
            'content_cluster_id' => $cluster->id,
        ]);

        $this->assertTrue($statement->concept->is($concept));
        $this->assertTrue($statement->contentCluster->is($cluster));
    }

    public function test_a_statement_with_no_link_has_null_concept_and_cluster(): void
    {
        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
        ]);

        $this->assertNull($statement->concept);
        $this->assertNull($statement->contentCluster);
    }

    public function test_deleting_a_concept_nullifies_the_link_instead_of_deleting_the_statement(): void
    {
        $concept = Concept::create(['name' => 'Concetto da eliminare', 'status' => Concept::STATUS_ACTIVE]);
        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
            'concept_id' => $concept->id,
        ]);

        $concept->delete();

        $this->assertNull($statement->fresh()->concept_id);
        $this->assertDatabaseHas('trust_knowledge_statements', ['id' => $statement->id]);
    }

    public function test_created_by_tracks_the_authoring_user(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $statement = TrustKnowledgeStatement::create([
            'domanda' => 'Domanda',
            'consenso' => 'Consenso',
            'incertezza' => 'Incertezza',
            'created_by' => $editor->id,
        ]);

        $this->assertTrue($statement->createdBy->is($editor));
    }
}
