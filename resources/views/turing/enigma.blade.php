@extends('layouts.app')

@section('title', 'Enigma e Bletchley Park — Quark')
@section(
    'description',
    'La guerra invisibile dei codici: Alan Turing, Bletchley Park, la macchina Enigma e la nascita del calcolo moderno.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <div class="container container--wide">
        <x-turing.article.breadcrumb :items="[['label' => 'Enigma']]" />
    </div>

    <x-turing.article.hero
        kicker="Enigma"
        title="Enigma, Ultra e la guerra invisibile"
        lead="Durante la Seconda guerra mondiale, Alan Turing contribuì al lavoro di Bletchley Park per decifrare i messaggi tedeschi prodotti dalla macchina Enigma. Quel lavoro accelerò la nascita del calcolo automatico moderno e cambiò per sempre il rapporto tra matematica, sicurezza e tecnologia."
        image="turing/enigma/turing-enigma-background.webp"
    />

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il problema"
            title="Una guerra combattuta anche con pattern e probabilità"
            text="Ogni messaggio cifrato con Enigma sembrava, a un primo sguardo, una parete opaca. Il lavoro degli analisti a Bletchley Park consisteva nel trasformare quell’opacità in ipotesi verificabili, restringendo ogni giorno da capo lo spazio enorme di combinazioni possibili con cui i tedeschi cifravano le proprie comunicazioni militari."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Rotori e chiavi"
            title="Uno spazio di configurazioni enorme"
            text="Enigma combinava rotori intercambiabili, un cablaggio interno e un pannello di connessioni (il plugboard) con impostazioni cambiate ogni giorno secondo un calendario riservato. La sua sicurezza non dipendeva da un singolo meccanismo segreto, ma dal numero straordinariamente alto di configurazioni che rotori e plugboard potevano assumere insieme: violarla significava innanzitutto restringere, non indovinare, quello spazio di possibilità."
        />

        <x-turing.article.figure
            image="turing/enigma/turing-enigma-panel.webp"
            alt="Ricostruzione editoriale di una macchina Enigma con rotori, plugboard e tastiera in vista"
            caption="Rotori, plugboard e tastiera: i tre elementi che, combinati, generavano lo spazio di chiavi che Bletchley Park doveva restringere ogni giorno."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Crib e indizi"
            title="Piccoli appigli linguistici"
            text="Gli analisti cercavano nei messaggi intercettati frammenti plausibili di testo in chiaro — i cosiddetti crib — a partire da abitudini ricorrenti nelle comunicazioni militari tedesche, come saluti di rito o termini meteorologici ripetuti negli stessi orari. Ogni crib diventava un’ipotesi da trasformare in un test logico e meccanico, capace di escludere rapidamente le configurazioni di Enigma incompatibili con quel frammento."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="La Bombe"
            title="L’automazione entra in gioco"
            text="La Bombe, la macchina elettromeccanica sviluppata a Bletchley Park a partire dal lavoro di Turing e dei suoi colleghi, automatizzava la verifica dei crib su un numero di configurazioni troppo alto per essere controllato a mano in tempo utile. Non decifrava i messaggi da sola: restringeva rapidamente le impostazioni plausibili di Enigma, lasciando agli analisti umani il compito di completare e verificare la decrittazione."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il metodo"
            title="Non forza bruta, ma intelligenza organizzata"
            text="Il punto non era provare tutto: era capire cosa non poteva essere vero. La genialità del lavoro di Bletchley Park stava nel trasformare una montagna di possibilità in una sequenza di esclusioni, accelerata da macchine, turni di lavoro organizzati e disciplina matematica. Questa logica anticipa idee oggi centrali nell’informatica: filtrare segnali, ridurre uno spazio di ricerca enorme, riconoscere pattern ricorrenti e costruire sistemi capaci di amplificare — non sostituire — il ragionamento umano."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Dentro Bletchley"
            title="Una catena di lavoro, non un atto solitario"
            text="La decrittazione a Bletchley Park era un ecosistema, non il gesto isolato di un genio: le comunicazioni radio intercettate arrivavano agli analisti con urgenza crescente, che vi cercavano pattern e ipotesi verificabili a partire da abitudini operative e contesto militare; la Bombe restringeva meccanicamente le configurazioni compatibili con quegli indizi; e il risultato finale — noto con il nome in codice Ultra — doveva ancora arrivare in tempo utile alle strutture militari senza mai rivelare che quel canale era stato violato."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Eredità"
            title="Dalla crittografia alla cybersecurity"
            text="Il lavoro su Enigma è una delle radici culturali della sicurezza informatica contemporanea. Oggi parliamo di cifratura, attacchi, modelli, chiavi e automazione con un linguaggio che deve molto a quella stagione — e allo stesso spostamento di prospettiva che l’ha resa possibile: dal cercare di indovinare un segreto al costruire un metodo sistematico per restringerlo."
        />
    </x-turing.article.body>

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
