<?php

namespace App\Services\Newsletter;

use InvalidArgumentException;

class NewsletterConversionInstrumentation
{
    public const EVENTS = [
        'newsletter_cta_view',
        'newsletter_cta_submit',
        'newsletter_subscribe_success',
        'newsletter_subscribe_error',
        'newsletter_popup_dismiss',
    ];

    public const PLACEMENTS = ['article_band', 'popup'];

    /**
     * Build the only analytics payload shape allowed for newsletter conversion V1.
     * The class deliberately has no persistence/provider dependency: wiring to a
     * sink happens only after analytics ownership is clear.
     *
     * @return array{event:string,placement:?string,error_class:?string}
     */
    public function event(string $event, ?string $placement = null, ?string $errorClass = null): array
    {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Unsupported newsletter conversion event.');
        }

        if ($placement !== null && ! in_array($placement, self::PLACEMENTS, true)) {
            throw new InvalidArgumentException('Unsupported newsletter CTA placement.');
        }

        if (in_array($event, ['newsletter_cta_view', 'newsletter_cta_submit', 'newsletter_popup_dismiss'], true) && $placement === null) {
            throw new InvalidArgumentException('This newsletter event requires an explicit placement.');
        }

        if ($errorClass !== null && ! preg_match('/^[a-z0-9_]{1,40}$/', $errorClass)) {
            throw new InvalidArgumentException('Newsletter error_class must be a closed, non-PII identifier.');
        }

        return [
            'event' => $event,
            'placement' => $placement,
            'error_class' => $event === 'newsletter_subscribe_error' ? $errorClass : null,
        ];
    }
}
