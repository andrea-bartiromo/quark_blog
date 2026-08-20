<?php

namespace Tests\Feature\Admin;

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminArticlesSearchEscapingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function article(User $user, string $title): Article
    {
        return Article::create([
            'user_id' => $user->id,
            'title' => $title,
            'slug' => 'search-escaping-'.uniqid(),
            'body' => 'Testo di controllo.',
            'category' => 'scienza',
            'cover_image' => null,
            'status' => 'draft',
            'read_minutes' => 1,
            'verification_status' => 'unverified',
        ]);
    }

    public function test_percent_is_literal_not_a_sql_wildcard(): void
    {
        $editor = $this->editor();
        $this->article($editor, 'Sconto del 50% reale');
        $this->article($editor, 'Sconto del 50 percento reale');

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => '50%']));

        $response->assertOk();
        $response->assertSee('Sconto del 50% reale');
        $response->assertDontSee('Sconto del 50 percento reale');
    }

    public function test_underscore_is_literal_not_a_single_character_wildcard(): void
    {
        $editor = $this->editor();
        $this->article($editor, 'Manuale nome_file definitivo');
        $this->article($editor, 'Manuale nomeXfile definitivo');

        $response = $this->actingAs($editor)->get(route('admin.articles', ['q' => 'nome_file']));

        $response->assertOk();
        $response->assertSee('Manuale nome_file definitivo');
        $response->assertDontSee('Manuale nomeXfile definitivo');
    }
}
