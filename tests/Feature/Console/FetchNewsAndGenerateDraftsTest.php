<?php

namespace Tests\Feature\Console;

use App\Console\Commands\FetchNewsAndGenerateDrafts;
use App\Models\NewsSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class FetchNewsAndGenerateDraftsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.anthropic.key' => null]);
    }

    public function test_generation_failure_is_not_persisted_and_returns_failure(): void
    {
        $tester = $this->testerForRss($this->rss([
            ['title' => 'Nuova ricerca scientifica', 'url' => 'https://example.test/news/one'],
        ]));

        $exitCode = $tester->execute([]);

        $this->assertSame(SymfonyCommand::FAILURE, $exitCode);
        $this->assertDatabaseCount('news_suggestions', 0);
        $this->assertStringContainsString('ANTHROPIC_API_KEY non configurata', $tester->getDisplay());
    }

    public function test_generation_failure_releases_claim_so_a_retry_can_attempt_generation_again(): void
    {
        $url = 'https://example.test/news/retry';
        $rss = $this->rss([
            ['title' => 'Nuova ricerca energia da ritentare', 'url' => $url],
        ]);

        $first = $this->testerForRss($rss);
        $this->assertSame(SymfonyCommand::FAILURE, $first->execute([]));
        $this->assertDatabaseCount('news_suggestions', 0);

        $claim = Cache::lock('news:fetch:item:'.sha1($url), 120);
        $this->assertTrue($claim->get());
        $claim->release();

        $retry = $this->testerForRss($rss);
        $this->assertSame(SymfonyCommand::FAILURE, $retry->execute([]));
        $this->assertStringContainsString('ANTHROPIC_API_KEY non configurata', $retry->getDisplay());
        $this->assertDatabaseCount('news_suggestions', 0);
    }

    public function test_an_item_already_claimed_by_another_run_is_skipped_before_ai_generation(): void
    {
        $url = 'https://example.test/news/claimed';
        $lock = Cache::lock('news:fetch:item:'.sha1($url), 120);
        $this->assertTrue($lock->get());

        try {
            $tester = $this->testerForRss($this->rss([
                ['title' => 'Ricerca energia già in lavorazione', 'url' => $url],
            ]));

            $exitCode = $tester->execute([]);

            $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);
            $this->assertDatabaseCount('news_suggestions', 0);
            $this->assertStringNotContainsString('ANTHROPIC_API_KEY non configurata', $tester->getDisplay());
        } finally {
            $lock->release();
        }
    }

    public function test_generation_failure_does_not_stop_later_items_in_the_batch(): void
    {
        $tester = $this->testerForRss($this->rss([
            ['title' => 'Prima ricerca energia', 'url' => 'https://example.test/news/first'],
            ['title' => 'Seconda ricerca energia', 'url' => 'https://example.test/news/second'],
        ]));

        $this->assertSame(SymfonyCommand::FAILURE, $tester->execute([]));
        $this->assertDatabaseCount('news_suggestions', 0);
        $this->assertSame(2, substr_count($tester->getDisplay(), 'ANTHROPIC_API_KEY non configurata'));
    }

    public function test_existing_suggestion_is_idempotently_skipped_on_rerun(): void
    {
        NewsSuggestion::create([
            'source_title' => 'Già presente',
            'source_url' => 'https://example.test/news/existing',
            'source_name' => 'example.test',
            'source_excerpt' => 'ricerca energia',
            'category' => 'energia',
            'status' => 'pending',
            'fetched_at' => now(),
        ]);

        $tester = $this->testerForRss($this->rss([
            ['title' => 'Ricerca energia già presente', 'url' => 'https://example.test/news/existing'],
        ]));

        $this->assertSame(SymfonyCommand::SUCCESS, $tester->execute([]));
        $this->assertDatabaseCount('news_suggestions', 1);
        $this->assertStringNotContainsString('ANTHROPIC_API_KEY non configurata', $tester->getDisplay());
    }

    private function testerForRss(string $rss): CommandTester
    {
        $command = app(FetchNewsAndGenerateDrafts::class);
        $command->setLaravel(app());

        $feeds = new ReflectionProperty($command, 'feeds');
        $feeds->setAccessible(true);
        $feeds->setValue($command, [
            'energia' => ['data://text/plain,'.rawurlencode($rss)],
        ]);

        return new CommandTester($command);
    }

    private function rss(array $items): string
    {
        $xml = '<?xml version="1.0"?><rss><channel>';

        foreach ($items as $item) {
            $xml .= '<item>'
                .'<title>'.htmlspecialchars($item['title'], ENT_XML1).'</title>'
                .'<link>'.htmlspecialchars($item['url'], ENT_XML1).'</link>'
                .'<description>ricerca scientifica energia</description>'
                .'</item>';
        }

        return $xml.'</channel></rss>';
    }
}
