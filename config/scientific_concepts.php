<?php

/*
|--------------------------------------------------------------------------
| Concetti scientifici — registro alias V1 (Internal Linking V2)
|--------------------------------------------------------------------------
|
| Piccolo insieme CONSERVATIVO di concetti multi-parola noti, usato da
| App\Services\InternalLinking\ScientificConceptMatcher per riconoscere
| una frase come un CONCETTO (non una accidentale co-occorrenza di parole
| comuni) nel testo di un articolo. Non è un'enciclopedia: ogni voce qui
| aggiunge un piccolo bonus di punteggio in ArticleLinkSuggestionService
| SOLO quando la stessa frase (o un suo alias) compare LETTERALMENTE sia
| nel testo sorgente sia nel titolo/estratto/corpo del candidato target —
| mai una equivalenza semantica implicita, mai una chiamata esterna.
|
| Ogni voce:
|   - 'canonical': la forma di riferimento, usata per confrontare se due
|     alias diversi indicano lo stesso concetto (es. "buco nero" e
|     "buchi neri" condividono lo stesso canonical);
|   - 'aliases': TUTTE le varianti riconosciute, inclusa sempre la forma
|     canonica stessa. Confronto sempre case-insensitive, sui confini di
|     parola (mai una sotto-stringa dentro una parola più lunga — vedi
|     ScientificConceptMatcher::findWordBoundaryPhrase()).
|
| Deliberatamente ASSENTI:
|   - equivalenze a singola parola generica ("stella" non diventa
|     equivalente a "stelle" come concetto broad, "tempo" da solo non è
|     un concetto) — vedi FASE 4 della missione: un termine generico
|     resta debole finché non fa parte di una locuzione specifica;
|   - alias corti tutto-maiuscolo ambigui ("IA", "AI"): la stessa classe
|     di rischio già documentata in
|     ArticleLinkSuggestionService::SHORT_ACRONYM_ALLOWLIST per "AI"
|     (collisione con "ai", preposizione articolata comunissima) si
|     applicherebbe identica a "IA" — nessuna eccezione qui, la sola
|     forma estesa "intelligenza artificiale" resta l'alias sicuro;
|   - "GPT" da solo (ambiguo fuori contesto, vedi missione) — identificatori
|     come "GPT-5" sono già token singoli gestiti dal tokenizer esistente
|     (lettere+cifre+trattino in un solo token), non richiedono una voce
|     qui: questo registro serve SOLO alle frasi multi-parola separate da
|     spazi, che il tokenizer a singola parola non può riconoscere come
|     unità.
|
| Aggiungere una nuova voce è sicuro SOLO se la frase è: specifica (non
| una co-occorrenza casuale di parole comuni), verificabile a colpo
| d'occhio, e non richiede giudizio editoriale per essere riconosciuta.
| In caso di dubbio: non aggiungerla — "meglio non inserire un link che
| inserire un link semanticamente sbagliato" (principio della missione).
*/

return [
    'concepts' => [
        ['canonical' => 'intelligenza artificiale', 'aliases' => ['intelligenza artificiale']],
        ['canonical' => 'buco nero', 'aliases' => ['buco nero', 'buchi neri']],
        ['canonical' => 'rete neurale', 'aliases' => ['rete neurale', 'reti neurali']],
        ['canonical' => 'relatività generale', 'aliases' => ['relatività generale']],
        ['canonical' => 'relatività speciale', 'aliases' => ['relatività speciale']],
        ['canonical' => 'onde gravitazionali', 'aliases' => ['onde gravitazionali', 'onda gravitazionale']],
        ['canonical' => 'materia oscura', 'aliases' => ['materia oscura']],
        ['canonical' => 'energia oscura', 'aliases' => ['energia oscura']],
        ['canonical' => 'orologio atomico', 'aliases' => ['orologio atomico', 'orologi atomici']],
        ['canonical' => 'entanglement quantistico', 'aliases' => ['entanglement quantistico']],
        ['canonical' => 'test di turing', 'aliases' => ['test di turing']],
        ['canonical' => 'fibra ottica', 'aliases' => ['fibra ottica', 'fibre ottiche']],
        ['canonical' => 'ioni di litio', 'aliases' => ['ioni di litio', 'ione di litio']],
    ],
];
