<?php

namespace Database\Seeders;

use App\Models\SpecialPage;
use Illuminate\Database\Seeder;

class TuringSeeder extends Seeder
{
    public function run(): void
    {
        SpecialPage::updateOrCreate(
            ['slug' => 'turing'],
            [
                'title' => 'Alan Turing',
                'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
                'is_active' => true,
                'content' => [
                    'hero' => [
                        'kicker' => 'Quark Special Project',
                        'title' => 'Alan Turing',
                        'lead' => 'Una mente che attraversa guerra, matematica, computer e intelligenza artificiale. Turing non è solo una biografia: è una chiave per capire il nostro presente digitale.',
                        'primary_label' => 'Esplora Enigma',
                        'secondary_label' => 'Vai all’IA moderna',
                        'portrait_title' => 'Alan Mathison Turing',
                        'portrait_text' => '1912–1954 · Matematico, logico, pioniere dell’informatica',
                    ],
                    'intro' => [
                        'kicker' => 'Il filo rosso',
                        'title' => 'Dalla crittografia alla coscienza artificiale',
                        'text' => 'Questa area speciale racconta Turing come ponte tra tre mondi: il segreto militare, la nascita del computer e la domanda più difficile sull’intelligenza delle macchine.',
                    ],
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
                            'url' => '/turing/ia',
                            'style' => 'ai',
                        ],
                        [
                            'label' => '03 · Eredità',
                            'title' => 'Il genio inquieto',
                            'text' => 'La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.',
                            'url' => null,
                            'style' => 'legacy',
                        ],
                    ],
                    'why' => [
                        'kicker' => 'Perché conta ancora',
                        'title' => 'Ogni volta che parliamo di algoritmo, torniamo a Turing.',
                        'text' => 'La sua intuizione più potente non fu soltanto costruire macchine, ma immaginare un linguaggio universale per descrivere il calcolo. Oggi quella visione vive nei computer, nella crittografia, nei modelli linguistici e nelle domande etiche sull’automazione.',
                        'items' => [
                            ['title' => 'Calcolo', 'text' => 'la macchina universale'],
                            ['title' => 'Sicurezza', 'text' => 'codici, cifrari, decrittazione'],
                            ['title' => 'IA', 'text' => 'imitazione, linguaggio, giudizio'],
                        ],
                    ],
                    'timeline' => [
                        ['year' => '1912', 'title' => 'Nasce Alan Mathison Turing', 'text' => 'Un talento fuori dagli schemi, attratto presto dai numeri, dalla logica e dalla ricerca della verità.'],
                        ['year' => '1936', 'title' => 'La macchina universale', 'text' => 'Con On Computable Numbers immagina il principio teorico del computer moderno.'],
                        ['year' => '1939', 'title' => 'Bletchley Park', 'text' => 'La matematica entra nel cuore della guerra: decifrare Enigma diventa una questione di sopravvivenza.'],
                        ['year' => '1950', 'title' => 'Computing Machinery and Intelligence', 'text' => 'La domanda cambia forma: non che cosa sia una macchina, ma se possa apparire intelligente.'],
                        ['year' => 'Oggi', 'title' => 'L’era degli algoritmi', 'text' => 'LLM, IA generativa, cybersecurity e automazione riportano Turing al centro del presente.'],
                    ],
                    'final' => [
                        'kicker' => 'Prossima lettura',
                        'title' => 'Scegli da dove iniziare',
                        'text' => 'Vuoi partire dalla guerra dei codici o dalla domanda sull’intelligenza artificiale?',
                    ],
                ],
            ]
        );
    }
}
