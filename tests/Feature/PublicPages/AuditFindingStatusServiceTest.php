<?php

namespace Tests\Feature\PublicPages;

use App\Models\AuditFindingStatus;
use App\Models\User;
use App\Services\PublicPages\AuditFindingStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 31 (programma 100-cantieri Kairus). Stesso pattern di
 * App\Services\SearchConsole\SearchOpportunityStatusService (Mission 6),
 * applicato ai finding di PublicHealthDashboardService (Cantiere 30).
 */
class AuditFindingStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_finding_without_a_persisted_status_is_absent_from_statuses_for(): void
    {
        $statuses = app(AuditFindingStatusService::class)->statusesFor(['seo|home']);

        $this->assertSame([], $statuses);
    }

    public function test_set_status_creates_a_new_record(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);

        $record = app(AuditFindingStatusService::class)->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_IN_CARICO, $editor);

        $this->assertSame('seo', $record->domain);
        $this->assertSame('seo|home', $record->finding_key);
        $this->assertSame(AuditFindingStatus::STATUS_IN_CARICO, $record->status);
        $this->assertSame($editor->id, $record->updated_by);
        $this->assertDatabaseCount('audit_finding_statuses', 1);
    }

    public function test_set_status_updates_the_existing_record_instead_of_creating_a_duplicate(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $service = app(AuditFindingStatusService::class);

        $service->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_IN_CARICO, $editor);
        $service->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_DISMISSED, $editor);

        $this->assertDatabaseCount('audit_finding_statuses', 1);
        $this->assertSame(AuditFindingStatus::STATUS_DISMISSED, AuditFindingStatus::query()->sole()->status);
    }

    public function test_statuses_for_returns_one_query_worth_of_results_keyed_by_finding_key(): void
    {
        $service = app(AuditFindingStatusService::class);
        $service->setStatus('seo', 'seo|home', AuditFindingStatus::STATUS_IN_CARICO, null);
        $service->setStatus('wcag', 'wcag|contatti', AuditFindingStatus::STATUS_DISMISSED, null);

        $statuses = $service->statusesFor(['seo|home', 'wcag|contatti', 'links|/mai-visto']);

        $this->assertSame([
            'seo|home' => AuditFindingStatus::STATUS_IN_CARICO,
            'wcag|contatti' => AuditFindingStatus::STATUS_DISMISSED,
        ], $statuses);
    }
}
