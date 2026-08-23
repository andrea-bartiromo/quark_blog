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

    public const ERROR_CLASSES = [
        'validation',
        'already_subscribed',
        'network',
        'server',
        'unknown',
    ];

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

        if ($event === 'newsletter_subscribe_error') {
            if ($errorClass === null || ! in_array($errorClass, self::ERROR_CLASSES, true)) {
                throw new InvalidArgumentException('Newsletter subscribe error requires an allowed non-PII error_class.');
            }
        } elseif ($errorClass !== null) {
            throw new InvalidArgumentException('error_class is only valid for newsletter_subscribe_error.');
        }

        return [
            'event' => $event,
            'placement' => $placement,
            'error_class' => $event === 'newsletter_subscribe_error' ? $errorClass : null,
        ];
    }
}
