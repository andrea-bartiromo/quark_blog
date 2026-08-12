<?php

return [
    'v2' => [
        'binary' => env('DB_BACKUP_BINARY', 'mariadb-dump'),
        'directory' => env('DB_BACKUP_DIRECTORY', storage_path('backups/mariadb')),
        // Production retention is deliberately opt-in: no repository default guesses policy.
        'retention' => env('DB_BACKUP_RETENTION'),
        'lock_seconds' => (int) env('DB_BACKUP_LOCK_SECONDS', 900),
    ],
];
