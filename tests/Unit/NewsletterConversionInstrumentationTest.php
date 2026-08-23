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

    public function test_subscribe_error_accepts_only_the_explicit_non_pii_allowlist(): void
    {
        $service = new NewsletterConversionInstrumentation;

        foreach (NewsletterConversionInstrumentation::ERROR_CLASSES as $errorClass) {
            $this->assertSame($errorClass, $service->event('newsletter_subscribe_error', 'popup', $errorClass)['error_class']);
        }

        $this->expectException(InvalidArgumentException::class);
        $service->event('newsletter_subscribe_error', 'popup', 'database_timeout');
    }

    public function test_email_shaped_error_class_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NewsletterConversionInstrumentation)->event('newsletter_subscribe_error', 'popup', 'user@example.com');
    }

    public function test_error_class_cannot_be_attached_to_non_error_events(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NewsletterConversionInstrumentation)->event('newsletter_cta_view', 'popup', 'validation');
    }

    public function test_subscribe_error_requires_an_error_class(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NewsletterConversionInstrumentation)->event('newsletter_subscribe_error', 'popup');
    }
}
