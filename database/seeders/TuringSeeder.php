<?php

namespace Database\Seeders;

use App\Models\SpecialPage;
use Illuminate\Database\Seeder;

class TuringSeeder extends Seeder
{
    public function run(): void
    {
        // firstOrCreate su 'slug' inizializza la pagina soltanto se non esiste.
        // Le esecuzioni successive non duplicano la riga e non sovrascrivono
        // eventuali contenuti modificati dagli amministratori tramite il CMS.
        SpecialPage::firstOrCreate(
            ['slug' => 'turing'],
            [
                'title' => 'Alan Turing',
                'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
                'is_active' => true,
                'content' => [
                    'hero' => [
                        'kicker' => 'Kairus Special Project',
                        'title' => 'Alan Turing',
                        'lead' => 'Una mente che attraversa guerra, matematica, computer e intelligenza artificiale. Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.',
                        'primary_label' => 'Esplora Enigma',
                        'secondary_label' => 'Vai all’IA moderna',
                        'portrait_title' => 'Alan Mathison Turing',
                        'portrait_text' => '1912–1954 · Matematico, logico, pioniere dell’informatica',
                        'portrait_initials' => 'AT',
                        'portrait_years' => '1912 / 1954',
                        'terminal_title' => 'TURING ARCHIVE',
                        'terminal_lines' => [
                            'ENIGMA SIGNAL FOUND',
                            'MACHINE INTELLIGENCE: ACTIVE',
                            'QUESTION: CAN MACHINES THINK?',
                            'STATUS: STILL OPEN',
                        ],
                        // Stesso valore del fallback in TuringPageController::index():
                        // seedarlo esplicitamente non cambia nulla lato pubblico, ma
                        // popola il campo reale nell'editor admin invece di lasciarlo vuoto.
                        'background_image' => 'turing/hero/turing-hero.webp',
                        'portrait_image' => null,
                    ],

                    // Box Turing nella homepage (HomeController::turingHomeTeaser()).
                    // background_image resta null di proposito: se valorizzato,
                    // sostituisce il gradiente di sfondo attuale con un'immagine,
                    // un cambiamento visibile che non è mai stato il comportamento
                    // di default e che questo seeder non deve introdurre.
                    'home_teaser' => [
                        'kicker' => 'Special Project',
                        'title' => 'Alan Turing: l’uomo che ha decifrato il futuro.',
                        'text' => 'Una nuova area speciale di Kairus dedicata a Enigma, alla nascita del computer, al Test di Turing e al legame con l’intelligenza artificiale moderna.',
                        'cta_label' => 'Entra nella Turing Experience',
                        'terminal_title' => 'TURING ARCHIVE',
                        'terminal_lines' => [
                            'ENIGMA SIGNAL FOUND',
                            'MACHINE INTELLIGENCE: ACTIVE',
                            'QUESTION: CAN MACHINES THINK?',
                            'STATUS: STILL OPEN',
                        ],
                        'background_image' => null,
                    ],

                    'intro' => [
                        'kicker' => 'Il filo rosso',
                        'title' => 'Dalla crittografia alla coscienza artificiale',
                        'text' => 'Questa area speciale racconta Turing come ponte tra tre mondi: il segreto militare, la nascita del computer e la domanda più difficile sull’intelligenza delle macchine.',
                        'background_image' => 'turing/hero/turing-intro.webp',
                    ],

                    // Stessi tre percorsi di TuringPageController::defaultRouteCards(),
                    // con l'url reale /turing/ai (non /turing/ia, corretto da PR-T1) e
                    // /turing/legacy per la terza card (non più null).
                    'cards' => [
                        [
                            'label' => '01 · Bletchley Park',
                            'title' => 'La guerra di Enigma',
                            'text' => 'Bombe, rotori, messaggi cifrati e una guerra combattuta anche con probabilità e logica.',
                            'url' => '/turing/enigma',
                            'style' => 'enigma',
                        ],
                        [
                            'label' => '02 · Macchine intelligenti',
                            'title' => 'Dal Test di Turing agli LLM',
                            'text' => 'La domanda “le macchine possono pensare?” riletta nell’epoca dell’IA generativa.',
                            'url' => '/turing/ai',
                            'style' => 'ai',
                        ],
                        [
                            'label' => '03 · Eredità',
                            'title' => 'Il genio inquieto',
                            'text' => 'La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.',
                            'url' => '/turing/legacy',
                            'style' => 'legacy',
                        ],
                    ],

                    // Stesso testo di TuringPageController::defaultEditorialBlocks().
                    // 'image'/'background_image' sono volutamente assenti: turing/enigma.blade.php
                    // e turing/ai.blade.php leggono questi stessi campi per l'immagine hero/pannello
                    // delle rispettive pagine di approfondimento con un ordine di fallback diverso
                    // da quello dell'hub, quindi valorizzarli qui cambierebbe silenziosamente quale
                    // immagine appare su /turing/enigma e /turing/ai. Lasciandoli assenti, sia l'hub
                    // (via sectionImageFallbacks/sectionBackgroundFallbacks) sia le due pagine di
                    // approfondimento continuano a risolvere esattamente le stesse immagini di oggi.
                    'editorial_blocks' => [
                        [
                            'enabled' => true,
                            'key' => 'enigma',
                            'layout' => 'image_left',
                            'kicker' => 'Enigma',
                            'title' => 'La guerra dei codici: Enigma e Bletchley Park',
                            'text' => 'Nel cuore di Bletchley Park, matematici, linguisti, ingegneri e operatori lavorarono insieme per leggere comunicazioni cifrate tedesche. Turing contribuì in modo decisivo alla progettazione delle Bombe britanniche, macchine elettromeccaniche usate per restringere lo spazio delle configurazioni possibili. La storia di Enigma non è il gesto isolato di un genio, ma un laboratorio collettivo di crittoanalisi, probabilità e organizzazione tecnica sotto pressione.',
                            'link_label' => 'Esplora Enigma',
                            'link_url' => '/turing/enigma',
                        ],
                        [
                            'enabled' => true,
                            'key' => 'macchina-universale',
                            'layout' => 'image_right',
                            'kicker' => 'Computazione',
                            'title' => 'La macchina universale e l’idea moderna di programma',
                            'text' => 'Nel 1936 Turing descrisse un modello astratto capace di manipolare simboli secondo regole definite. Quella macchina teorica rese più precisa la nozione di algoritmo e mostrò che programma e dati potevano essere trattati nello stesso linguaggio formale. Non era un computer nel senso materiale del termine, ma una grammatica concettuale del calcolo che avrebbe influenzato profondamente l’informatica, la logica matematica e il modo in cui pensiamo le macchine programmabili.',
                            'link_label' => 'Scopri la macchina universale',
                            'link_url' => '/turing/computation',
                        ],
                        [
                            'enabled' => true,
                            'key' => 'test-turing',
                            'layout' => 'image_left',
                            'kicker' => 'Intelligenza',
                            'title' => 'Il gioco dell’imitazione e la domanda sulle macchine pensanti',
                            'text' => 'Nel saggio del 1950 Computing Machinery and Intelligence, Turing evitò una definizione rigida di pensiero e propose di osservare il comportamento in una conversazione mediata. Il cosiddetto Test di Turing non è una misura definitiva della coscienza, ma un esperimento concettuale: sposta l’attenzione da ciò che una macchina “è” a ciò che riesce a fare in un contesto comunicativo controllato.',
                            'link_label' => 'Leggi la domanda',
                            'link_url' => '/turing/intelligence',
                        ],
                        [
                            'enabled' => true,
                            'key' => 'ai-moderna',
                            'layout' => 'image_right',
                            'kicker' => 'IA moderna',
                            'title' => 'Dai modelli linguistici alla nuova attualità di Turing',
                            'text' => 'I sistemi contemporanei di apprendimento automatico, inclusi i modelli linguistici, rendono di nuovo attuale la domanda posta da Turing, ma non la chiudono. Generare risposte plausibili non equivale automaticamente a pensare o comprendere. Il confronto con il Test di Turing resta utile se usato con precisione: come lente storica per discutere comportamento, simulazione, automazione e limiti delle macchine intelligenti.',
                            'link_label' => 'Vai all’IA moderna',
                            'link_url' => '/turing/ai',
                        ],
                    ],

                    'why' => [
                        'kicker' => 'Perché conta ancora',
                        'title' => 'Ogni volta che parliamo di algoritmo, torniamo a Turing.',
                        'text' => 'La sua intuizione più potente non fu soltanto costruire macchine, ma immaginare un linguaggio universale per descrivere il calcolo. Oggi quella visione vive nei computer, nella crittografia, nei modelli linguistici e nelle domande etiche sull’automazione.',
                        'background_image' => 'turing/backgrounds/turing-universal-machine-background.webp',
                        'items' => [
                            ['title' => 'Calcolo', 'text' => 'la macchina universale'],
                            ['title' => 'Sicurezza', 'text' => 'codici, cifrari, decrittazione'],
                            ['title' => 'IA', 'text' => 'imitazione, linguaggio, giudizio'],
                        ],
                    ],

                    // Il campo 'timeline' resta volutamente assente. Un override CMS non
                    // vuoto qui disattiverebbe la Timeline a capitoli temporali (Decision
                    // #003 del Project Book) e farebbe tornare TuringPageController al
                    // rendering piatto precedente — una regressione visibile che questo
                    // seeder deve evitare. Con il campo assente, TuringPageController
                    // continua a usare defaultTimeline()/defaultTimelineChapters().

                    'final' => [
                        'kicker' => 'Prossima lettura',
                        'title' => 'Scegli da dove iniziare',
                        'text' => 'Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?',
                        'background_image' => 'turing/backgrounds/turing-ai-background.webp',
                    ],
                ],
            ]
        );
    }
}
