<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Concept;
use App\Services\ContentGraph\ConceptDuplicateAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mission 17 — Duplicate Concept Detection: audit read-only, mai un
 * merge/eliminazione automatico. Stesso contratto "read-only, editor
 * decide" già verificato per PercorsoCoverageAuditService.
 */
class ConceptDuplicateAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ConceptDuplicateAuditService
    {
        return app(ConceptDuplicateAuditService::class);
    }

    public function test_no_duplicates_returns_an_empty_list(): void
    {
        Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        Concept::create(['name' => 'Entanglement quantistico', 'slug' => 'entanglement-quantistico', 'status' => 'active']);

        $this->assertSame([], $this->service()->audit());
    }

    public function test_two_concepts_with_the_same_name_after_normalization_are_flagged(): void
    {
        $a = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $b = Concept::create(['name' => "  Entropia's twin  ", 'slug' => 'entropia-twin', 'status' => 'draft']);
        $b->update(['name' => 'ENTROPIA']);

        $result = $this->service()->audit();

        $this->assertCount(1, $result);
        $this->assertSame('entropia', $result[0]['normalized_text']);
        $ids = collect($result[0]['concepts'])->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_an_alias_colliding_with_another_concepts_name_is_flagged(): void
    {
        $named = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $other = Concept::create(['name' => 'Disordine termodinamico', 'slug' => 'disordine-termodinamico', 'status' => 'active']);
        $other->aliases()->create(['alias' => 'entropia']);

        $result = $this->service()->audit();

        $this->assertCount(1, $result);
        $matches = collect($result[0]['concepts'])->keyBy('id');
        $this->assertSame('name', $matches[$named->id]['matched_via']);
        $this->assertSame('alias', $matches[$other->id]['matched_via']);
    }

    public function test_two_aliases_on_different_concepts_colliding_are_flagged(): void
    {
        $a = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $b = Concept::create(['name' => 'Disordine termodinamico', 'slug' => 'disordine-termodinamico', 'status' => 'active']);
        $a->aliases()->create(['alias' => 'Shannon entropy']);
        $b->aliases()->create(['alias' => '  shannon entropy?  ']);

        $result = $this->service()->audit();

        $this->assertCount(1, $result);
        $this->assertSame('shannon entropy', $result[0]['normalized_text']);
    }

    public function test_aliases_within_the_same_concept_never_self_flag(): void
    {
        $concept = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        $concept->aliases()->create(['alias' => 'Entropia']);
        $concept->aliases()->create(['alias' => 'entropia']);

        $this->assertSame([], $this->service()->audit());
    }

    public function test_legitimately_distinct_concepts_are_never_fuzzy_matched(): void
    {
        Concept::create(['name' => 'Rete Neurale', 'slug' => 'rete-neurale', 'status' => 'active']);
        Concept::create(['name' => 'Reti Neurali', 'slug' => 'reti-neurali', 'status' => 'active']);

        $this->assertSame([], $this->service()->audit());
    }

    public function test_a_group_with_more_than_two_colliding_concepts_lists_all_of_them(): void
    {
        $a = Concept::create(['name' => 'Entropia', 'slug' => 'entropia-1', 'status' => 'active']);
        $b = Concept::create(['name' => 'entropia', 'slug' => 'entropia-2', 'status' => 'draft']);
        $c = Concept::create(['name' => 'Buco nero', 'slug' => 'buco-nero', 'status' => 'active']);
        $c->aliases()->create(['alias' => 'Entropia']);

        $result = $this->service()->audit();

        $this->assertCount(1, $result);
        $ids = collect($result[0]['concepts'])->pluck('id')->all();
        $this->assertCount(3, $ids);
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertContains($c->id, $ids);
    }

    public function test_audit_never_mutates_any_concept_or_alias(): void
    {
        $a = Concept::create(['name' => 'Entropia', 'slug' => 'entropia', 'status' => 'active']);
        Concept::create(['name' => 'entropia', 'slug' => 'entropia-2', 'status' => 'draft']);

        $this->service()->audit();

        $this->assertDatabaseCount('concepts', 2);
        $this->assertSame('Entropia', $a->fresh()->name);
    }
}
