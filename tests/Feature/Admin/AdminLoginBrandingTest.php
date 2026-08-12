<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class AdminLoginBrandingTest extends TestCase
{
    public function test_admin_login_uses_kairus_branding(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Kairus.', false);
        $response->assertDontSee('Il <em>Lab</em>oratorio', false);
    }
}
