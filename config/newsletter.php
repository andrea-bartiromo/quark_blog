<?php

return [
    'reconfirmation' => [
        // Timeline di consenso: giorno 10, 20, 30; cancellazione al giorno 40.
        'initial_wait_days' => 10,
        'reminder_interval_days' => 10,
        'delete_after_last_reminder_days' => 10,
        'expires_after_days' => 10,
        'max_attempts' => 3,

        // Il valore legacy resta per il flusso admin ed evita di rompere
        // configurazioni esistenti; il processore automatico usa 10 giorni.
        'cooldown_hours' => 24,
        'manual_resend_cooldown_hours' => 24,

        // Interruttori fail-closed.
        'automation_enabled' => (bool) env('NEWSLETTER_RECONFIRMATION_AUTOMATION_ENABLED', true),
        'cleanup_enabled' => (bool) env('NEWSLETTER_RECONFIRMATION_CLEANUP_ENABLED', true),
    ],
];
