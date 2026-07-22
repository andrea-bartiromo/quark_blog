<?php

/**
 * Il Laboratorio — Rivista italiana di divulgazione scientifica
 *
 * @author    Andrea Bartiromo <redazione@illaboratorio.it>
 * @copyright 2025 Andrea Bartiromo. Tutti i diritti riservati.
 * @license   Proprietario — tutti i diritti riservati
 *
 * @link      https://www.illaboratorio.it
 */

namespace App\Console\Commands;

use App\Models\NewsSuggestion;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * IL LABORATORIO — FetchNewsAndGenerateDrafts
 *
 * Raccoglie notizie da feed RSS verificati, genera bozze con Claude AI
 * rispettando i criteri editoriali della testata: solo fatti verificabili,
 * fonti primarie citate, nessun dato inventato.
 *
 * Esecuzione manuale:
 *   php artisan news:fetch [--dry-run] [--category=]
 *
 * Schedulazione automatica (routes/console.php):
 *   Lunedì e giovedì alle 9:00
 */
class FetchNewsAndGenerateDrafts extends Command
{
    protected $signature = 'news:fetch {--dry-run : Mostra le notizie trovate senza salvare} {--category= : Filtra per categoria}';

    protected $description = 'Raccoglie notizie scientifiche da feed RSS verificati e genera bozze con AI nel rispetto dei criteri editoriali';

    /**
     * Feed RSS di fonti istituzionali e testate affidabili.
     * Ogni fonte è stata selezionata per affidabilità e rilevanza scientifica.
     */
    private array $feeds = [
        'intelligenza-artificiale' => [
            'https://www.ansa.it/sito/notizie/tecnologia/rss.xml',         // ANSA Tecnologia
            'https://www.wired.it/feed/',                                   // Wired Italia
            'https://www.corriere.it/tecnologia/rss.xml',                  // Corriere Tecnologia
        ],
        'energia' => [
            'https://www.qualenergia.it/feed/',                            // QualEnergia
            'https://www.rinnovabili.it/feed/',                            // Rinnovabili.it
            'https://www.ansa.it/canale_ambiente/rss.xml',                 // ANSA Ambiente
        ],
        'salute' => [
            'https://www.salute.gov.it/portale/news/rss.xml',              // Ministero Salute
            'https://www.quotidianosanita.it/rss/',                        // Quotidiano Sanità
            'https://www.airc.it/feed/',                                   // AIRC
        ],
        'spazio' => [
            'https://www.media.inaf.it/feed/',                             // INAF Media
            'https://www.asi.it/feed/',                                    // ASI
        ],
        'societa' => [
            'https://www.agi.it/tecnologia/rss.xml',                       // AGI Tecnologia
            'https://www.ilsole24ore.com/rss/home.xml',                   // Il Sole 24 Ore
        ],
        'ambiente' => [
            'https://www.greenreport.it/feed/',                            // GreenReport
            'https://www.isprambiente.gov.it/it/rss/news.xml',            // ISPRA
        ],
    ];

    /**
     * Parole chiave per filtrare notizie rilevanti per la divulgazione
     * scientifica e tecnologica italiana.
     */
    private array $keywords = [
        'ricerca', 'scienza', 'tecnologia', 'innovazione', 'università',
        'laboratorio', 'studio', 'scoperta', 'intelligenza artificiale',
        'robotica', 'biotech', 'startup', 'rinnovabili', 'sostenibile',
        'salute', 'medico', 'clinico', 'satellite', 'spazio', 'energia',
        'italiano', 'italiana', 'Italia', 'CNR', 'INFN', 'ASI', 'INAF',
        'AIRC', 'ISS', 'Terna', 'GSE', 'ISPRA', 'AIFA',
    ];

    public function handle(): int
    {
        $this->info('');
        $this->info('🔬 IL LABORATORIO — Raccolta notizie con verifica editoriale');
        $this->info('═══════════════════════════════════════════════════════════');

        $categoryFilter = $this->option('category');
        $isDryRun = $this->option('dry-run');
        $totalSaved = 0;
        $totalSkipped = 0;

        $feeds = $categoryFilter
            ? [$categoryFilter => ($this->feeds[$categoryFilter] ?? [])]
            : $this->feeds;

        foreach ($feeds as $category => $feedUrls) {
            $this->info('');
            $this->info("📂 Categoria: <fg=yellow>{$category}</>");

            foreach ($feedUrls as $feedUrl) {
                $items = $this->fetchRssFeed($feedUrl);

                if (empty($items)) {
                    $this->line("   ⚠ Feed non raggiungibile: {$feedUrl}");

                    continue;
                }

                $source = parse_url($feedUrl, PHP_URL_HOST);
                $this->line('   📡 '.count($items)." notizie da: {$source}");

                foreach ($items as $item) {
                    if (! $this->isRelevant($item)) {
                        $totalSkipped++;

                        continue;
                    }

                    if (NewsSuggestion::where('source_url', $item['url'])->exists()) {
                        continue;
                    }

                    $this->line('   ✓ Trovata: <fg=green>'.Str::limit($item['title'], 70).'</>');

                    if (! $isDryRun) {
                        $draft = $this->generateDraftWithAI($item, $category, $source);

                        NewsSuggestion::create([
                            'source_title' => $item['title'],
                            'source_url' => $item['url'],
                            'source_name' => $source,
                            'source_excerpt' => $item['excerpt'] ?? null,
                            'category' => $category,
                            'generated_title' => $draft['title'] ?? null,
                            'generated_excerpt' => $draft['excerpt'] ?? null,
                            'generated_body' => $draft['body'] ?? null,
                            'status' => 'pending',
                            'fetched_at' => now(),
                        ]);

                        $totalSaved++;
                    }
                }
            }
        }

        $this->info('');
        $this->info('════════════════════════════════════════════════════════');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN — nessun dato salvato.');
        } else {
            $this->info("✅ Bozze generate: <fg=green>{$totalSaved}</>");
            $this->info("⏭  Notizie non pertinenti: {$totalSkipped}");

            if ($totalSaved > 0) {
                $this->info('');
                $this->info('⚠️  IMPORTANTE: Le bozze generate richiedono verifica editoriale');
                $this->info('   PRIMA di pubblicare:');
                $this->info('   1. Apri la fonte originale e verifica i dati chiave');
                $this->info('   2. Controlla che nessun nome, istituto o dato sia stato inventato dall\'AI');
                $this->info('   3. Aggiungi le fonti primarie nel campo apposito');
                $this->info('   4. Solo dopo contrassegna come "Verificato" nel pannello');
                $this->info('');
                $this->info('   Pannello suggerimenti: '.config('app.url').'/admin/suggerimenti');
                $this->info('   Pannello verifica:     '.config('app.url').'/admin/verifica');
            }
        }

        return Command::SUCCESS;
    }

    private function fetchRssFeed(string $url): array
    {
        $items = [];

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'user_agent' => 'Il Laboratorio RSS Reader/1.0',
                ],
            ]);

            $xmlContent = @file_get_contents($url, false, $context);
            if (! $xmlContent) {
                return [];
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            libxml_clear_errors();
            if (! $xml) {
                return [];
            }

            $entries = $xml->channel->item ?? $xml->entry ?? [];

            foreach ($entries as $entry) {
                $title = trim((string) ($entry->title ?? ''));
                $link = trim((string) ($entry->link ?? $entry->guid ?? ''));
                $desc = trim(strip_tags((string) ($entry->description ?? $entry->summary ?? '')));
                $pubDate = (string) ($entry->pubDate ?? $entry->published ?? '');

                if (! $title || ! $link) {
                    continue;
                }
                if ($pubDate && strtotime($pubDate) < strtotime('-7 days')) {
                    continue;
                }

                $items[] = [
                    'title' => $title,
                    'url' => $link,
                    'excerpt' => Str::limit($desc, 500),
                    'date' => $pubDate,
                ];
            }
        } catch (\Exception $e) {
            // Feed non raggiungibile
        }

        return $items;
    }

    private function isRelevant(array $item): bool
    {
        $text = strtolower($item['title'].' '.($item['excerpt'] ?? ''));
        foreach ($this->keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Genera una bozza di articolo usando Claude API nel rispetto
     * dei criteri editoriali de Il Laboratorio:
     *
     * - Solo fatti verificabili con fonte identificabile
     * - Nessun nome di persona inventato
     * - Nessun dato senza fonte
     * - Struttura classica testata online
     * - La bozza è un PUNTO DI PARTENZA che richiede verifica
     */
    private function generateDraftWithAI(array $item, string $category, string $sourceName): array
    {
        $apiKey = config('services.anthropic.key');

        if (! $apiKey) {
            $this->warn('   ⚠ ANTHROPIC_API_KEY non configurata.');

            return [];
        }

        $catLabels = config('laboratorio.categories');
        $catLabel = $catLabels[$category] ?? $category;

        $prompt = <<<PROMPT
Sei il caporedattore de "Il Laboratorio", una rivista italiana di divulgazione scientifica e tecnologica con rigorosi standard editoriali. Stai scrivendo la prima bozza di un articolo da una notizia ricevuta da una fonte esterna.

REGOLE EDITORIALI FONDAMENTALI (non derogabili):
1. Cita SOLO fatti verificabili con fonte identificabile. Se la notizia originale non fornisce un dato preciso, non inventarlo.
2. Non inventare MAI nomi di persone, ricercatori, istituzioni o aziende non presenti nella notizia originale.
3. Non aggiungere dati numerici (percentuali, cifre, date) che non siano esplicitamente nella notizia originale.
4. Usa il condizionale per fatti non ancora confermati ("potrebbe", "si prevede").
5. Indica sempre da dove proviene l'informazione ("secondo [fonte]", "come comunicato da [istituzione]").
6. Se la notizia è incompleta, dillo esplicitamente invece di inventare dettagli.

STRUTTURA ARTICOLO (testata online):
- TITOLO: preciso e specifico, senza sensazionalismo. Contiene il fatto principale.
- SOMMARIO: 1-2 frasi con i dati chiave. Max 200 caratteri.
- LEAD (1° paragrafo): chi, cosa, quando, dove, perché. Solo dati verificabili.
- CORPO (3-5 paragrafi con sottotitoli in grassetto): contesto, implicazioni, prospettive.
- AVVERTENZA REDAZIONALE (ultimo elemento): indica chiaramente cosa deve essere verificato dal redattore prima della pubblicazione.
- FONTI CITATE: elenco delle fonti usate per generare la bozza.

STILE:
- Linguaggio preciso ma accessibile al pubblico colto non specializzato.
- Paragrafi brevi (3-4 righe). Tono autorevole, mai sensazionalistico.
- Contestualizza sempre la notizia nel quadro italiano ed europeo.

NOTIZIA DA ELABORARE:
Titolo fonte: {$item['title']}
URL fonte originale: {$item['url']}
Testata: {$sourceName}
Categoria: {$catLabel}
Estratto disponibile: {$item['excerpt']}

RISPOSTA RICHIESTA:
Rispondi SOLO con un oggetto JSON valido con questa struttura (nessun testo prima o dopo):
{
  "title": "Titolo preciso (max 90 caratteri)",
  "excerpt": "Sommario breve (max 200 caratteri)",
  "body": "Corpo completo dell'articolo (700-1000 parole, paragrafi separati da \\n\\n, sottotitoli tra **). L'ultimo paragrafo deve iniziare con ⚠ NOTA REDAZIONALE: e indicare cosa il giornalista deve verificare sulla fonte primaria prima di pubblicare.",
  "sources_used": "Fonti usate per generare questa bozza (titolo notizia + URL)"
}
PROMPT;

        try {
            $response = $this->callAnthropicAPI($prompt, $apiKey);
            if (! $response) {
                return [];
            }

            $json = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                preg_match('/\{.*\}/s', $response, $matches);
                $json = $matches ? json_decode($matches[0], true) : null;
            }

            return $json ?? [];

        } catch (\Exception $e) {
            $this->warn('   ⚠ Errore AI: '.$e->getMessage());

            return [];
        }
    }

    private function callAnthropicAPI(string $prompt, string $apiKey): ?string
    {
        $payload = json_encode([
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 2000,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", [
                    'Content-Type: application/json',
                    'x-api-key: '.$apiKey,
                    'anthropic-version: 2023-06-01',
                ]),
                'content' => $payload,
                'timeout' => 30,
            ],
        ]);

        $result = @file_get_contents('https://api.anthropic.com/v1/messages', false, $context);
        if (! $result) {
            return null;
        }

        $data = json_decode($result, true);

        return $data['content'][0]['text'] ?? null;
    }
}
