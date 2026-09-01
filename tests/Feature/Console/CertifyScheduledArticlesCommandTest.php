<?php

namespace Tests\Feature\Console;

use App\Models\Article;
use App\Models\Concept;
use App\Models\ContentCluster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CertifyScheduledArticlesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_reports_only_the_next_fourteen_days_in_stable_order(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $second = $this->article('Secondo', now()->addDays(2));
        $first = $this->article('Primo', now()->addDay());
        $this->article('Fuori finestra', now()->addDays(15));
        $this->article('Già scaduto', now()->subMinute());

        $payload = $this->runJson();

        $this->assertSame(2, $payload['count']);
        $this->assertSame([$first->id, $second->id], array_column($payload['items'], 'id'));
        $this->assertTrue($payload['read_only']);
    }

    public function test_it_reports_health_paths_concepts_sources_collisions_and_hidden_page_contract(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $article = $this->article('Completo', now()->addDay(), ['primary_sources' => "https://example.test/source"]);
        $other = $this->article('Collisione', now()->addDay());
        $cluster = ContentCluster::create(['name' => 'Percorso Test', 'slug' => 'percorso-test', 'is_active' => true]);
        $cluster->articles()->attach($article, ['position' => 10, 'is_primary' => true]);
        $concept = Concept::create(['name' => 'Concetto Test', 'status' => Concept::STATUS_ACTIVE]);
        $article->contentConcepts()->create(['concept_id' => $concept->id, 'relation_type' => 'primary', 'weight' => 100]);

        $payload = $this->runJson();
        $item = collect($payload['items'])->firstWhere('id', $article->id);

        $this->assertSame(['Percorso Test'], $item['percorsi']);
        $this->assertSame(['Concetto Test'], $item['concepts']);
        $this->assertTrue($item['has_sources']);
        $this->assertSame(2, $item['collision_count']);
        $this->assertSame('404_until_publication', $item['public_page_expectation']);
        $this->assertIsArray($item['content_health']['warnings']);
        $this->assertDatabaseHas('articles', ['id' => $other->id, 'status' => Article::STATUS_SCHEDULED]);
    }

    public function test_it_is_read_only_and_rejects_unsafe_windows(): void
    {
        Carbon::setTestNow('2026-09-01 10:00:00 UTC');
        $article = $this->article('Immutabile', now()->addDay());
        $before = DB::table('articles')->where('id', $article->id)->first();

        $this->artisan('editorial:scheduled-certification')->assertSuccessful();
        $after = DB::table('articles')->where('id', $article->id)->first();

        $this->assertEquals($before, $after);
        $this->artisan('editorial:scheduled-certification', ['--days' => 0])->assertFailed();
        $this->artisan('editorial:scheduled-certification', ['--days' => 32])->assertFailed();
        $this->artisan('editorial:scheduled-certification', ['--from' => 'not-a-date'])->assertFailed();
    }

    /** @param array<string, mixed> $overrides */
    private function article(string $title, Carbon $publishedAt, array $overrides = []): Article
    {
        return Article::withoutEvents(fn () => Article::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'excerpt' => 'Sommario',
            'body' => '<a href="/articolo/interno">Link interno</a>',
            'category' => 'fisica',
            'status' => Article::STATUS_SCHEDULED,
            'published_at' => $publishedAt,
            'seo_title' => $title,
            'seo_description' => 'Descrizione SEO',
        ], $overrides)));
    }

    /** @return array<string, mixed> */
    private function runJson(): array
    {
        Artisan::call('editorial:scheduled-certification', ['--json' => true]);

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }
}
