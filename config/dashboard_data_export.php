<?php

return [
    'schema_version' => '1.0.0',
    'timezone' => 'Europe/Rome',
    'max_range_days' => 366,
    'max_rows_per_dataset' => 5000,
    // Segmenti inferiori a cinque osservazioni non sono abbastanza robusti
    // per analisi esterne e possono facilitare reidentificazioni indirette.
    'minimum_sample_size' => 5,
    'temporary_file_ttl_minutes' => 60,
    'rate_limit' => '6,1',
];
