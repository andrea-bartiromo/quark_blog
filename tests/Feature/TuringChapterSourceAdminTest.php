<?php

namespace Tests\Feature;

use App\Models\TuringChapterSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 61 (programma "100 cantieri Kairus"). Registro fonti per
 * capitolo: nessuna riga viene mai creata da questo programma, solo da
 * un editor umano tramite questo form — vedi il docblock di
 * App\Models\TuringChapterSource.
 */
class TuringChapterSourceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    public function test_guest_cannot_view_the_sources_admin_page(): void
    {
        $this->get(route('admin.turing.chapter-sources'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_add_a_source(): void
    {
        $this->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'enigma',
            'label' => 'Test',
            'url' => 'https://example.com',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, TuringChapterSource::count());
    }

    public function test_the_table_starts_empty_with_no_seeded_sources(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing.chapter-sources'));

        $response->assertOk();
        $response->assertSeeText('Nessuna fonte registrata.');
        $this->assertSame(0, TuringChapterSource::count());
    }

    public function test_editor_can_add_a_source_for_a_real_chapter(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'computation',
            'label' => 'On Computable Numbers (1936)',
            'url' => 'https://www.cs.virginia.edu/~robins/Turing_Paper_1936.pdf',
            'year' => '1936',
        ]);

        $response->assertRedirect(route('admin.turing.chapter-sources'));
        $this->assertSame(1, TuringChapterSource::count());

        $source = TuringChapterSource::first();
        $this->assertSame('computation', $source->chapter);
        $this->assertSame('On Computable Numbers (1936)', $source->label);
        $this->assertSame('1936', $source->year);
    }

    public function test_adding_a_source_for_an_invalid_chapter_is_rejected(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'hub',
            'label' => 'Test',
            'url' => 'https://example.com',
        ]);

        $response->assertSessionHasErrors('chapter');
        $this->assertSame(0, TuringChapterSource::count());
    }

    public function test_adding_a_source_with_an_invalid_url_is_rejected(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'enigma',
            'label' => 'Test',
            'url' => 'not-a-url',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertSame(0, TuringChapterSource::count());
    }

    public function test_the_year_field_is_optional(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'legacy',
            'label' => 'Scuse pubbliche del 2009',
            'url' => 'https://example.com/2009-apology',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull(TuringChapterSource::first()->year);
    }

    public function test_sources_added_later_get_a_higher_sort_order_within_their_chapter(): void
    {
        $editor = $this->actingAs($this->editor());

        $editor->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'ai', 'label' => 'Prima', 'url' => 'https://example.com/1',
        ]);
        $editor->post(route('admin.turing.chapter-sources.store'), [
            'chapter' => 'ai', 'label' => 'Seconda', 'url' => 'https://example.com/2',
        ]);

        $sources = TuringChapterSource::where('chapter', 'ai')->orderBy('sort_order')->get();
        $this->assertSame('Prima', $sources[0]->label);
        $this->assertSame('Seconda', $sources[1]->label);
        $this->assertLessThan($sources[1]->sort_order, $sources[0]->sort_order);
    }

    public function test_editor_can_remove_a_source(): void
    {
        $source = TuringChapterSource::create([
            'chapter' => 'intelligence',
            'label' => 'Test',
            'url' => 'https://example.com',
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->editor())
            ->delete(route('admin.turing.chapter-sources.destroy', $source));

        $response->assertRedirect(route('admin.turing.chapter-sources'));
        $this->assertSame(0, TuringChapterSource::count());
    }

    public function test_guest_cannot_remove_a_source(): void
    {
        $source = TuringChapterSource::create([
            'chapter' => 'intelligence',
            'label' => 'Test',
            'url' => 'https://example.com',
            'sort_order' => 0,
        ]);

        $this->delete(route('admin.turing.chapter-sources.destroy', $source))
            ->assertRedirect(route('login'));

        $this->assertSame(1, TuringChapterSource::count());
    }

    public function test_the_admin_page_lists_sources_grouped_under_their_own_chapter_only(): void
    {
        TuringChapterSource::create(['chapter' => 'enigma', 'label' => 'Fonte Enigma', 'url' => 'https://example.com/e', 'sort_order' => 0]);
        TuringChapterSource::create(['chapter' => 'legacy', 'label' => 'Fonte Legacy', 'url' => 'https://example.com/l', 'sort_order' => 0]);

        $html = $this->actingAs($this->editor())->get(route('admin.turing.chapter-sources'))->getContent();

        $enigmaSectionPos = strpos($html, '>enigma<');
        $legacySectionPos = strpos($html, '>legacy<');
        $fonteEnigmaPos = strpos($html, 'Fonte Enigma');
        $fonteLegacyPos = strpos($html, 'Fonte Legacy');

        $this->assertNotFalse($enigmaSectionPos);
        $this->assertNotFalse($legacySectionPos);
        $this->assertGreaterThan($enigmaSectionPos, $fonteEnigmaPos);
        $this->assertLessThan($legacySectionPos, $fonteEnigmaPos);
        $this->assertGreaterThan($legacySectionPos, $fonteLegacyPos);
    }

    public function test_the_turing_lite_editor_links_to_the_sources_admin_page(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.turing'));

        $response->assertOk();
        $response->assertSee(route('admin.turing.chapter-sources'), false);
    }
}
