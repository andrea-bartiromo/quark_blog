<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Prompt 3 (pianificazione categorie): il form editoriale
 * (Admin\CategoryController) deve poter impostare bozza/programmato/
 * pubblicato senza mai pubblicare o modificare automaticamente gli
 * articoli collegati — vedi Admin\CategoryController::validated().
 */
class CategoryAdminSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function payload(array $overrides = []): array
    {
        // Come ogni checkbox HTML: se il payload di test non la includesse
        // esplicitamente, l'assenza equivarrebbe a "deselezionata" (vedi
        // $request->boolean('is_active') in Admin\CategoryController) —
        // qui di default "attiva", non l'oggetto di questi test.
        return array_merge([
            'name' => 'Categoria Admin',
            'slug' => '',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_creating_a_category_without_a_status_defaults_to_published_now(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'UTC'));

        $this->actingAs($this->editor())->post(route('admin.categories.store'), $this->payload());

        $category = Category::where('name', 'Categoria Admin')->firstOrFail();

        $this->assertSame(Category::STATUS_PUBLISHED, $category->status);
        $this->assertTrue($category->published_at->equalTo(now()));
        $this->assertTrue($category->isPubliclyVisible());

        Carbon::setTestNow();
    }

    public function test_creating_a_draft_category_leaves_published_at_null(): void
    {
        $this->actingAs($this->editor())->post(route('admin.categories.store'), $this->payload([
            'status' => Category::STATUS_DRAFT,
        ]));

        $category = Category::where('name', 'Categoria Admin')->firstOrFail();

        $this->assertSame(Category::STATUS_DRAFT, $category->status);
        $this->assertNull($category->published_at);
        $this->assertFalse($category->isPubliclyVisible());
    }

    public function test_creating_a_scheduled_category_converts_editorial_rome_input_to_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-01 08:00:00', 'UTC'));

        $this->actingAs($this->editor())->post(route('admin.categories.store'), $this->payload([
            'status' => Category::STATUS_SCHEDULED,
            'scheduled_date' => '2026-07-14',
            'scheduled_time' => '10:00',
        ]));

        $category = Category::where('name', 'Categoria Admin')->firstOrFail();

        $this->assertSame(Category::STATUS_SCHEDULED, $category->status);
        // 2026-07-14 10:00 Europe/Rome (CEST, UTC+2) == 08:00 UTC.
        $this->assertSame('2026-07-14 08:00:00', $category->published_at->utc()->format('Y-m-d H:i:s'));
        $this->assertFalse($category->isPubliclyVisible());

        Carbon::setTestNow();
    }

    public function test_creating_a_scheduled_category_without_date_or_time_fails_validation(): void
    {
        $response = $this->actingAs($this->editor())->post(route('admin.categories.store'), $this->payload([
            'status' => Category::STATUS_SCHEDULED,
        ]));

        $response->assertSessionHasErrors(['scheduled_date', 'scheduled_time']);
        $this->assertDatabaseMissing('categories', ['name' => 'Categoria Admin']);
    }

    public function test_editing_an_already_published_category_never_moves_published_at_forward(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 09:00:00', 'UTC'));
        $category = Category::create([
            'name' => 'Categoria Stabile',
            'slug' => 'categoria-stabile',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);
        $originalPublishedAt = $category->fresh()->published_at;

        Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00', 'UTC'));
        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), $this->payload([
            'name' => 'Categoria Stabile',
            'slug' => 'categoria-stabile',
            'description' => 'Descrizione aggiornata, non è una nuova pubblicazione.',
            'status' => Category::STATUS_PUBLISHED,
        ]));

        $this->assertTrue($category->fresh()->published_at->equalTo($originalPublishedAt));

        Carbon::setTestNow();
    }

    public function test_switching_from_scheduled_to_published_publishes_immediately_not_at_the_old_schedule(): void
    {
        $category = Category::create([
            'name' => 'Categoria Da Anticipare',
            'slug' => 'categoria-da-anticipare',
            'is_active' => true,
            'status' => Category::STATUS_SCHEDULED,
            'published_at' => now()->addMonth(),
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-01 09:00:00', 'UTC'));
        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), $this->payload([
            'name' => 'Categoria Da Anticipare',
            'slug' => 'categoria-da-anticipare',
            'status' => Category::STATUS_PUBLISHED,
        ]));

        $fresh = $category->fresh();
        $this->assertSame(Category::STATUS_PUBLISHED, $fresh->status);
        $this->assertTrue($fresh->published_at->equalTo(now()));
        $this->assertTrue($fresh->isPubliclyVisible());

        Carbon::setTestNow();
    }

    public function test_switching_a_published_category_to_draft_clears_published_at_and_hides_it(): void
    {
        $category = Category::create([
            'name' => 'Categoria Da Ritirare',
            'slug' => 'categoria-da-ritirare',
            'is_active' => true,
            'status' => Category::STATUS_PUBLISHED,
        ]);

        $this->actingAs($this->editor())->put(route('admin.categories.update', $category), $this->payload([
            'name' => 'Categoria Da Ritirare',
            'slug' => 'categoria-da-ritirare',
            'status' => Category::STATUS_DRAFT,
        ]));

        $fresh = $category->fresh();
        $this->assertSame(Category::STATUS_DRAFT, $fresh->status);
        $this->assertNull($fresh->published_at);
        $this->assertFalse($fresh->isPubliclyVisible());
    }
}
