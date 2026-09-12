<?php

return [
    'v2' => [
        // Empty means auto-discover: prefer mariadb-dump, then compatible mysqldump.
        'binary' => env('DB_BACKUP_BINARY', ''),
        'directory' => env('DB_BACKUP_DIRECTORY', storage_path('backups/mariadb')),
        // Production retention is deliberately opt-in: no repository default guesses policy.
        'retention' => env('DB_BACKUP_RETENTION'),
        // Cantiere 19 (programma 100-cantieri Kairus): soglia di età oltre la
        // quale deploy:verify-database-backup segnala il backup più recente
        // come stantio. Backup V2 è manuale/opt-in (nessuno scheduler lo
        // esegue automaticamente), quindi nessuna cadenza può essere
        // presunta qui: come la retention, deliberatamente opt-in, nessun
        // default di repository indovina una policy operativa.
        'max_age_hours' => env('DB_BACKUP_MAX_AGE_HOURS'),
        // Dedicated cross-process lock store; never inherit a process-local test/application store.
        'lock_store' => env('DB_BACKUP_LOCK_STORE', 'file'),
        'lock_seconds' => (int) env('DB_BACKUP_LOCK_SECONDS', 900),
        'revision_file' => env('DB_BACKUP_REVISION_FILE', base_path('REVISION')),
    ],
];
