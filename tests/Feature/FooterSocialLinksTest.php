<?php

namespace Tests\Feature;

use Tests\TestCase;

class FooterSocialLinksTest extends TestCase
{
    private const LINKEDIN = 'https://www.linkedin.com/company/kairus-it/';

    private const FACEBOOK = 'https://www.facebook.com/profile.php?id=61593323495927';

    public function test_footer_renders_both_configured_https_profiles_without_secrets(): void
    {
        config(['laboratorio.social' => [
            'linkedin' => ['label' => 'LinkedIn', 'url' => self::LINKEDIN],
            'facebook' => ['label' => 'Facebook', 'url' => self::FACEBOOK],
        ]]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('Seguici')
            ->assertSee('href="'.self::LINKEDIN.'"', false)
            ->assertSee('href="'.self::FACEBOOK.'"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertSee('LinkedIn')
            ->assertSee('Facebook')
            ->assertDontSee('access_token', false)
            ->assertDontSee('api_key', false);
    }

    public function test_footer_omits_only_the_profile_without_a_valid_https_url(): void
    {
        config(['laboratorio.social' => [
            'linkedin' => ['label' => 'LinkedIn', 'url' => self::LINKEDIN],
            'facebook' => ['label' => 'Facebook', 'url' => 'http://www.facebook.com/kairus'],
        ]]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Seguici')
            ->assertSee('href="'.self::LINKEDIN.'"', false)
            ->assertDontSee('http://www.facebook.com/kairus', false)
            ->assertDontSee('>Facebook<', false);
    }

    public function test_footer_omits_the_social_section_when_no_profile_is_configured(): void
    {
        config(['laboratorio.social' => [
            'linkedin' => ['label' => 'LinkedIn', 'url' => null],
            'facebook' => ['label' => 'Facebook', 'url' => ''],
        ]]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('id="footer-social-title"', false)
            ->assertDontSee('class="footer-social"', false);
    }

    public function test_footer_rejects_non_https_and_malformed_destinations(): void
    {
        config(['laboratorio.social' => [
            'linkedin' => ['label' => 'LinkedIn', 'url' => 'javascript:alert(1)'],
            'facebook' => ['label' => 'Facebook', 'url' => 'not-a-url'],
        ]]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('javascript:', false)
            ->assertDontSee('not-a-url', false)
            ->assertDontSee('id="footer-social-title"', false);
    }
}
