<?php

namespace Tests\Unit;

use App\Services\Newsletter\NewsletterConversionInstrumentation;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class NewsletterConversionInstrumentationTest extends TestCase
{
    public function test_payload_contains_only_closed_non_pii_fields(): void
    {
        $payload = (new NewsletterConversionInstrumentation)->event('newsletter_cta_submit', 'article_band');

        $this->assertSame([
            'event' => 'newsletter_cta_submit',
            'placement' => 'article_band',
            'error_class' => null,
        ], $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('name', $payload);
        $this->assertArrayNotHasKey('user_id', $payload);
        $this->assertArrayNotHasKey('ip', $payload);
    }

    public function test_unknown_event_or_placement_is_rejected(): void
    {
        $service = new NewsletterConversionInstrumentation;

        $this->expectException(InvalidArgumentException::class);
        $service->event('newsletter_email_captured', 'popup');
    }

    public function test_error_class_accepts_only_closed_identifier_shape(): void
    {
        $service = new NewsletterConversionInstrumentation;

        $this->assertSame('validation', $service->event('newsletter_subscribe_error', 'popup', 'validation')['error_class']);

        $this->expectException(InvalidArgumentException::class);
        $service->event('newsletter_subscribe_error', 'popup', 'user@example.com');
    }
}
