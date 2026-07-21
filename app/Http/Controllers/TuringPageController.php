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
            'heroBackgroundImage' => $hero['background_image'] ?? 'turing-hero.webp',
            'heroPortraitImage' => $hero['portrait_image'] ?? null,
            'introBackgroundImage' => $intro['background_image'] ?? 'turing-intro.webp',
            'whyBackgroundImage' => $why['background_image'] ?? 'turing-universal-machine-background.webp',
            'whyPanelImage' => 'turing-legacy-panel.webp',
            'timelineBackgroundImage' => 'turing-test-background.webp',
            'finalBackgroundImage' => $final['background_image'] ?? 'turing-ai-background.webp',
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
                'background_image' => 'turing-enigma-background.webp',
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
                'background_image' => 'turing-universal-machine-background.webp',
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
                'background_image' => 'turing-test-background.webp',
                'link_label' => 'Leggi la domanda',
                'link_url' => '#test-turing',
            ],
            [
                'enabled' => true,
                'key' => 'ai-moderna',
                'layout' => 'image_right',
                'kicker' => 'IA moderna',
                'title' => 'Dai modelli linguistici alla nuova attualità di Turing',
                'text' => 'I sistemi contemporanei di apprendimento automatico, inclusi i modelli linguistici, rendono di nuovo attuale la domanda posta da Turing, ma non la chiudono. Generare risposte plausibili non equivale automaticamente a pensare o comprendere. Il confronto con il Test di Turing resta utile se usato con precisione: come lente storica per discutere comportamento, simulazione, automazione e limiti delle macchine intelligenti.',
                'image' => 'turing/modern-ai.webp',
                'background_image' => 'turing-ai-background.webp',
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
            ],
            [
                'year' => '1936',
                'title' => 'Computabilità e macchina universale',
                'text' => 'Con On Computable Numbers, with an Application to the Entscheidungsproblem, Turing formalizza un modello astratto di calcolo e introduce l’idea di macchina universale, una base concettuale dell’informatica moderna.',
            ],
            [
                'year' => '1938–1939',
                'title' => 'Princeton e ritorno nel Regno Unito',
                'text' => 'Dopo il dottorato a Princeton, Turing rientra nel Regno Unito nel 1938. Con l’inizio della guerra, le sue competenze matematiche diventano centrali nel lavoro crittoanalitico britannico.',
            ],
            [
                'year' => '1939–1945',
                'title' => 'Bletchley Park',
                'text' => 'Durante la Seconda guerra mondiale lavora a Bletchley Park alla crittoanalisi di Enigma e di altri sistemi cifrati. Il risultato nasce da una vasta collaborazione tecnica e organizzativa.',
            ],
            [
                'year' => '1945–1946',
                'title' => 'Il progetto ACE',
                'text' => 'Al National Physical Laboratory, Turing lavora al progetto ACE, Automatic Computing Engine, contribuendo alla transizione dalle idee teoriche sul calcolo alle architetture concrete dei primi computer.',
            ],
            [
                'year' => '1950',
                'title' => 'Computing Machinery and Intelligence',
                'text' => 'Nel saggio pubblicato su Mind, Turing propone il gioco dell’imitazione e riformula la domanda sulle macchine pensanti in termini di comportamento osservabile in una conversazione.',
            ],
            [
                'year' => '1952',
                'title' => 'Processo e condanna',
                'text' => 'Turing viene perseguito per omosessualità, allora criminalizzata nel Regno Unito. La condanna e il trattamento imposto restano una ferita storica nella memoria scientifica e civile britannica.',
            ],
            [
                'year' => '1954',
                'title' => 'La morte',
                'text' => 'Alan Turing muore il 7 giugno 1954 a Wilmslow. La sua eredità scientifica continuerà a crescere nei decenni successivi, fino a diventare parte essenziale della cultura informatica contemporanea.',
            ],
            [
                'year' => '2009–2013',
                'title' => 'Scuse ufficiali e grazia reale',
                'text' => 'Nel 2009 il governo britannico presenta scuse ufficiali per il trattamento subito da Turing. Nel 2013 arriva la grazia reale, distinta dalla successiva legislazione più ampia a favore di altre persone condannate per norme discriminatorie.',
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
                'image' => 'turing-universal-machine-background.webp',
                'alt' => 'Pagine e schemi legati alla macchina universale di Turing',
                'events' => array_slice($events, 0, 3),
            ],
            [
                'period' => '1939–1946',
                'title' => 'La guerra e il calcolo applicato',
                'intro' => 'A Bletchley Park la crittoanalisi diventa lavoro collettivo su scala industriale; nel dopoguerra le stesse competenze confluiscono nei primi progetti di calcolatori elettronici.',
                'image' => 'turing-enigma-panel.webp',
                'alt' => 'Rotori e componenti crittoanalitici legati a Enigma e Bletchley Park',
                'events' => array_slice($events, 3, 2),
            ],
            [
                'period' => '1950–2013',
                'title' => 'Il pensiero delle macchine e l’eredità',
                'intro' => 'Dalla domanda sul pensiero delle macchine alla persecuzione subita e, decenni dopo, al riconoscimento pubblico: la parabola che rende Turing una figura ancora attuale.',
                'image' => 'turing-legacy-panel.webp',
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
            'enigma' => 'turing-enigma-background.webp',
            'macchina-universale' => 'turing-universal-machine-background.webp',
            'test-turing' => 'turing-test-background.webp',
            'ai-moderna' => 'turing-ai-background.webp',
        ];
    }
}
