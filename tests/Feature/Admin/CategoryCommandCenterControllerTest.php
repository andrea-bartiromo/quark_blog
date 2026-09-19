<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cantiere 54 (programma "100 cantieri Kairus", dipende dal Cantiere 49).
 *
 * Pagina di sola lettura: mostra i segnali già calcolati per ciascuna
 * categoria, non li imposta — stessa disciplina già verificata per
 * Admin\EditorialOperationsDashboardController.
 */
class CategoryCommandCenterControllerTest extends TestCase
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
        $this->get(route('admin.categories.command-center'))->assertRedirect(route('login'));
    }

    public function test_author_role_cannot_reach_the_page(): void
    {
        $this->actingAs($this->author())
            ->get(route('admin.categories.command-center'))
            ->assertRedirect(route('redazione.dashboard'));
    }

    public function test_editor_can_view_the_empty_state(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.categories.command-center'));

        $response->assertOk();
        $response->assertSeeText('Command Center — Categorie');
    }

    public function test_editor_sees_every_category_by_name(): void
    {
        Category::create(['name' => 'Salute Command Center Test', 'slug' => 'salute-command-center-test', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);
        Category::create(['name' => 'Energia Command Center Test', 'slug' => 'energia-command-center-test', 'is_active' => true, 'status' => Category::STATUS_PUBLISHED]);

        $response = $this->actingAs($this->editor())->get(route('admin.categories.command-center'));

        $response->assertOk();
        $response->assertSeeText('Salute');
        $response->assertSeeText('Energia');
    }

    public function test_the_page_links_back_to_the_categories_admin_page(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.categories.command-center'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.categories').'"', false);
    }

    public function test_the_categories_admin_page_links_to_the_command_center(): void
    {
        $response = $this->actingAs($this->editor())->get(route('admin.categories'));

        $response->assertOk();
        $response->assertSee('href="'.route('admin.categories.command-center').'"', false);
    }
}
