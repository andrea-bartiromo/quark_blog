<?php

namespace Tests\Unit;

use App\Models\SocialPublication;
use PHPUnit\Framework\TestCase;

class SocialPublicationPresentationTest extends TestCase
{
    public function test_it_exposes_only_https_remote_urls(): void
    {
        $publication = new SocialPublication(['remote_url' => 'https://social.example/post/1']);
        $this->assertSame('https://social.example/post/1', $publication->safeRemoteUrl());

        $publication->remote_url = 'javascript:alert(1)';
        $this->assertNull($publication->safeRemoteUrl());
    }

    public function test_it_redacts_likely_secrets_and_bounds_errors(): void
    {
        $publication = new SocialPublication(['last_error_message' => 'Request failed token=super-secret authorization:Bearer123']);

        $error = $publication->sanitizedError();
        $this->assertStringNotContainsString('super-secret', $error);
        $this->assertStringNotContainsString('Bearer123', $error);
        $this->assertLessThanOrEqual(280, mb_strlen($error));
    }
}
