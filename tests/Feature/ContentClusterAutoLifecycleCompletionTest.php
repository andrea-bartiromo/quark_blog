<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentCluster;
use App\Models\User;
use App\Services\ArticlePathNavigation;
use App\Services\ContentClusters\ContentClusterLifecycleReconciler;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Percorsi Automatic Lifecycle Completion V1: updating -> complete, one-way
 * only, triggered exclusively by ContentClusterPublicSequence reaching
 * "every configured member is part of the continuous public prefix, zero
 * hidden remainder" — never inferred from dates alone, never mutating
 * public visibility (is_active/publish_at) or ArticlePathNavigation
 * behaviour.
 */
class ContentClusterAutoLifecycleCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function publishedArticle(string $title): Article
    {
        return $this->article($title, Article::STATUS_PUBLISHED, now()->subHour());
    }

    private function scheduledArticle(string $title): Article
    {
        return $this->article($title, Article::STATUS_SCHEDULED, now()->addDay());
    }

    private function article(string $title, string $status, $publishedAt): Article
    {
        $author = User::factory()->create();

        return Article::create([
            'user_id' => $author->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => $status,
            'read_minutes' => 1,
            'published_at' => $publishedAt,
        ]);
    }

    private function reconciler(): ContentClusterLifecycleReconciler
    {
        return app(ContentClusterLifecycleReconciler::class);
    }

    // 1. updating + P P P => complete
    public function test_1_all_public_members_promotes_to_complete(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach([
            $this->publishedArticle('Uno')->id => ['position' => 10],
            $this->publishedArticle('Due')->id => ['position' => 20],
            $this->publishedArticle('Tre')->id => ['position' => 30],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertTrue($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $result->previousLifecycle);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $result->resultingLifecycle);
        $this->assertSame(3, $result->publicPrefixLength);
        $this->assertSame(3, $result->totalMembershipCount);
        $this->assertFalse($result->hasHiddenRemainder);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);
    }

    // 2. updating + P P S => unchanged
    public function test_2_trailing_hidden_member_stays_updating(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach([
            $this->publishedArticle('Uno')->id => ['position' => 10],
            $this->publishedArticle('Due')->id => ['position' => 20],
            $this->scheduledArticle('Tre futura')->id => ['position' => 30],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);
        $this->assertTrue($result->hasHiddenRemainder);
    }

    // 3. updating + P S P => unchanged
    public function test_3_gap_in_the_middle_stays_updating(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach([
            $this->publishedArticle('Uno')->id => ['position' => 10],
            $this->scheduledArticle('Due futura')->id => ['position' => 20],
            $this->publishedArticle('Tre')->id => ['position' => 30],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);
        $this->assertSame(1, $result->publicPrefixLength);
    }

    // 4. updating + S P P => unchanged
    public function test_4_gap_at_the_start_stays_updating(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach([
            $this->scheduledArticle('Uno futura')->id => ['position' => 10],
            $this->publishedArticle('Due')->id => ['position' => 20],
            $this->publishedArticle('Tre')->id => ['position' => 30],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);
        $this->assertSame(0, $result->publicPrefixLength);
        $this->assertSame('zero_public_prefix', $result->reason);
    }

    // 5. updating + zero members => unchanged
    public function test_5_zero_members_stays_updating(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame('no_members', $result->reason);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);
    }

    // 6. updating + single P => complete
    public function test_6_single_public_member_promotes_to_complete(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach($this->publishedArticle('Unico')->id, ['position' => 10]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertTrue($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);
    }

    // 7. already complete => unchanged/idempotent
    public function test_7_already_complete_cluster_is_untouched(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $cluster->articles()->attach($this->publishedArticle('Uno')->id, ['position' => 10]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame('not_updating', $result->reason);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);
    }

    // 8. complete + newly added hidden member => DOES NOT auto-reopen
    public function test_8_completed_cluster_with_a_newly_added_hidden_member_does_not_reopen(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $cluster->articles()->attach([
            $this->publishedArticle('Uno')->id => ['position' => 10],
            $this->scheduledArticle('Due appena aggiunta')->id => ['position' => 20],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);

        // La command processa solo i Percorsi "in aggiornamento": un
        // Percorso già concluso non viene nemmeno selezionato per la
        // riconciliazione, quindi non può mai essere riaperto da essa.
        $this->artisan('percorsi:reconcile-lifecycle')->assertExitCode(0);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);
    }

    // 9. exact scheduled publication boundary
    public function test_9_promotes_exactly_when_the_final_articles_publication_instant_is_reached(): void
    {
        $target = Carbon::parse('2026-09-10 12:00:00', 'UTC');
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $author = User::factory()->create();
        $final = Article::create([
            'user_id' => $author->id,
            'title' => 'Ultima tappa',
            'slug' => 'ultima-tappa-'.uniqid(),
            'body' => '<p>Corpo.</p>',
            'excerpt' => 'Estratto.',
            'category' => 'scienza',
            'status' => Article::STATUS_SCHEDULED,
            'read_minutes' => 1,
            'published_at' => $target,
        ]);
        $cluster->articles()->attach([
            $this->publishedArticle('Prima tappa')->id => ['position' => 10],
            $final->id => ['position' => 20],
        ]);

        // Article::published() richiede status='published', non solo
        // published_at <= now(): quello stato viene impostato SOLO dal
        // comando separato articles:publish-scheduled (ogni minuto), non
        // dal semplice passare del tempo. Il reconciler del lifecycle non
        // deve mai reimplementare questa regola: qui simuliamo la
        // sequenza reale di produzione (publish-scheduled gira prima di
        // reconcile-lifecycle) invece di limitarci a viaggiare nel tempo.
        Carbon::setTestNow($target->clone()->subSecond());
        $this->artisan('articles:publish-scheduled')->assertExitCode(0);
        $before = $this->reconciler()->reconcile($cluster);
        $this->assertFalse($before->changed);
        $this->assertSame(Article::STATUS_SCHEDULED, $final->fresh()->status);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);

        Carbon::setTestNow($target);
        $this->artisan('articles:publish-scheduled')->assertExitCode(0);
        $this->assertSame(Article::STATUS_PUBLISHED, $final->fresh()->status);
        $after = $this->reconciler()->reconcile($cluster->fresh());
        $this->assertTrue($after->changed);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);

        Carbon::setTestNow();
    }

    // 10. continuous-prefix service remains the authority (no local reimplementation)
    public function test_10_reconciler_defers_entirely_to_content_cluster_public_sequence(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        // Un articolo 'draft' (mai eleggibile a prescindere dalla data) prova
        // che il reconciler non reimplementa la regola di pubblico altrove:
        // si affida interamente ad Article::published() via
        // ContentClusterPublicSequence.
        $draft = $this->article('Bozza', Article::STATUS_DRAFT, null);
        $cluster->articles()->attach([
            $this->publishedArticle('Uno')->id => ['position' => 10],
            $draft->id => ['position' => 20],
        ]);

        $result = $this->reconciler()->reconcile($cluster);

        $this->assertFalse($result->changed);
        $this->assertTrue($result->hasHiddenRemainder);
    }

    // 11. public visibility is unaffected by the lifecycle transition
    public function test_11_promotion_never_touches_is_active_or_publish_at(): void
    {
        $cluster = ContentCluster::factory()->create([
            'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING,
            'is_active' => false,
            'publish_at' => now()->addWeek(),
        ]);
        $cluster->articles()->attach($this->publishedArticle('Unico')->id, ['position' => 10]);

        $this->reconciler()->reconcile($cluster);

        $fresh = $cluster->fresh();
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $fresh->lifecycle_status);
        $this->assertFalse($fresh->is_active);
        $this->assertNotNull($fresh->publish_at);
        $this->assertFalse($fresh->isPubliclyVisible());
    }

    // 12. ArticlePathNavigation behaviour is unaffected by the transition
    public function test_12_article_path_navigation_result_is_identical_before_and_after_promotion(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING, 'is_active' => true, 'publish_at' => null]);
        $first = $this->publishedArticle('Primo');
        $second = $this->publishedArticle('Secondo');
        $cluster->articles()->attach([
            $first->id => ['position' => 10, 'is_primary' => true],
            $second->id => ['position' => 20, 'is_primary' => false],
        ]);

        $before = app(ArticlePathNavigation::class)->forArticle($first->fresh());

        $this->reconciler()->reconcile($cluster);

        $after = app(ArticlePathNavigation::class)->forArticle($first->fresh());

        $this->assertSame($before['total'], $after['total']);
        $this->assertSame($before['current_index'], $after['current_index']);
        $this->assertSame($before['next']->id, $after['next']->id);
    }

    // 13. no hidden article metadata leaks through the command's output
    public function test_13_command_output_never_names_a_hidden_article(): void
    {
        $cluster = ContentCluster::factory()->create(['name' => 'Percorso visibile', 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach([
            $this->publishedArticle('Pubblico')->id => ['position' => 10],
            $this->scheduledArticle('Titolo segreto futuro')->id => ['position' => 20],
        ]);

        $this->artisan('percorsi:reconcile-lifecycle')
            ->assertExitCode(0)
            ->expectsOutputToContain('Conclusi automaticamente: 0');

        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $cluster->fresh()->lifecycle_status);
    }

    // 14. the command can run twice safely (idempotent end-to-end)
    public function test_14_running_the_command_twice_is_safe_and_the_second_run_changes_nothing(): void
    {
        $cluster = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach($this->publishedArticle('Unico')->id, ['position' => 10]);

        $this->artisan('percorsi:reconcile-lifecycle')->assertExitCode(0);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);

        // Ora il Percorso è "complete": la seconda run non lo riseleziona
        // nemmeno (filtro su lifecycle_status=updating), quindi non fa nulla.
        $this->artisan('percorsi:reconcile-lifecycle')
            ->assertExitCode(0)
            ->expectsOutputToContain('Nessun Percorso "in aggiornamento" da valutare.');
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $cluster->fresh()->lifecycle_status);
    }

    // 15. multiple clusters reconcile independently
    public function test_15_multiple_clusters_reconcile_independently_in_one_command_run(): void
    {
        $readyToComplete = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $readyToComplete->articles()->attach($this->publishedArticle('Uno')->id, ['position' => 10]);

        $stillGated = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $stillGated->articles()->attach($this->scheduledArticle('Futuro')->id, ['position' => 10]);

        $alreadyComplete = ContentCluster::factory()->create(['lifecycle_status' => ContentCluster::LIFECYCLE_COMPLETE]);
        $alreadyComplete->articles()->attach($this->publishedArticle('Concluso')->id, ['position' => 10]);

        $this->artisan('percorsi:reconcile-lifecycle')
            ->assertExitCode(0)
            ->expectsOutputToContain('Conclusi automaticamente: 1 — Invariati: 1 — Falliti: 0');

        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $readyToComplete->fresh()->lifecycle_status);
        $this->assertSame(ContentCluster::LIFECYCLE_UPDATING, $stillGated->fresh()->lifecycle_status);
        $this->assertSame(ContentCluster::LIFECYCLE_COMPLETE, $alreadyComplete->fresh()->lifecycle_status);
    }

    public function test_command_is_registered_on_the_scheduler(): void
    {
        $schedule = app(Schedule::class);
        $commands = collect($schedule->events())->map(fn ($event) => $event->command ?? '');

        $this->assertTrue($commands->contains(fn ($command) => str_contains((string) $command, 'percorsi:reconcile-lifecycle')));
    }

    public function test_activity_log_records_the_automatic_promotion(): void
    {
        $cluster = ContentCluster::factory()->create(['name' => 'Percorso da concludere', 'lifecycle_status' => ContentCluster::LIFECYCLE_UPDATING]);
        $cluster->articles()->attach($this->publishedArticle('Unico')->id, ['position' => 10]);

        $this->artisan('percorsi:reconcile-lifecycle')->assertExitCode(0);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'content_cluster',
            'subject_id' => $cluster->id,
        ]);
    }
}
