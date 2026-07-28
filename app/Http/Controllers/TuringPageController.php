<?php

namespace App\Http\Controllers;

use App\Models\SpecialPage;
use Illuminate\View\View;

class TuringPageController extends Controller
{
    public function index(): View
    {
        $page = SpecialPage::where('slug', 'turing')->first();
        $content = ($page && $page->is_active && is_array($page->content)) ? ($page->content ?? []) : [];
        $hero = $content['hero'] ?? [];
        $intro = $content['intro'] ?? [];
        $why = $content['why'] ?? [];
        $final = $content['final'] ?? [];

        /* La struttura a capitoli temporali (Decision #003) si applica solo
           quando la Timeline usa gli eventi di default: un override CMS del
           campo "timeline" (flat) mantiene il rendering singolo precedente,
           cosi' da non introdurre regressioni sui contenuti gia' pubblicati. */
        $rawTimeline = $content['timeline'] ?? null;
        $timelineOverrideItems = is_array($rawTimeline)
            ? array_values(array_filter(
                $rawTimeline,
                fn ($item) => is_array($item) && $this->isRenderableTimelineEvent($item),
            ))
            : [];
        $timelineEvents = $timelineOverrideItems !== [] ? $timelineOverrideItems : $this->defaultTimeline();
        $timelineChapters = $timelineOverrideItems !== [] ? [] : $this->defaultTimelineChapters();

        return view('turing.index', [
            'page' => $page,
            'content' => $content,
            'hero' => $hero,
            'intro' => $intro,
            'cards' => collect($this->contentItemsOrFallback(
                $content,
                'cards',
                $this->defaultRouteCards(),
                fn (array $item) => $this->isRenderableRouteCard($item),
            )),
            'editorialBlocks' => collect($this->contentItemsOrFallback(
                $content,
                'editorial_blocks',
                $this->defaultEditorialBlocks(),
                fn (array $item) => $this->isRenderableEditorialBlock($item),
            )),
            'why' => $why,
            'whyItems' => collect($why['items'] ?? []),
            'timeline' => collect($timelineEvents),
            'timelineChapters' => collect($timelineChapters),
            'final' => $final,
            'sectionImageFallbacks' => $this->sectionImageFallbacks(),
            'sectionBackgroundFallbacks' => $this->sectionBackgroundFallbacks(),
            'heroBackgroundImage' => $hero['background_image'] ?? 'turing/hero/turing-hero.webp',
            'heroPortraitImage' => $hero['portrait_image'] ?? null,
            'introBackgroundImage' => $intro['background_image'] ?? 'turing/hero/turing-intro.webp',
            'whyBackgroundImage' => $why['background_image'] ?? 'turing/backgrounds/turing-universal-machine-background.webp',
            'whyPanelImage' => 'turing/backgrounds/turing-legacy-panel.webp',
            'timelineBackgroundImage' => 'turing/backgrounds/turing-test-background.webp',
            'finalBackgroundImage' => $final['background_image'] ?? 'turing/backgrounds/turing-ai-background.webp',
            'terminalLines' => collect($hero['terminal_lines'] ?? [
                'ENIGMA SIGNAL FOUND',
                'MACHINE INTELLIGENCE: ACTIVE',
                'QUESTION: CAN MACHINES THINK?',
                'STATUS: STILL OPEN',
            ]),
        ]);
    }

    private function contentItemsOrFallback(array $content, string $key, array $fallback, callable $isRenderable): array
    {
        $items = $content[$key] ?? null;

        if (! is_array($items) || $items === []) {
            return $fallback;
        }

        $structuredItems = array_values(array_filter(
            $items,
            fn ($item) => is_array($item) && $isRenderable($item),
        ));

        return $structuredItems === [] ? $fallback : $structuredItems;
    }

    private function isRenderableEditorialBlock(array $item): bool
    {
        return array_key_exists('enabled', $item)
            || $this->hasFilledAny($item, ['title', 'text', 'kicker', 'key', 'image']);
    }

    private function isRenderableTimelineEvent(array $item): bool
    {
        /* Deve rispecchiare esattamente il filtro di rendering di
           <x-special.timeline> (year/title/text): un evento con solo
           image/url non vi appare, quindi non deve poter disabilitare i
           capitoli di default lasciando una Timeline senza eventi. */
        return $this->hasFilledAny($item, ['year', 'title', 'text']);
    }

    private function isRenderableRouteCard(array $item): bool
    {
        /* Deve rispecchiare esattamente il filtro di rendering di
           <x-special.feature-cards> (label/title/text). */
        return $this->hasFilledAny($item, ['label', 'title', 'text']);
    }

    private function hasFilledAny(array $item, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && filled($item[$key])) {
                return true;
            }
        }

        return false;
    }

    private function defaultRouteCards(): array
    {
        return [
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
        ];
    }

    private function defaultEditorialBlocks(): array
    {
        return [
            [
                'enabled' => true,
                'key' => 'enigma',
                'layout' => 'image_left',
                'kicker' => 'Enigma',
                'title' => 'La guerra dei codici: Enigma e Bletchley Park',
                'text' => 'Nel cuore di Bletchley Park, matematici, linguisti, ingegneri e operatori lavorarono insieme per leggere comunicazioni cifrate tedesche. Turing contribuì in modo decisivo alla progettazione delle Bombe britanniche, macchine elettromeccaniche usate per restringere lo spazio delle configurazioni possibili. La storia di Enigma non è il gesto isolato di un genio, ma un laboratorio collettivo di crittoanalisi, probabilità e organizzazione tecnica sotto pressione.',
                'image' => 'turing/enigma.webp',
                'background_image' => 'turing/enigma/turing-enigma-background.webp',
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
                'image' => 'turing/universal-machine.webp',
                'background_image' => 'turing/backgrounds/turing-universal-machine-background.webp',
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
                'image' => 'turing/turing-test.webp',
                'background_image' => 'turing/backgrounds/turing-test-background.webp',
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
                'image' => 'turing/modern-ai.webp',
                'background_image' => 'turing/backgrounds/turing-ai-background.webp',
                'link_label' => 'Vai all’IA moderna',
                'link_url' => '/turing/ai',
            ],
        ];
    }

    private function defaultTimeline(): array
    {
        return [
            [
                'year' => '1912',
                'title' => 'La nascita a Londra',
                'text' => 'Alan Mathison Turing nasce a Londra il 23 giugno 1912. La sua formazione attraverserà matematica, logica e scienze naturali, con un interesse precoce per i problemi astratti e per il rigore dimostrativo.',
                'details' => "Alan Mathison Turing nasce il 23 giugno 1912 a Londra, figlio di un funzionario dell’amministrazione coloniale britannica in India. Trascorre l’infanzia in Inghilterra, spesso lontano dai genitori, e mostra presto un interesse spiccato per la matematica e le scienze naturali, non sempre premiato da un sistema scolastico dell’epoca più orientato agli studi classici.\n\nAl Sherborne School stringe un’amicizia intensa con il compagno di studi Christopher Morcom, appassionato come lui di scienza; la morte prematura di Morcom nel 1930 lascia un segno profondo su Turing e, secondo diversi biografi, alimenta un interesse duraturo per le domande sulla mente che tornerà, in forma diversa, nei suoi lavori successivi.",
            ],
            [
                'year' => '1936',
                'title' => 'Computabilità e macchina universale',
                'text' => 'Con On Computable Numbers, with an Application to the Entscheidungsproblem, Turing formalizza un modello astratto di calcolo e introduce l’idea di macchina universale, una base concettuale dell’informatica moderna.',
                'details' => "Nel 1936 Turing pubblica On Computable Numbers, with an Application to the Entscheidungsproblem, un articolo di logica matematica che affronta un problema posto da David Hilbert: esiste un metodo meccanico in grado di stabilire, per qualunque enunciato matematico, se sia dimostrabile? Per rispondere, Turing definisce con precisione che cosa significhi «calcolare», descrivendo una macchina astratta capace di leggere, scrivere e spostarsi su un nastro secondo un insieme finito di regole.\n\nÈ importante non confondere questa «macchina di Turing» con un computer fisico: si tratta di un modello matematico, non di un apparecchio costruito o pensato per funzionare davvero. La sua importanza sta nell’aver introdotto l’idea di macchina universale, capace di simulare qualunque altra macchina di questo tipo a partire dalla sua descrizione — un principio che anticipa concettualmente la nozione di programma memorizzato ed è oggi considerato una delle fondamenta teoriche dell’informatica, sviluppato in parallelo e indipendentemente anche dal lavoro di Alonzo Church sul lambda calcolo.",
            ],
            [
                'year' => '1938–1939',
                'title' => 'Princeton e ritorno nel Regno Unito',
                'text' => 'Dopo il dottorato a Princeton, Turing rientra nel Regno Unito nel 1938. Con l’inizio della guerra, le sue competenze matematiche diventano centrali nel lavoro crittoanalitico britannico.',
                'details' => "Tra il 1936 e il 1938 Turing consegue il dottorato all’Università di Princeton sotto la supervisione del logico Alonzo Church, approfondendo il tema della computabilità relativa e delle logiche ordinali. Rifiuta un’offerta per restare negli Stati Uniti e fa ritorno nel Regno Unito nel 1938, riprendendo un legame con l’Università di Cambridge.\n\nGià nei mesi precedenti allo scoppio della guerra collabora part-time con la Government Code and Cypher School, l’organismo britannico responsabile della crittoanalisi. Con la dichiarazione di guerra alla Germania nel settembre 1939, l’impegno diventa a tempo pieno e Turing si trasferisce a Bletchley Park.",
            ],
            [
                'year' => '1939–1945',
                'title' => 'Bletchley Park',
                'text' => 'Durante la Seconda guerra mondiale lavora a Bletchley Park alla crittoanalisi di Enigma e di altri sistemi cifrati. Il risultato nasce da una vasta collaborazione tecnica e organizzativa.',
                'details' => "A Bletchley Park lavora nella sezione dedicata ai cifrari navali tedeschi, nota come Hut 8. Il compito non è individuale: vi partecipano migliaia di persone — crittoanalisti, linguisti, ingegneri e operatrici, in gran parte donne del Women’s Royal Naval Service — che gestiscono materialmente le macchine impiegate per accelerare la ricerca delle chiavi di cifratura. Turing contribuisce in modo decisivo alla progettazione della Bombe britannica, sviluppata a partire dai risultati ottenuti negli anni Trenta dai crittoanalisti polacchi Marian Rejewski, Jerzy Różycki e Henryk Zygalski, che avevano già violato Enigma e condiviso le loro tecniche con Regno Unito e Francia poche settimane prima dell’invasione della Polonia.\n\nTuring elabora inoltre metodi statistici, tra cui la tecnica nota come Banburismus, per restringere lo spazio delle configurazioni possibili di Enigma nel traffico navale, uno dei fronti più critici della guerra sull’Atlantico. Gli storici concordano nel descrivere il lavoro di decrittazione di Bletchley Park come un contributo significativo allo sforzo bellico alleato, pur restando difficile misurarne con esattezza l’impatto complessivo sulla durata del conflitto.",
            ],
            [
                'year' => '1945–1946',
                'title' => 'Il progetto ACE',
                'text' => 'Al National Physical Laboratory, Turing lavora al progetto ACE, Automatic Computing Engine, contribuendo alla transizione dalle idee teoriche sul calcolo alle architetture concrete dei primi computer.',
                'details' => "Dal 1945 Turing lavora al National Physical Laboratory a un progetto di calcolatore elettronico a programma memorizzato, l’Automatic Computing Engine (ACE). Il rapporto tecnico che presenta nel 1946 è tra le proposte di progettazione più dettagliate e complete del periodo, e applica concretamente i principi teorici della macchina universale del 1936 all’architettura di una macchina reale.\n\nLa costruzione dell’ACE nella sua forma completa procede però lentamente, per ritardi organizzativi e vincoli di risorse: Turing lascia il National Physical Laboratory nel 1948, prima che il progetto sia realizzato, e una versione ridotta, la Pilot ACE, entrerà in funzione solo nei primi anni Cinquanta a opera di altri ricercatori. Nel frattempo si trasferisce all’Università di Manchester, dove lavora al software per uno dei primi calcolatori a programma memorizzato realmente costruiti.",
            ],
            [
                'year' => '1950',
                'title' => 'Computing Machinery and Intelligence',
                'text' => 'Nel saggio pubblicato su Mind, Turing propone il gioco dell’imitazione e riformula la domanda sulle macchine pensanti in termini di comportamento osservabile in una conversazione.',
                'details' => "Nel 1950, mentre lavora all’Università di Manchester, Turing pubblica sulla rivista Mind il saggio Computing Machinery and Intelligence. Invece di affrontare direttamente la domanda «le macchine possono pensare?», che giudica mal posta perché dipende da definizioni troppo ambigue di «macchina» e «pensare», propone di sostituirla con un esperimento concreto: il gioco dell’imitazione, in cui un interrogante conversa per iscritto con un umano e con una macchina, senza sapere quale sia quale, cercando di distinguerli.\n\nIl saggio non sostiene che superare questo gioco equivalga a «pensare» in senso pieno, né offre una definizione operativa definitiva di intelligenza: propone piuttosto di spostare l’attenzione sul comportamento osservabile invece che su ipotesi indimostrabili sui processi interni della macchina. Turing stesso anticipa e discute diverse obiezioni a questa impostazione, comprese quelle di natura teologica, matematica e legate alla creatività, senza pretendere di risolverle tutte.",
            ],
            [
                'year' => '1952',
                'title' => 'Processo e condanna',
                'text' => 'Turing viene perseguito per omosessualità, allora criminalizzata nel Regno Unito. La condanna e il trattamento imposto restano una ferita storica nella memoria scientifica e civile britannica.',
                'details' => "Nel gennaio 1952 Turing denuncia alla polizia un furto nella sua abitazione di Wilmslow. Nel corso delle indagini emerge una relazione con un giovane conoscente, Arnold Murray, e Turing viene incriminato per «gross indecency» ai sensi del Criminal Law Amendment Act del 1885 — la stessa normativa che decenni prima aveva portato alla condanna di Oscar Wilde: nel Regno Unito di allora gli atti omosessuali tra uomini erano reato.\n\nProcessato nel marzo 1952, viene giudicato colpevole. Di fronte alla scelta tra il carcere e la libertà vigilata condizionata a un trattamento ormonale — una forma di castrazione chimica mediante somministrazione di estrogeni — accetta la seconda opzione. La condanna comporta inoltre la perdita della sua autorizzazione di sicurezza, ponendo fine alla possibilità di proseguire il lavoro di consulenza crittografica per il governo britannico. Il trattamento subito da Turing, oggi ampiamente riconosciuto come ingiusto, va inquadrato nel contesto più ampio della persecuzione delle persone omosessuali nel Regno Unito del dopoguerra, di cui Turing è il caso più noto ma non l’unico.",
            ],
            [
                'year' => '1954',
                'title' => 'La morte',
                'text' => 'Alan Turing muore il 7 giugno 1954 a Wilmslow. La sua eredità scientifica continuerà a crescere nei decenni successivi, fino a diventare parte essenziale della cultura informatica contemporanea.',
                'details' => "Alan Turing muore il 7 giugno 1954 nella sua abitazione di Wilmslow. L’inchiesta ufficiale dell’epoca registra come causa del decesso un avvelenamento da cianuro e conclude per il suicidio; accanto al corpo viene trovata una mela parzialmente morsa, mai analizzata per verificare se fosse stata effettivamente il veicolo del veleno.\n\nNegli anni alcuni biografi e storici hanno messo in discussione alcuni dettagli della ricostruzione ufficiale, ipotizzando anche un avvelenamento accidentale legato alle sostanze chimiche che Turing utilizzava in esperimenti di elettrodeposizione nel proprio laboratorio domestico. Il verdetto di suicidio resta comunque la ricostruzione più diffusa nella letteratura storica. La sua eredità scientifica avrebbe continuato a crescere nei decenni successivi, fino a diventare parte essenziale della cultura informatica contemporanea.",
            ],
            [
                'year' => '2009–2013',
                'title' => 'Scuse ufficiali e grazia reale',
                'text' => 'Nel 2009 il governo britannico presenta scuse ufficiali per il trattamento subito da Turing. Nel 2013 arriva la grazia reale, distinta dalla successiva legislazione più ampia a favore di altre persone condannate per norme discriminatorie.',
                'details' => "Nel settembre 2009, in seguito a una petizione pubblica online, il primo ministro britannico Gordon Brown presenta a nome del governo scuse ufficiali per il modo in cui Turing fu trattato. È una dichiarazione politica di rammarico, non un atto legale: non modifica in alcun modo la condanna del 1952, che resta formalmente iscritta.\n\nNel dicembre 2013 la regina Elisabetta II concede a Turing una grazia reale postuma tramite la prerogativa reale di clemenza, un atto giuridico specifico riferito unicamente alla sua condanna. Solo alcuni anni più tardi, con il Policing and Crime Act 2017 — la normativa nota informalmente come «Alan Turing law» — il perdono postumo viene esteso automaticamente anche ad altri uomini deceduti, condannati in passato per reati oggi aboliti che criminalizzavano rapporti omosessuali consenzienti; la stessa legge introduce inoltre una procedura per l’estinzione della condanna a favore delle persone ancora in vita nella stessa condizione.",
            ],
        ];
    }

    /**
     * Raggruppa gli eventi di default in capitoli temporali (Decision #003).
     * Il contenuto (periodo/titolo/testo/immagine) e' specifico di questo
     * Special Project: i componenti Blade restano generici e ricevono solo
     * dati, cosi' da poter essere riusati da futuri Special Projects con i
     * propri capitoli.
     */
    private function defaultTimelineChapters(): array
    {
        $events = $this->defaultTimeline();

        return [
            [
                'period' => '1912–1939',
                'title' => 'La formazione di un pensiero computazionale',
                'intro' => 'Dagli anni della formazione alla definizione teorica di macchina universale: le basi concettuali che renderanno possibile, più avanti, il calcolo automatico.',
                'image' => 'turing/backgrounds/turing-universal-machine-background.webp',
                'alt' => 'Pagine e schemi legati alla macchina universale di Turing',
                'events' => array_slice($events, 0, 3),
            ],
            [
                'period' => '1939–1946',
                'title' => 'La guerra e il calcolo applicato',
                'intro' => 'A Bletchley Park la crittoanalisi diventa lavoro collettivo su scala industriale; nel dopoguerra le stesse competenze confluiscono nei primi progetti di calcolatori elettronici.',
                'image' => 'turing/enigma/turing-enigma-panel.webp',
                'alt' => 'Rotori e componenti crittoanalitici legati a Enigma e Bletchley Park',
                'events' => array_slice($events, 3, 2),
            ],
            [
                'period' => '1950–2013',
                'title' => 'Il pensiero delle macchine e l’eredità',
                'intro' => 'Dalla domanda sul pensiero delle macchine alla persecuzione subita e, decenni dopo, al riconoscimento pubblico: la parabola che rende Turing una figura ancora attuale.',
                'image' => 'turing/backgrounds/turing-legacy-panel.webp',
                'alt' => 'Ritratto simbolico dell’eredità di Alan Turing',
                'events' => array_slice($events, 5, 4),
            ],
        ];
    }

    private function sectionImageFallbacks(): array
    {
        return [
            'enigma' => 'turing/enigma.webp',
            'macchina-universale' => 'turing/universal-machine.webp',
            'test-turing' => 'turing/turing-test.webp',
            'ai-moderna' => 'turing/modern-ai.webp',
        ];
    }

    private function sectionBackgroundFallbacks(): array
    {
        return [
            'enigma' => 'turing/enigma/turing-enigma-background.webp',
            'macchina-universale' => 'turing/backgrounds/turing-universal-machine-background.webp',
            'test-turing' => 'turing/backgrounds/turing-test-background.webp',
            'ai-moderna' => 'turing/backgrounds/turing-ai-background.webp',
        ];
    }
}
