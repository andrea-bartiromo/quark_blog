@extends('layouts.app')

@section('title', 'La macchina universale e la teoria della computazione — Quark')
@section(
    'description',
    'Un approfondimento sulla macchina di Turing, sulla nozione formale di algoritmo, sui limiti della computazione e sull’idea moderna di programma.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <div class="container container--wide">
        <x-turing.article.breadcrumb :items="[['label' => 'Computazione']]" />
    </div>

    <x-turing.article.hero
        kicker="Computazione"
        title="La macchina universale e l’idea moderna di programma"
        lead="Nel 1936 Alan Turing propose un modello astratto capace di descrivere con precisione che cosa significhi eseguire un procedimento meccanico. Da quell’esperimento teorico nacque una nuova grammatica del calcolo, destinata a influenzare l’informatica moderna."
        image="turing/backgrounds/turing-universal-machine-background.webp"
    />

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Prima dei computer"
            title="Quando «computer» era una persona"
            text="Prima dell’avvento dei calcolatori elettronici, la parola «computer» indicava spesso una persona incaricata di eseguire calcoli seguendo procedure ben definite. Il problema che Turing affrontò nel 1936 fu rendere quell’idea di procedimento meccanico precisa e formale, indipendente da chi — o cosa — lo eseguisse."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il modello"
            title="La macchina di Turing"
            text="Il modello immagina un nastro teoricamente illimitato, diviso in celle che contengono simboli, e una testina capace di leggere e scrivere una cella alla volta spostandosi lungo il nastro. Il comportamento è governato da un insieme finito di stati interni e da regole di transizione che stabiliscono, passo dopo passo, cosa fare in base al simbolo letto. Non è un progetto hardware da costruire, ma un modello matematico astratto: la sua forza sta nella precisione con cui descrive il concetto di calcolo, non nei materiali con cui potrebbe essere realizzato."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Algoritmi e computabilità"
            title="Ciò che si può calcolare — e ciò che non si può"
            text="Il modello di Turing permette di distinguere in modo rigoroso i problemi calcolabili, risolvibili da una procedura algoritmica ben definita, da quelli che nessun algoritmo generale può risolvere. Il più celebre di questi risultati riguarda il cosiddetto problema dell’arresto: non esiste, in generale, un procedimento meccanico capace di stabilire in anticipo se un altro procedimento arriverà mai a una conclusione. La teoria della computazione non si limita a descrivere cosa le macchine sanno fare: mostra anche l’esistenza di limiti strutturali, non semplicemente pratici."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="L’idea universale"
            title="Una macchina capace di simularne altre"
            text="L’intuizione forse più influente di Turing è che una singola macchina possa simulare il comportamento di qualunque altra, a condizione di riceverne una descrizione come input. Questa idea anticipa la distinzione, oggi scontata, tra macchina fisica, programma e dati: lo stesso dispositivo può eseguire compiti diversissimi semplicemente cambiando le istruzioni che gli vengono fornite. Sarebbe però impreciso dire che Turing abbia «inventato da solo il computer moderno»: la macchina universale è un risultato della logica matematica, non un progetto ingegneristico, e la sua influenza sui computer reali passa attraverso decenni di sviluppi successivi, non un collegamento diretto e immediato."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Dal modello ai computer reali"
            title="Un’influenza reale, non una linea retta"
            text="Le idee di Turing hanno influenzato in profondità l’informatica successiva — dal concetto di programma memorizzato all’elaborazione simbolica, fino alle architetture programmabili e ai linguaggi di programmazione moderni. Va però mantenuta una distinzione netta: la macchina di Turing resta un modello teorico, mentre i computer elettronici costruiti a partire dagli anni Quaranta sono il risultato di scelte ingegneristiche, tecnologiche ed economiche indipendenti, che con quel modello dialogano senza esserne una diretta conseguenza."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="I limiti del calcolo"
            title="Non solo cosa le macchine sanno fare"
            text="Uno dei risultati più importanti della teoria della computazione non riguarda le capacità delle macchine, ma i loro limiti: esistono problemi che nessun procedimento meccanico generale può risolvere, indipendentemente dalla potenza di calcolo disponibile. Questo confine teorico prepara una domanda che tornerà, in una forma diversa, quando Turing stesso si chiederà — pochi anni dopo — se e in che senso una macchina possa dirsi intelligente."
        />
    </x-turing.article.body>

    <x-turing.article.callout kicker="In sintesi" title="Un linguaggio per ragionare sul calcolo">
        <p>
            La forza della macchina di Turing non consiste nel descrivere un singolo dispositivo, ma nel fornire un
            linguaggio generale con cui ragionare su algoritmi, programmi e limiti del calcolo.
        </p>
    </x-turing.article.callout>

    <x-turing.article.callout kicker="Nota editoriale" title="Un testo fondativo, letto con il senno di poi">
        <p>
            Il saggio del 1936 in cui Turing presenta questo modello è oggi considerato uno dei testi fondativi
            dell’informatica teorica — non perché descrivesse una macchina da costruire, ma perché offriva per la
            prima volta una definizione rigorosa di ciò che un procedimento di calcolo può, in linea di principio,
            fare.
        </p>
    </x-turing.article.callout>

    <x-turing.article.cta
        kicker="Continua il percorso"
        title="Torna allo speciale o approfondisci"
        text="Rivedi da dove è partito questo percorso o esplora gli altri approfondimenti dedicati ad Alan Turing."
        :actions="[
            ['label' => 'Torna allo speciale', 'url' => route('turing')],
            ['label' => 'Esplora Enigma', 'url' => route('turing.enigma')],
            // Ora che /turing/intelligence esiste (PR #46), e' la destinazione
            // piu' precisa per questa CTA, al posto del rimando generico a
            // /turing/ai usato provvisoriamente nella PR #45.
            ['label' => 'Dal calcolo all’intelligenza', 'url' => route('turing.intelligence')],
        ]"
    />

</div>
@endsection
