<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAdsModalAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private function editor(): User
    {
        return User::factory()->create(['role' => 'editor']);
    }

    private function adsPageHtml(): string
    {
        return $this->actingAs($this->editor())
            ->get(route('admin.ads'))
            ->assertOk()
            ->getContent();
    }

    public function test_admin_ads_modal_exposes_accessible_dialog_semantics(): void
    {
        $html = $this->adsPageHtml();

        $this->assertStringContainsString('id="modal-new"', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="modal-new-title"', $html);
        $this->assertStringContainsString('id="modal-new-title"', $html);

        $this->assertStringContainsString('id="modal-new-open"', $html);
        $this->assertStringContainsString('aria-haspopup="dialog"', $html);
        $this->assertStringContainsString('aria-controls="modal-new"', $html);
    }

    public function test_admin_ads_modal_supports_keyboard_close_and_focus_management(): void
    {
        $html = $this->adsPageHtml();

        $this->assertStringContainsString("event.key === 'Escape'", $html);
        $this->assertStringContainsString("event.key !== 'Tab'", $html);
        $this->assertStringContainsString(
            'newAdModalLastFocused = document.activeElement',
            $html
        );
        $this->assertStringContainsString(
            'newAdModalLastFocused.focus()',
            $html
        );
        $this->assertStringContainsString('firstFocusable.focus()', $html);
        $this->assertStringContainsString('last.focus()', $html);
        $this->assertStringContainsString('first.focus()', $html);
    }

    public function test_admin_ads_modal_no_longer_uses_legacy_inline_display_handlers(): void
    {
        $html = $this->adsPageHtml();

        $this->assertStringNotContainsString(
            "document.getElementById('modal-new').style.display='flex'",
            $html
        );

        $this->assertStringNotContainsString(
            "document.getElementById('modal-new').style.display='none'",
            $html
        );
    }
}
