<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 53 (programma "100 cantieri Kairus", dipende dai Cantieri 49-50).
 *
 * Pagina di sola lettura: mostra i segnali già calcolati da
 * CategoryHubCtrBenchmarkService, non li imposta — stessa disciplina già
 * verificata per Admin\SecondReadAnalyticsController.
 */
class CategoryHubCtrBenchmarkControllerTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'editor'])->save();

        return $user;
    }

    private function author(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => 'author'])->save();

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.category-hub-ctr-benchmark'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.category-hub-ctr-benchmark'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_view_the_empty_state(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.category-hub-ctr-benchmark'));

        $response->assertOk();
        $response->assertSeeText('Benchmark CTR hub categorie');
    }

    public function test_editor_sees_a_publicly_visible_category_in_the_breakdown(): void
    {
        Category::create(['name' => 'Salute CTR Test', 'slug' => 'salute-ctr-test', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->editor())->get(route('admin.category-hub-ctr-benchmark'));

        $response->assertOk();
        $response->assertSeeText('Salute CTR Test');
    }

    public function test_the_period_filter_is_accepted(): void
    {
        $response = $this->actingAs($this->editor())
            ->get(route('admin.category-hub-ctr-benchmark', ['periodo' => '7']));

        $response->assertOk();
    }
}
