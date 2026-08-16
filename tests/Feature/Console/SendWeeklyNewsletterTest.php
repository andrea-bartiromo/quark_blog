<?php

namespace Tests\Feature\Console;

use App\Console\Commands\SendWeeklyNewsletter;
use App\Jobs\SendNewsletterJob;
use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class SendWeeklyNewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_retrying_the_same_weekly_delivery_sends_one_email(): void
    {
        $subscriber = $this->confirmedSubscriber('subscriber@example.com');
        $this->publishedArticle();

        $deliveryKey = 'weekly:2026-W33:'.$subscriber->id;

        Mail::shouldReceive('send')->once()->andReturnNull();

        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
    }

    public function test_mail_failure_releases_the_claim_so_a_retry_can_send(): void
    {
        $subscriber = $this->confirmedSubscriber('retry@example.com');
        $this->publishedArticle();

        $deliveryKey = 'weekly:2026-W33:'.$subscriber->id;
        $cacheKey = 'newsletter:delivery:'.$deliveryKey;

        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('provider unreachable'));

        try {
            (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('provider unreachable', $e->getMessage());
        }

        // Stato coerente subito dopo il fallimento: nessuna claim residua.
        $this->assertFalse(Cache::has($cacheKey));

        Mail::shouldReceive('send')->once()->andReturnNull();
        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
    }

    public function test_no_articles_releases_the_claim_without_sending(): void
    {
        // Nessun articolo pubblicato: comportamento editoriale invariato
        // (early return), ma la claim non deve restare bloccata per
        // sempre se in seguito un articolo diventa disponibile.
        $subscriber = $this->confirmedSubscriber('no-articles@example.com');

        $deliveryKey = 'weekly:2026-W33:'.$subscriber->id;
        $cacheKey = 'newsletter:delivery:'.$deliveryKey;

        Mail::shouldReceive('send')->never();

        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();

        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_next_week_allows_a_new_delivery(): void
    {
        $subscriber = $this->confirmedSubscriber('weekly@example.com');
        $this->publishedArticle();

        Mail::shouldReceive('send')->twice()->andReturnNull();

        (new SendNewsletterJob($subscriber, 'weekly:2026-W33:'.$subscriber->id))->handle();
        (new SendNewsletterJob($subscriber, 'weekly:2026-W34:'.$subscriber->id))->handle();
    }

    public function test_two_different_subscribers_produce_two_distinct_deliveries(): void
    {
        $first = $this->confirmedSubscriber('first@example.com');
        $second = $this->confirmedSubscriber('second@example.com');
        $this->publishedArticle();

        Mail::shouldReceive('send')->twice()->andReturnNull();

        (new SendNewsletterJob($first, 'weekly:2026-W33:'.$first->id))->handle();
        (new SendNewsletterJob($second, 'weekly:2026-W33:'.$second->id))->handle();
    }

    public function test_command_dispatches_one_job_per_confirmed_subscriber_with_a_stable_weekly_key(): void
    {
        $confirmed = $this->confirmedSubscriber('confirmed@example.com');
        Newsletter::create([
            'email' => 'unconfirmed@example.com',
            'confirmed' => false,
            'token' => 'token-2',
            'unsubscribe_token' => 'unsub-2',
        ]);
        $this->publishedArticle();

        Bus::fake();

        $tester = $this->commandTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(SymfonyCommand::SUCCESS, $exitCode);

        $weekKey = now()->startOfWeek()->format('Y-m-d');

        Bus::assertDispatchedTimes(SendNewsletterJob::class, 1);
        Bus::assertDispatched(
            SendNewsletterJob::class,
            fn (SendNewsletterJob $job) => $job->subscriber->is($confirmed)
                && $job->deliveryKey === $weekKey.':'.$confirmed->id
        );
    }

    private function confirmedSubscriber(string $email): Newsletter
    {
        return Newsletter::create([
            'email' => $email,
            'confirmed' => true,
            'token' => hash('sha256', 'confirm-'.$email),
            'unsubscribe_token' => md5('unsubscribe-'.$email),
        ]);
    }

    private function publishedArticle(): Article
    {
        $author = User::factory()->create(['role' => 'author']);

        return Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato '.uniqid('', true),
            'slug' => 'articolo-pubblicato-'.uniqid('', true),
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);
    }

    private function commandTester(): CommandTester
    {
        $command = app(SendWeeklyNewsletter::class);
        $command->setLaravel(app());

        return new CommandTester($command);
    }
}
