<?php

namespace Tests\Feature\ContentGraph;

use App\Models\Concept;
use App\Services\ContentGraph\ConceptAliasIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConceptAliasIntegrityServiceTest extends TestCase
{
    use RefreshDatabase;

    private function concept(string $name, string $slug): Concept
    {
        return Concept::create([
            'name' => $name,
            'slug' => $slug,
            'status' => Concept::STATUS_ACTIVE,
        ]);
    }

    public function test_reports_exact_alias_duplicates_across_concepts(): void
    {
        $first = $this->concept('Primo', 'primo');
        $second = $this->concept('Secondo', 'secondo');
        $first->aliases()->create(['alias' => 'Campo quantistico']);
        $second->aliases()->create(['alias' => 'Campo quantistico']);

        $findings = app(ConceptAliasIntegrityService::class)->audit();

        $exact = collect($findings)->firstWhere('code', ConceptAliasIntegrityService::DUPLICATE_EXACT);
        $this->assertNotNull($exact);
        $this->assertSame([1, 2], collect($exact['aliases'])->pluck('concept_id')->sort()->values()->all());
    }

    public function test_reports_case_insensitive_duplicates_without_calling_them_exact(): void
    {
        $first = $this->concept('Primo', 'primo');
        $second = $this->concept('Secondo', 'secondo');
        $first->aliases()->create(['alias' => 'Entropia']);
        $second->aliases()->create(['alias' => 'ENTROPIA']);

        $findings = app(ConceptAliasIntegrityService::class)->audit();

        $this->assertTrue(collect($findings)->contains(
            fn (array $finding) => $finding['code'] === ConceptAliasIntegrityService::DUPLICATE_CASE_INSENSITIVE
                && $finding['normalized_text'] === 'entropia'
        ));
        $this->assertFalse(collect($findings)->contains(
            fn (array $finding) => $finding['code'] === ConceptAliasIntegrityService::DUPLICATE_EXACT
        ));
    }

    public function test_reports_alias_matching_another_concept_canonical_name(): void
    {
        $canonical = $this->concept('Materia oscura', 'materia-oscura');
        $other = $this->concept('Cosmologia', 'cosmologia');
        $other->aliases()->create(['alias' => 'MATERIA OSCURA']);

        $findings = app(ConceptAliasIntegrityService::class)->audit();

        $finding = collect($findings)->firstWhere(
            'code',
            ConceptAliasIntegrityService::MATCHES_OTHER_CONCEPT_NAME,
        );

        $this->assertNotNull($finding);
        $this->assertSame($other->id, $finding['aliases'][0]['concept_id']);
        $this->assertStringContainsString((string) $other->id, $finding['aliases'][0]['edit_url']);
        $this->assertNotSame($canonical->id, $finding['aliases'][0]['concept_id']);
    }

    public function test_reports_whitespace_only_legacy_alias(): void
    {
        $concept = $this->concept('Vuoto', 'vuoto');
        $concept->aliases()->create(['alias' => '   ']);

        $findings = app(ConceptAliasIntegrityService::class)->audit();

        $this->assertTrue(collect($findings)->contains(
            fn (array $finding) => $finding['code'] === ConceptAliasIntegrityService::EMPTY_AFTER_NORMALIZATION
                && $finding['aliases'][0]['concept_id'] === $concept->id
        ));
    }

    public function test_audit_is_read_only_and_query_bounded(): void
    {
        $concept = $this->concept('Relatività', 'relativita');
        $alias = $concept->aliases()->create(['alias' => 'Teoria della relatività']);
        $before = $alias->fresh()->getAttributes();

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(ConceptAliasIntegrityService::class)->audit();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(2, $queries);
        $this->assertSame($before, $alias->fresh()->getAttributes());
    }
}
