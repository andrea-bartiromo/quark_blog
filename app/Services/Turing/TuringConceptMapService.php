<?php

namespace App\Services\Turing;

class TuringConceptMapService
{
    /**
     * Cantiere 59 (programma "100 cantieri Kairus").
     *
     * Trascrizione letterale della tabella "4. Mappa dei contenuti" in
     * docs/00_Governance/Architettura_Editoriale_v1.0.docx (versione 1.0,
     * 29 luglio 2026, §4) — un vero blueprint editoriale già redatto da
     * un umano sui cinque audit tecnico-editoriali dello Speciale, non
     * una tassonomia inventata qui. Ogni riga: argomento, capitolo
     * principale dove va sviluppato, capitoli di richiamo (dove il tema
     * può essere evocato senza essere ripetuto, ciascuno con il proprio
     * qualificatore così come scritto nella fonte — "teaser"/"cenno"/
     * "richiamo"/"fondamento" non sono sinonimi intercambiabili, indicano
     * un trattamento editoriale diverso), e il livello di approfondimento
     * rilevato dagli audit alla data del documento.
     *
     * 'livello_approfondimento' è una fotografia editoriale del §4 così
     * come redatto il 29 luglio 2026 — non viene mai re-verificata
     * automaticamente contro il testo attuale dei capitoli (lo Speciale
     * resta comunque non pubblico, dietro `config('turing.chapters_public')`,
     * quindi nessuna revisione dei capitoli risulta ancora intervenuta da
     * allora). Un editor che aggiorna un capitolo deve aggiornare anche
     * questa riga a mano: non esiste (e questo cantiere non introduce)
     * alcun meccanismo automatico che dedurrebbe il livello di
     * approfondimento dal testo pubblicato.
     *
     * @return list<array{argomento: string, capitolo_principale: string, richiami: list<array{capitolo: string, qualificatore: string}>, livello_approfondimento: string}>
     */
    public static function concepts(): array
    {
        return [
            ['argomento' => 'Enigma (la macchina)', 'capitolo_principale' => 'enigma', 'richiami' => [['capitolo' => 'legacy', 'qualificatore' => 'teaser']], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Bletchley Park', 'capitolo_principale' => 'enigma', 'richiami' => [['capitolo' => 'legacy', 'qualificatore' => 'teaser']], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Macchina universale', 'capitolo_principale' => 'computation', 'richiami' => [['capitolo' => 'ai', 'qualificatore' => 'cenno'], ['capitolo' => 'legacy', 'qualificatore' => 'teaser']], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Computabilità', 'capitolo_principale' => 'computation', 'richiami' => [], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Algoritmo / Decidibilità', 'capitolo_principale' => 'computation', 'richiami' => [], 'livello_approfondimento' => 'Da formalizzare come concetti a sé (oggi solo impliciti)'],
            ['argomento' => "Problema dell'arresto", 'capitolo_principale' => 'computation', 'richiami' => [], 'livello_approfondimento' => 'Presente, da ampliare con un paragrafo dedicato'],
            ['argomento' => 'Tesi di Church-Turing', 'capitolo_principale' => 'computation', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Test di Turing (struttura)', 'capitolo_principale' => 'intelligence', 'richiami' => [['capitolo' => 'legacy', 'qualificatore' => 'teaser'], ['capitolo' => 'ai', 'qualificatore' => 'richiamo']], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Computing Machinery and Intelligence (1950)', 'capitolo_principale' => 'intelligence', 'richiami' => [['capitolo' => 'legacy', 'qualificatore' => 'teaser']], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Obiezioni di Turing', 'capitolo_principale' => 'intelligence', 'richiami' => [], 'livello_approfondimento' => 'Presente solo come elenco, da sviluppare'],
            ['argomento' => 'Rapporto imitazione / comprensione', 'capitolo_principale' => 'intelligence', 'richiami' => [['capitolo' => 'ai', 'qualificatore' => 'richiamo']], 'livello_approfondimento' => 'Approfondito in Intelligence, da riprendere senza ripetere in AI'],
            ['argomento' => 'IA simbolica / sistemi esperti', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Connessionismo', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Reti neurali', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Solo accennato, da sviluppare'],
            ['argomento' => 'Deep Learning', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Dati e potenza computazionale', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Transformer', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Solo nominato, da spiegare'],
            ['argomento' => 'LLM', 'capitolo_principale' => 'ai', 'richiami' => [['capitolo' => 'intelligence', 'qualificatore' => 'richiamo']], 'livello_approfondimento' => 'Solo nominato, da spiegare'],
            ['argomento' => 'Bias', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Solo accennato, da sviluppare'],
            ['argomento' => 'Allucinazioni', 'capitolo_principale' => 'ai', 'richiami' => [], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Capacità vs comprensione vs coscienza', 'capitolo_principale' => 'ai', 'richiami' => [['capitolo' => 'intelligence', 'qualificatore' => 'fondamento']], 'livello_approfondimento' => 'Assente, da colmare'],
            ['argomento' => 'Continuità/discontinuità Turing-oggi', 'capitolo_principale' => 'ai', 'richiami' => [['capitolo' => 'intelligence', 'qualificatore' => 'richiamo']], 'livello_approfondimento' => 'Solo accennato, da sviluppare'],
            ['argomento' => 'Persecuzione (1952)', 'capitolo_principale' => 'legacy', 'richiami' => [], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Grazia reale (2013)', 'capitolo_principale' => 'legacy', 'richiami' => [], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Turing Law (2017)', 'capitolo_principale' => 'legacy', 'richiami' => [], 'livello_approfondimento' => 'Approfondito'],
            ['argomento' => 'Eredità culturale', 'capitolo_principale' => 'legacy', 'richiami' => [], 'livello_approfondimento' => 'Presente, da arricchire con esempi concreti'],
        ];
    }

    /**
     * Gli stessi concetti raggruppati per capitolo principale, nello
     * stesso ordine di TuringNavigationMetricsService::CHAPTERS (esclusa
     * 'hub', che non ospita concetti propri nel §4 della fonte).
     *
     * @return array<string, list<array{argomento: string, capitolo_principale: string, richiami: list<array{capitolo: string, qualificatore: string}>, livello_approfondimento: string}>>
     */
    public static function conceptsByChapter(): array
    {
        $concepts = self::concepts();

        return collect(array_filter(TuringNavigationMetricsService::CHAPTERS, fn (string $chapter) => $chapter !== 'hub'))
            ->mapWithKeys(fn (string $chapter) => [
                $chapter => array_values(array_filter($concepts, fn (array $c) => $c['capitolo_principale'] === $chapter)),
            ])
            ->all();
    }
}
