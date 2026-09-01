<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlainTextPasteEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_redazione_editors_expose_the_explicit_plain_text_paste_command(): void
    {
        $editor = User::factory()->create(['role' => 'editor']);
        $author = User::factory()->create(['role' => 'author']);

        foreach ([
            [$editor, route('admin.articles.create')],
            [$author, route('redazione.articles.create')],
        ] as [$user, $url]) {
            $this->actingAs($user)
                ->get($url)
                ->assertOk()
                ->assertSee('Incolla testo semplice')
                ->assertSee("editor.ui.registry.addButton('pasteplaintext'", false)
                ->assertSee("clipboard.getData('text/plain')", false)
                ->assertSee("editor.once('paste'", false);
        }
    }
}
