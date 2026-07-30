@extends('layouts.app')

@php
    // Il CMS conserva, per il blocco 'enigma' della pagina hub, due campi
    // immagine storici (background_image/image, editabili da admin/turing-lite).
    // Se presenti, restano l'override editoriale per l'hero e per la figura
    // principale del modulo "Anatomia della macchina"; altrimenti si usa come
    // default il nuovo asset editoriale dedicato. Nessun altro campo CMS
    // esiste per le immagini aggiuntive introdotte in questa pagina, che
    // quindi non hanno — e non possono avere — un override CMS.
    $page = \App\Models\SpecialPage::where('slug', 'turing')->first();
    $content = ($page && $page->is_active) ? ($page->content ?? []) : [];
    $blocks = collect($content['editorial_blocks'] ?? []);
    $enigmaBlock = $blocks->firstWhere('key', 'enigma') ?? [];

    $resolveAsset = function (?string $value): ?string {
        if (blank($value)) {
            return null;
        }
        $value = trim($value);

        return str_starts_with($value, 'http') || str_starts_with($value, '/')
            ? $value
            : asset('assets/img/' . ltrim($value, '/'));
    };

    $heroImage = $resolveAsset($enigmaBlock['background_image'] ?? $enigmaBlock['image'] ?? null)
        ?? asset('images/turing/enigma/hero-enigma.png');

    $anatomyImage = $resolveAsset($enigmaBlock['image'] ?? $enigmaBlock['background_image'] ?? null);
    $anatomyIsCms = filled($anatomyImage);
    $anatomyImage = $anatomyImage ?? asset('images/turing/enigma/cutaway-enigma.png');
@endphp

@section('title', 'Enigma e Bletchley Park — Quark')
@section(
    'description',
    'La guerra invisibile dei codici: anatomia della macchina Enigma, il percorso elettrico del segnale, la chiave giornaliera, la Bombe e la nascita del calcolo moderno a Bletchley Park.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
    <link rel="stylesheet" href="{{ asset('css/turing-enigma.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <div class="container container--wide">
        <x-turing.article.breadcrumb :items="[['label' => 'Enigma']]" />
    </div>

    {{-- 1. Hero cinematografica con dati chiave --}}
    <section class="enigma-hero" style="background-image:url('{{ $heroImage }}')">
        <div class="container container--wide">
            <div class="enigma-hero__grid">
                <p class="turing-kicker">Enigma</p>
                <h1>Enigma, Ultra e la guerra invisibile</h1>
                <p class="enigma-hero__lead">
                    Durante la Seconda guerra mondiale, Alan Turing contribuì al lavoro di Bletchley Park per
                    decifrare i messaggi tedeschi prodotti dalla macchina Enigma. Quel lavoro accelerò la nascita
                    del calcolo automatico moderno e cambiò per sempre il rapporto tra matematica, sicurezza e
                    tecnologia.
                </p>

                <ul class="enigma-stat-row">
                    <li class="enigma-stat-chip">
                        <strong>3</strong>
                        <span>Rotori intercambiabili nella configurazione standard della Wehrmacht</span>
                    </li>
                    <li class="enigma-stat-chip">
                        <strong>~10.000</strong>
                        <span>Persone impiegate a Bletchley Park entro il 1945</span>
                    </li>
                    <li class="enigma-stat-chip">
                        <strong>1939–1945</strong>
                        <span>Anni di utilizzo bellico attivo della macchina</span>
                    </li>
                </ul>
            </div>
        </div>
    </section>

    {{-- 2. Apertura narrativa --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Il problema"
                title="Una guerra combattuta anche con pattern e probabilità"
            />
            <div class="enigma-lede">
                <p>
                    Ogni messaggio cifrato con Enigma sembrava, a un primo sguardo, una parete opaca. Non un codice
                    da tradurre parola per parola, ma l’uscita di una macchina che, per ogni lettera premuta,
                    sceglieva la propria sostituzione da uno spazio di possibilità enorme e cambiato ogni giorno.
                </p>
                <p>
                    Il lavoro degli analisti di Bletchley Park non consisteva nel «leggere» quel testo, ma nel
                    trasformare la sua opacità in ipotesi verificabili: restringere, giorno dopo giorno, lo spazio
                    di configurazioni compatibili con ciò che si osservava nel traffico intercettato, fino a isolare
                    quella realmente usata.
                </p>
                <p>
                    Le pagine seguenti ricostruiscono quel lavoro da dentro: come era fatta la macchina, come un
                    segnale elettrico diventava una lettera cifrata, come veniva scelta — e violata — la chiave del
                    giorno, e come un intero centro operativo trasformò un problema individualmente insormontabile
                    in un processo collettivo e ripetibile.
                </p>
            </div>
        </div>
    </section>

    {{-- 3. Anatomia della macchina --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Anatomia della macchina"
                title="Otto parti, una sola macchina"
                text="Enigma non nascondeva un singolo meccanismo segreto: la sua sicurezza nasceva dalla combinazione di componenti relativamente semplici, ciascuno modificabile in modo indipendente."
            />

            <div class="turing-split">
                <x-turing.article.figure
                    :image="$anatomyImage"
                    :alt="$anatomyIsCms ? ($enigmaBlock['title'] ?? 'Immagine editoriale della pagina Enigma') : 'Diagramma illustrato di una macchina Enigma aperta, con etichette che indicano tastiera, lampboard, rotori, plugboard, riflettore e connessioni di alimentazione'"
                    :caption="$anatomyIsCms ? null : 'Tastiera, lampboard, rotori, plugboard e riflettore: i componenti che, combinati, generavano lo spazio di chiavi che Bletchley Park doveva restringere ogni giorno.'"
                    :width="$anatomyIsCms ? null : 287"
                    :height="$anatomyIsCms ? null : 289"
                />

                <div class="turing-copy-panel">
                    <p>
                        Una tastiera per digitare il testo in chiaro. Un pannello di lampadine (il lampboard) che
                        segnalava, lettera per lettera, il risultato cifrato. Un set di rotori intercambiabili, ciascuno
                        con un proprio cablaggio interno. Un riflettore, che rimandava il segnale indietro attraverso i
                        rotori percorrendo un cammino diverso da quello di andata. Un plugboard, che scambiava coppie di
                        lettere prima e dopo il passaggio nei rotori. Nessuno di questi elementi, da solo, era
                        particolarmente sofisticato: era la loro combinazione, cambiata ogni giorno secondo un
                        calendario riservato, a generare uno spazio di configurazioni troppo vasto per essere
                        esplorato a mano.
                    </p>
                </div>
            </div>

            <x-special.feature-cards
                label="Componenti della macchina Enigma"
                :cards="[
                    ['label' => '01', 'title' => 'Rotore', 'text' => 'Disco cablato internamente: a ogni lettera in ingresso ne fa corrispondere una diversa in uscita, e ruota a ogni pressione di tasto.'],
                    ['label' => '02', 'title' => 'Riflettore', 'text' => 'Rimanda il segnale indietro attraverso i rotori lungo un percorso diverso da quello di andata, garantendo che cifrare due volte la stessa lettera non la restituisca invariata.'],
                    ['label' => '03', 'title' => 'Lampada', 'text' => 'Si accende sul lampboard per indicare, una lettera alla volta, il risultato cifrato del tasto premuto.'],
                    ['label' => '04', 'title' => 'Tasto', 'text' => 'Il punto di ingresso del segnale: ogni pressione avvia il percorso elettrico attraverso plugboard, rotori e riflettore.'],
                    ['label' => '05', 'title' => 'Spina', 'text' => 'Il singolo connettore usato per collegare due lettere sul plugboard tramite un cavo dedicato.'],
                    ['label' => '06', 'title' => 'Plugboard', 'text' => 'Pannello di prese che permette di scambiare fino a diverse coppie di lettere prima che il segnale raggiunga i rotori.'],
                    ['label' => '07', 'title' => 'Contatto a molla', 'text' => 'Il punto di contatto elettrico interno a ogni rotore: garantisce che il segnale passi in modo affidabile da un disco al successivo.'],
                    ['label' => '08', 'title' => 'Cavo stecker', 'text' => 'Il cavo che collega fisicamente due prese del plugboard, materializzando lo scambio di una coppia di lettere.'],
                ]"
            />

            <x-turing.article.figure
                image="{{ asset('images/turing/enigma/rotor-exploded.png') }}"
                alt="Diagramma esploso di tre rotori Enigma affiancati, con etichette che indicano rotore sinistro, centrale e destro, nucleo, cablaggio, tacca di notch e anello di regolazione"
                caption="Ogni rotore è in realtà un piccolo sistema a sé: nucleo, cablaggio interno, tacca che determina l’avanzamento del rotore successivo, anello di regolazione (ring setting) che sposta il cablaggio rispetto alle lettere esterne."
                width="288"
                height="290"
            />
        </div>
    </section>

    {{-- 4. Percorso elettrico del segnale --}}
    <section class="turing-section turing-section--split">
        <div class="container container--wide turing-split">
            <div class="turing-copy-panel">
                <x-special.section-header
                    variant="panel"
                    align="left"
                    kicker="Il percorso del segnale"
                    title="Da un tasto premuto a una lampada accesa"
                    text="Ogni lettera digitata avviava un segnale elettrico che attraversava la macchina due volte — andata e ritorno — passando per plugboard, rotori e riflettore, prima di accendere la lampada corrispondente alla lettera cifrata."
                />
            </div>

            <x-turing.article.figure
                image="{{ asset('images/turing/enigma/signal-path.png') }}"
                alt="Diagramma del percorso del segnale in una macchina Enigma: dalla tastiera al plugboard, attraverso i rotori, fino al riflettore e ritorno lungo lo stesso schema"
                caption="Tastiera → plugboard → rotori → riflettore → ritorno attraverso i rotori e il plugboard: lo stesso tasto non produce mai la stessa lettera cifrata due volte di seguito, perché i rotori ruotano a ogni pressione."
                width="287"
                height="289"
            />
        </div>

        <div class="container container--wide">
            <x-turing.enigma.step-list :steps="[
                ['title' => 'Il tasto viene premuto', 'text' => 'L’operatore preme una lettera sulla tastiera, avviando il circuito elettrico della macchina.'],
                ['title' => 'Il segnale entra nel circuito', 'text' => 'La corrente comincia il proprio percorso attraverso i componenti interni della macchina.'],
                ['title' => 'Passaggio nel plugboard', 'text' => 'Se quella lettera è collegata a un’altra tramite un cavo stecker, il segnale viene scambiato prima ancora di raggiungere i rotori.'],
                ['title' => 'Passaggio nei rotori', 'text' => 'Il segnale attraversa in sequenza i rotori installati, ciascuno dei quali lo trasforma secondo il proprio cablaggio interno.'],
                ['title' => 'Riflessione', 'text' => 'Il riflettore rimanda il segnale indietro attraverso i rotori, ma lungo un percorso elettrico diverso da quello di andata.'],
                ['title' => 'Ritorno attraverso i rotori', 'text' => 'Il segnale riflesso riattraversa i rotori in ordine inverso, trasformandosi di nuovo.'],
                ['title' => 'Nuovo passaggio nel plugboard', 'text' => 'Se necessario, il plugboard scambia ancora una volta la lettera prima dell’uscita finale.'],
                ['title' => 'Una lampada si accende', 'text' => 'La lampada corrispondente alla lettera cifrata risultante si illumina sul lampboard: quella è la lettera da trascrivere.'],
            ]" />
        </div>
    </section>

    {{-- 5. Configurazione della chiave giornaliera --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Rotori e chiavi"
                title="Una chiave diversa ogni giorno"
                text="Enigma combinava rotori intercambiabili, un cablaggio interno e un pannello di connessioni con impostazioni cambiate ogni giorno secondo un calendario riservato. La sicurezza della macchina non dipendeva da un singolo meccanismo segreto, ma dal numero straordinariamente alto di configurazioni che rotori e plugboard potevano assumere insieme."
            />

            <div class="enigma-duo-grid">
                <x-turing.article.figure
                    image="{{ asset('images/turing/enigma/daily-key-settings.png') }}"
                    alt="Tabella illustrata di un esempio di configurazione giornaliera di Enigma, con ordine dei rotori, regolazione degli anelli, posizione di partenza, connessioni del plugboard e riflettore"
                    caption="Un esempio illustrativo — non una configurazione storica reale — del tipo di foglio di impostazioni giornaliere che ogni reparto doveva ricevere e applicare correttamente: ordine dei rotori, regolazione degli anelli, posizione di partenza, coppie del plugboard, riflettore."
                    width="287"
                    height="290"
                />

                <x-turing.article.figure
                    image="{{ asset('images/turing/enigma/plugboard.png') }}"
                    alt="Diagramma del plugboard di Enigma con coppie di lettere collegate da cavi, ad esempio A con C e J con K"
                    caption="Il plugboard scambiava coppie di lettere prima e dopo il passaggio nei rotori: quali lettere fossero collegate cambiava ogni giorno, moltiplicando ulteriormente lo spazio di configurazioni possibili."
                    width="287"
                    height="289"
                />
            </div>
        </div>
    </section>

    {{-- 6. Vulnerabilità e crib --}}
    <section class="turing-section">
        <div class="container container--wide turing-split">
            <div class="turing-copy-panel">
                <x-special.section-header
                    variant="panel"
                    align="left"
                    kicker="Crib e indizi"
                    title="Piccoli appigli linguistici"
                    text="Gli analisti cercavano nei messaggi intercettati frammenti plausibili di testo in chiaro — i cosiddetti crib — a partire da abitudini ricorrenti nelle comunicazioni militari tedesche."
                />
                <p>
                    Alcuni crib nascevano da abitudini quasi burocratiche: rapporti meteorologici trasmessi ogni
                    mattina con una formula quasi fissa, saluti di rito a inizio o fine messaggio, il nome di un
                    ufficiale ripetuto nella stessa posizione. Nessuno di questi elementi, da solo, rivelava la
                    chiave del giorno — ma ciascuno restringeva le ipotesi plausibili, trasformando un problema
                    altrimenti astronomico in un test logico e meccanico da verificare rapidamente.
                </p>
                <p>
                    La vulnerabilità più sfruttabile, in altre parole, non era quasi mai nella matematica della
                    macchina: era nel comportamento umano prevedibile di chi la usava ogni giorno sotto pressione,
                    con procedure ripetitive e poco tempo per variarle.
                </p>
            </div>

            <x-turing.article.figure
                image="{{ asset('images/turing/enigma/german-operator.png') }}"
                alt="Illustrazione editoriale di un operatore militare tedesco che digita su una macchina Enigma"
                caption="Le comunicazioni cifrate che gli analisti dovevano interpretare partivano da operatori come questo, in postazioni sparse su tutti i fronti — ed erano le loro abitudini, più che un difetto della macchina, il punto più debole del sistema."
                width="287"
                height="289"
            />
        </div>
    </section>

    {{-- 7. Pipeline operativa di Bletchley Park --}}
    <section class="turing-section has-bg" style="background-image:url('{{ asset('images/turing/enigma/bletchley-park.png') }}')">
        <div class="container container--wide">
            <div class="turing-section__head">
                <p class="turing-kicker">Dentro Bletchley</p>
                <h2>Una catena di lavoro, non un atto solitario</h2>
                <p>
                    La decrittazione a Bletchley Park era un ecosistema, non il gesto isolato di un genio: un
                    messaggio intercettato attraversava fasi distinte, ciascuna affidata a persone e strumenti
                    diversi, prima di diventare informazione utilizzabile.
                </p>
            </div>

            <x-turing.enigma.step-list :steps="[
                ['title' => 'Intercettazione', 'text' => 'Le stazioni di ascolto radio captavano il traffico cifrato tedesco e lo trascrivevano carattere per carattere.'],
                ['title' => 'Ricerca del crib', 'text' => 'Gli analisti cercavano nel messaggio frammenti plausibili di testo in chiaro, a partire da abitudini ricorrenti.'],
                ['title' => 'Verifica alla Bombe', 'text' => 'Le ipotesi venivano tradotte in un test meccanico, eseguito dalla Bombe per restringere rapidamente le configurazioni compatibili.'],
                ['title' => 'Conferma manuale', 'text' => 'Le impostazioni candidate individuate dalla Bombe venivano verificate a mano prima di essere considerate affidabili.'],
                ['title' => 'Traduzione e distribuzione', 'text' => 'Il messaggio decifrato veniva tradotto, valutato e distribuito con la massima riservatezza — il materiale noto con il nome in codice Ultra.'],
            ]" />

            <x-turing.article.figure
                image="{{ asset('images/turing/enigma/operations-room.png') }}"
                alt="Illustrazione editoriale di una sala operativa di Bletchley Park affollata di analisti al lavoro su documenti e schede"
                caption="Una sala operativa di Bletchley Park: ogni fase della catena coinvolgeva persone diverse, spesso in turni continui, non un singolo analista isolato con la propria macchina."
                width="288"
                height="289"
            />
        </div>
    </section>

    {{-- 8. Sezione tecnica sulla Bombe --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="La Bombe"
                title="L’automazione entra in gioco"
                text="La Bombe, la macchina elettromeccanica sviluppata a Bletchley Park a partire dal lavoro di Turing e dei suoi colleghi, automatizzava la verifica dei crib su un numero di configurazioni troppo alto per essere controllato a mano in tempo utile. Non decifrava i messaggi da sola: restringeva rapidamente le impostazioni plausibili di Enigma, lasciando agli analisti umani il compito di completare e verificare la decrittazione."
            />

            <div class="enigma-duo-grid">
                <x-turing.article.figure
                    image="{{ asset('images/turing/enigma/bombe-machine.png') }}"
                    alt="Diagramma illustrato della Bombe, con etichette che indicano i tamburi rotanti, l’unità di integrazione e il pannello di output"
                    caption="I tamburi rotanti simulavano le posizioni dei rotori di Enigma; l’unità di integrazione individuava le combinazioni compatibili con i crib; il pannello di output mostrava le impostazioni candidate."
                    width="287"
                    height="290"
                />

                <x-turing.article.figure
                    image="{{ asset('images/turing/enigma/turing-bombe.png') }}"
                    alt="Illustrazione editoriale di Alan Turing al lavoro accanto alla Bombe"
                    caption="La Bombe nacque dal lavoro di Turing e dei suoi colleghi a Bletchley Park, a partire dai metodi crittanalitici sviluppati nei primi anni di guerra."
                    width="287"
                    height="289"
                />
            </div>
        </div>
    </section>

    {{-- 9. Confronto Enigma / Bombe --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Due macchine, due logiche"
                title="Enigma cifra, la Bombe restringe"
                text="Le due macchine non svolgevano lo stesso compito, e non erano nemmeno paragonabili per scala: la loro giustapposizione è quello che rende visibile, in un solo sguardo, il cambio di paradigma fra cifrare e decifrare in modo sistematico."
            />

            <div class="enigma-comparison">
                <span class="enigma-vs-badge" aria-hidden="true">VS</span>
                <x-special.feature-cards
                    label="Confronto fra Enigma e la Bombe"
                    :cards="[
                        [
                            'label' => 'Enigma',
                            'title' => 'La macchina che cifra',
                            'text' => 'Cifra i messaggi · Meccanica · Portatile · Usata sul campo · Protetta da chiavi cambiate ogni giorno',
                        ],
                        [
                            'label' => 'Bombe',
                            'title' => 'La macchina che restringe',
                            'text' => 'Restringe le chiavi possibili · Elettromeccanica · Grande e complessa · Usata a Bletchley Park · Non decifra da sola',
                        ],
                    ]"
                />
            </div>
        </div>
    </section>

    {{-- 10. Timeline storica --}}
    <x-special.timeline
        id="enigma-timeline"
        kicker="Una vicenda lunga quasi trent’anni"
        title="Dall’invenzione alla cybersecurity di oggi"
        :events="[
            ['year' => '1918', 'title' => 'L’invenzione', 'text' => 'L’ingegnere tedesco Arthur Scherbius brevetta la macchina Enigma per un uso commerciale, non ancora militare.'],
            ['year' => 'Anni ’20', 'title' => 'L’adozione militare', 'text' => 'Le forze armate tedesche adottano e modificano progressivamente Enigma per le proprie comunicazioni riservate.'],
            ['year' => '1932', 'title' => 'Le prime violazioni', 'text' => 'Matematici polacchi, fra cui Marian Rejewski, violano per primi versioni della macchina, gettando le basi del lavoro successivo di Bletchley Park.'],
            ['year' => '1939–1945', 'title' => 'L’uso bellico esteso', 'text' => 'Enigma diventa lo strumento di cifratura standard di gran parte delle comunicazioni militari tedesche durante la Seconda guerra mondiale.'],
            ['year' => '1940', 'title' => 'Bletchley Park entra in azione', 'text' => 'Il centro di crittanalisi britannico avvia su scala industriale il lavoro di decrittazione del traffico Enigma.'],
            ['year' => '1943–1944', 'title' => 'I progressi decisivi', 'text' => 'Il perfezionamento della Bombe e l’organizzazione del lavoro a Bletchley Park rendono la decrittazione sistematica e ripetibile.'],
            ['year' => '1945 e oltre', 'title' => 'Un’eredità che continua', 'text' => 'I metodi e le macchine sviluppati per violare Enigma confluiscono nelle basi tecniche e concettuali dell’informatica del dopoguerra.'],
        ]"
    />

    {{-- 11. Eredità nella cybersecurity --}}
    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Eredità"
                title="Dalla crittografia alla cybersecurity"
            />
            <div class="enigma-lede">
                <p>
                    Il lavoro su Enigma è una delle radici culturali della sicurezza informatica contemporanea. Non
                    tanto per la macchina in sé, ormai un artefatto storico, quanto per lo spostamento di
                    prospettiva che quel lavoro rese necessario: dal cercare di indovinare un segreto al costruire
                    un metodo sistematico, ripetibile e organizzato per restringerlo.
                </p>
                <p>
                    Oggi parliamo di cifratura, gestione delle chiavi, superficie di attacco e automazione con un
                    linguaggio che deve molto a quella stagione. Il principio per cui la sicurezza di un sistema non
                    dovrebbe dipendere dalla segretezza del suo funzionamento, ma dalla robustezza delle chiavi
                    effettivamente in uso, è un'eredità concettuale diretta di quella storia — non un parallelo
                    casuale.
                </p>
            </div>
        </div>
    </section>

    <x-turing.article.callout kicker="In sintesi" title="Un problema di scala, risolto con metodo">
        <p>
            Enigma non fu violata da un singolo colpo di genio, ma da un lavoro collettivo che seppe trasformare uno
            spazio di possibilità enorme in una sequenza gestibile di esclusioni — un metodo che ha attraversato la
            crittografia di guerra fino alla sicurezza informatica di oggi.
        </p>
    </x-turing.article.callout>

    <x-turing.article.callout kicker="Nota editoriale" title="Una rappresentazione stilizzata del processo">
        <p>
            La sequenza che segue non è una trascrizione storica, ma una rappresentazione editoriale sintetica delle
            fasi concettuali del lavoro di decrittazione descritto in questa pagina, dall’analisi dei rotori alla
            priorità assegnata al materiale Ultra:
        </p>
        <code>ANALISI DEI ROTORI</code>
        <code>RICERCA DI UN CRIB</code>
        <code>SPAZIO DELLE CONFIGURAZIONI RIDOTTO DALLA BOMBE</code>
        <code>IPOTESI VERIFICATA</code>
        <code>MATERIALE ULTRA IN PRIORITÀ</code>
        <code>MESSAGGIO DECIFRATO</code>
    </x-turing.article.callout>

    <x-turing.article.cta
        kicker="Continua il percorso"
        title="Torna allo speciale o approfondisci"
        text="Rivedi da dove è partito questo percorso o esplora gli altri approfondimenti dedicati ad Alan Turing."
        :actions="[
            ['label' => 'Torna allo speciale', 'url' => route('turing')],
            ['label' => 'La macchina universale', 'url' => route('turing.computation')],
            ['label' => 'Il gioco dell’imitazione', 'url' => route('turing.intelligence')],
        ]"
    />

</div>
@endsection
