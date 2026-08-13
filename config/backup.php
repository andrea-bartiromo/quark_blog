<?php

return [
    'v2' => [
        // Empty means auto-discover: prefer mariadb-dump, then compatible mysqldump.
        'binary' => env('DB_BACKUP_BINARY', ''),
        'directory' => env('DB_BACKUP_DIRECTORY', storage_path('backups/mariadb')),
        // Production retention is deliberately opt-in: no repository default guesses policy.
        'retention' => env('DB_BACKUP_RETENTION'),
        'lock_seconds' => (int) env('DB_BACKUP_LOCK_SECONDS', 900),
        'revision_file' => env('DB_BACKUP_REVISION_FILE', base_path('REVISION')),
    ],
];
