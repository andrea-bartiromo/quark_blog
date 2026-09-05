<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\SocialDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialDraft>
 */
class SocialDraftFactory extends Factory
{
    protected $model = SocialDraft::class;

    /**
     * Nessuna ArticleFactory esiste nel progetto (i test esistenti creano
     * Article::create() a mano): stesso pattern qui, un articolo minimo e
     * già pubblicato, coerente con l'uso più comune di una bozza Social.
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::create([
                'user_id' => User::factory()->create(['role' => 'author'])->id,
                'title' => 'Articolo di prova '.fake()->unique()->numerify('####'),
                'slug' => 'articolo-social-draft-'.fake()->unique()->numerify('######'),
                'body' => '<p>Corpo di prova.</p>',
                'category' => 'intelligenza-artificiale',
                'status' => Article::STATUS_PUBLISHED,
                'published_at' => now()->subDay(),
            ])->id,
            'channel' => SocialDraft::CHANNEL_FACEBOOK,
            'status' => SocialDraft::STATUS_DRAFT,
            'copy' => fake()->sentence(12),
            'destination_url' => null,
            'use_utm' => true,
            'utm_campaign' => null,
            'scheduled_at' => null,
            'created_by' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }

    public function forChannel(string $channel): static
    {
        return $this->state(fn () => ['channel' => $channel]);
    }

    public function reviewed(): static
    {
        return $this->state(fn () => ['status' => SocialDraft::STATUS_REVIEWED]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => SocialDraft::STATUS_APPROVED]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => SocialDraft::STATUS_SCHEDULED,
            'scheduled_at' => now()->addDays(3),
        ]);
    }
}
