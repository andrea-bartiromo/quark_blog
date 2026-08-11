<?php

namespace Tests\Feature\Console;

use App\Jobs\SendNewsletterJob;
use App\Models\Article;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
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
        $subscriber = Newsletter::create([
            'email' => 'subscriber@example.com',
            'confirmed' => true,
            'token' => 'confirm-token',
            'unsubscribe_token' => 'unsubscribe-token',
        ]);

        $author = User::factory()->create(['role' => 'author']);

        Article::create([
            'user_id' => $author->id,
            'title' => 'Articolo pubblicato',
            'slug' => 'articolo-pubblicato',
            'excerpt' => 'Sommario',
            'body' => '<p>Corpo.</p>',
            'category' => 'energia',
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        $deliveryKey = 'weekly:2026-W33:'.$subscriber->id;

        Mail::shouldReceive('send')->once()->andReturnNull();

        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
        (new SendNewsletterJob($subscriber, $deliveryKey))->handle();
    }
}
