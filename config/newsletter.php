<?php

return [
    'reconfirmation' => [
        // Timeline di consenso: giorno 10, 20, 30; cancellazione al giorno 40.
        'initial_wait_days' => 10,
        'reminder_interval_days' => 10,
        'delete_after_last_reminder_days' => 10,
        'expires_after_days' => 10,
        'max_attempts' => 3,

        // Reinvio manuale: al massimo una richiesta ogni 24 ore.
        'manual_resend_cooldown_hours' => 24,

        // Interruttore fail-closed del processore schedulato.
        'automation_enabled' => (bool) env('NEWSLETTER_RECONFIRMATION_AUTOMATION_ENABLED', true),
    ],
];
