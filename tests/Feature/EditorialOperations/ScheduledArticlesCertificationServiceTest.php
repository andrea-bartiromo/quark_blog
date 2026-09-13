<?php

namespace Tests\Feature\EditorialOperations;

use App\Models\Article;
use App\Models\User;
use App\Services\EditorialOperations\ScheduledArticlesCertificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Cantiere 37 (programma 100-cantieri Kairus). Estratto da
 * CertifyScheduledArticles (editorial:scheduled-certification) — la
 * copertura esaustiva della logica di certificazione resta in
 * CertifyScheduledArticlesCommandTest (comportamento invariato dopo
 * l'estrazione, riprovato lì); qui si prova solo che il servizio
 * standalone rispetti la finestra from/days passata da un chiamante
 * diverso dal comando CLI (ScheduledPublicationsReportController).
 */
class ScheduledArticlesCertificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): ScheduledArticlesCertificationService
    {
        return app(ScheduledArticlesCertificationService::class);
    }

    /** @param array<string, mixed> $overrides */
    private function article(string $title, Carbon $publishedAt, array $overrides = []): Article
    {
        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo di prova.</p>',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => $publishedAt,
        ], $overrides)));
    }

    public function test_report_includes_only_articles_within_the_requested_window(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $within = $this->article('Entro finestra', now()->addDays(29));
        $this->article('Fuori finestra', now()->addDays(31));
        $this->article('Già pubblicato', now()->subDay());

        $payload = $this->service()->report(now(), 30);

        $this->assertSame(1, $payload['count']);
        $this->assertSame([$within->id], array_column($payload['items'], 'id'));
        $this->assertTrue($payload['read_only']);
    }

    public function test_report_window_boundaries_reflect_the_from_and_days_arguments(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $from = now()->addDays(5);

        $payload = $this->service()->report($from, 30);

        $this->assertSame($from->toISOString(), $payload['from']);
        $this->assertSame($from->copy()->addDays(30)->toISOString(), $payload['until']);
    }

    public function test_a_shared_publication_instant_is_reported_as_a_collision(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $this->article('Primo', now()->addDay());
        $this->article('Secondo', now()->addDay());

        $payload = $this->service()->report(now(), 30);

        $this->assertSame(2, $payload['items'][0]['collision_count']);
        $this->assertSame(2, $payload['items'][1]['collision_count']);
    }
}
