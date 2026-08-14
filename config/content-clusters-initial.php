<?php

return [
    [
        'slug' => 'ia-spiegata',
        'name' => 'IA spiegata',
        'pillar' => 'che-cose-un-llm-e-come-funziona-davvero',
        'articles' => [
            ['slug' => 'che-cose-un-llm-e-come-funziona-davvero', 'position' => 10, 'primary' => true],
            ['slug' => 'come-funziona-davvero-chatgpt', 'position' => 20, 'primary' => true],
            ['slug' => 'perche-lintelligenza-artificiale-allucina', 'position' => 30, 'primary' => true],
            ['slug' => 'il-test-di-turing-spiegato-davvero', 'position' => 40, 'primary' => true],
            ['slug' => 'il-test-di-turing-ha-ancora-senso-nel-2026', 'position' => 50, 'primary' => true],
            ['slug' => 'lintelligenza-artificiale-fisica', 'position' => 60, 'primary' => true],
            ['slug' => 'ai-e-diagnosi-medica', 'position' => 70, 'primary' => false],
            ['slug' => 'gpt-5-e-futuro-del-lavoro', 'position' => 80, 'primary' => false],
        ],
    ],
    [
        'slug' => 'spazio',
        'name' => 'Spazio',
        'pillar' => 'perche-il-cielo-e-nero',
        'articles' => [
            ['slug' => 'perche-il-cielo-e-nero', 'position' => 10, 'primary' => true],
            ['slug' => 'guardare-lontano-nello-spazio-guardare-indietro-nel-tempo', 'position' => 20, 'primary' => true],
            ['slug' => 'come-i-telescopi-vedono-il-passato', 'position' => 30, 'primary' => true],
            ['slug' => 'relativita-speciale', 'position' => 40, 'primary' => false],
            ['slug' => 'perche-il-sole-non-esplode', 'position' => 50, 'primary' => true],
            ['slug' => 'betelgeuse-guida', 'position' => 60, 'primary' => true],
            ['slug' => 'betelgeuse-compagna', 'position' => 70, 'primary' => false],
            ['slug' => 'satellite-italiano-clima', 'position' => 80, 'primary' => false],
        ],
    ],
    [
        'slug' => 'scienza-quotidiana',
        'name' => 'Scienza quotidiana',
        'pillar' => 'microonde',
        'articles' => [
            ['slug' => 'microonde', 'position' => 10, 'primary' => true],
            ['slug' => 'wi-fi', 'position' => 20, 'primary' => true],
            ['slug' => 'orologi-atomici', 'position' => 30, 'primary' => true],
            ['slug' => 'pelle-doca', 'position' => 40, 'primary' => true],
            ['slug' => 'odore-della-pioggia', 'position' => 50, 'primary' => true],
        ],
    ],
    [
        'slug' => 'energia-e-batterie',
        'name' => 'Energia e batterie',
        'pillar' => null,
        'articles' => [
            ['slug' => 'perche-una-batteria-si-scarica', 'position' => 10, 'primary' => true],
            ['slug' => 'batterie-al-sodio', 'position' => 20, 'primary' => true],
            ['slug' => 'dove-finisce-lelettricita-non-utilizzata', 'position' => 30, 'primary' => true],
            ['slug' => 'fotovoltaico-organico', 'position' => 40, 'primary' => false],
        ],
        'notes' => 'Pillar intentionally unset: editorial review should choose a stronger overview article before activation.',
    ],
];
