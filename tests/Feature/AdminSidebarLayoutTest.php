<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminSidebarLayoutTest extends TestCase
{
    public function test_admin_layout_exposes_accessible_mobile_sidebar_toggle(): void
    {
        $this->actingAs(new User([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]));

        $html = view('layouts.admin')->render();

        $this->assertStringContainsString('admin-has-sidebar-toggle', $html);
        $this->assertStringContainsString('data-admin-sidebar-toggle', $html);
        $this->assertStringContainsString('aria-controls="admin-sidebar"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('id="admin-sidebar"', $html);
        $this->assertStringContainsString('data-admin-sidebar-overlay', $html);
        $this->assertStringContainsString("event.key === 'Escape'", $html);
    }

    public function test_admin_layout_keeps_sidebar_navigation_available(): void
    {
        $this->actingAs(new User([
            'name' => 'Admin Test',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]));

        $html = view('layouts.admin')->render();

        $this->assertStringContainsString('Navigazione amministrazione', $html);
        $this->assertStringContainsString('Dashboard', $html);
        $this->assertStringContainsString('Articoli', $html);
        $this->assertStringContainsString('Categorie', $html);
        $this->assertStringContainsString('Statistiche', $html);
    }

    public function test_admin_css_keeps_desktop_layout_and_scopes_mobile_spacing(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));

        $this->assertStringContainsString(".admin-main {\n  margin-left: 240px;", $css);
        $this->assertStringContainsString('@media (max-width: 900px)', $css);
        $this->assertStringContainsString('.admin-sidebar.open', $css);
        $this->assertStringContainsString('.admin-sidebar-toggle', $css);
        $this->assertStringContainsString('.admin-sidebar-overlay[hidden]', $css);
        $this->assertStringContainsString(".admin-main {\n    margin-left: 0;\n    width: 100%;\n    padding: 1rem;\n  }", $css);
        $this->assertStringContainsString(".admin-has-sidebar-toggle .admin-main {\n    padding: 4.25rem 1rem 1rem;\n  }", $css);
    }

    public function test_redazione_layout_does_not_receive_admin_sidebar_toggle_spacing(): void
    {
        $html = file_get_contents(resource_path('views/layouts/redazione.blade.php'));

        $this->assertStringContainsString('class="admin-body"', $html);
        $this->assertStringNotContainsString('admin-has-sidebar-toggle', $html);
        $this->assertStringNotContainsString('data-admin-sidebar-toggle', $html);
        $this->assertStringNotContainsString('data-admin-sidebar-overlay', $html);
    }
}
