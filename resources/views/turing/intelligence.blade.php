@extends('layouts.app')

@section('title', 'Il gioco dell’imitazione e la domanda sulle macchine pensanti — Quark')
@section(
    'description',
    'Il Test di Turing spiegato a partire dal saggio del 1950: cosa misura davvero il gioco dell’imitazione, cosa non misura, e perché la domanda originaria resta rilevante.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <div class="container container--wide">
        <x-turing.article.breadcrumb :items="[['label' => 'Intelligenza']]" />
    </div>

    <x-turing.article.hero
        kicker="Intelligenza"
        title="Il gioco dell’imitazione e la domanda sulle macchine pensanti"
        lead="Nel 1950 Alan Turing propose di sostituire una domanda mal posta — «le macchine possono pensare?» — con un esperimento concreto e osservabile: una conversazione in cui un giudice umano deve distinguere una macchina da una persona."
        image="turing/backgrounds/turing-test-background.webp"
    />

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il punto di partenza"
            title="Una domanda mal posta di proposito"
            text="Nel saggio Computing Machinery and Intelligence, pubblicato su Mind nel 1950, Turing osserva che «le macchine possono pensare?» è una domanda che dipende troppo dal significato ambiguo delle parole «macchina» e «pensare» per avere una risposta utile. Propone quindi di sostituirla con una domanda diversa, più precisa: una macchina può comportarsi, in una conversazione, in modo indistinguibile da un essere umano?"
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="La struttura"
            title="Il gioco dell’imitazione"
            text="Il test — noto anche come «gioco dell’imitazione», dal nome di un precedente gioco di società basato sull’indovinare il genere di un interlocutore per iscritto — coinvolge tre parti: un giudice umano, un interlocutore umano e una macchina. Il giudice comunica con entrambi solo per iscritto, senza vedere chi ha davanti, e deve stabilire quale dei due sia la macchina. Se il giudice sbaglia con una frequenza paragonabile al caso, la macchina ha «superato» il test."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Un equivoco frequente"
            title="Cosa il test misura — e cosa no"
            text="Il test valuta un comportamento linguistico osservabile, non uno stato interno. Superarlo non dimostra che una macchina sia consapevole, comprenda ciò che dice o provi qualcosa: dimostra soltanto che il suo comportamento conversazionale, in quel contesto, non è distinguibile da quello umano. Confondere le due cose — comportamento convincente e coscienza — è uno degli equivoci più comuni nel modo in cui il test viene citato ancora oggi."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Le obiezioni"
            title="Turing rispose per primo ai suoi critici"
            text="Nello stesso saggio, Turing anticipa e discute diverse obiezioni possibili — teologiche, matematiche, legate alla coscienza o alla creatività — senza pretendere di chiuderle definitivamente. Quella discussione è parte integrante della proposta: il test non nasce come un verdetto scientifico assoluto, ma come uno strumento concettuale per rendere discutibile, in modo operativo, una domanda che altrimenti resterebbe puramente filosofica."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Settant’anni dopo"
            title="Una domanda tornata attuale, non risolta"
            text="Con i moderni modelli linguistici, conversazioni difficilmente distinguibili da quelle umane sono diventate un’esperienza comune, e il test di Turing viene citato di continuo nel dibattito pubblico sull’intelligenza artificiale. Il rischio è ripetere lo stesso equivoco di sempre su scala più larga: un modello che supera brillantemente il test resta un sistema che produce linguaggio plausibile, non un caso automaticamente risolto della domanda originaria."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Perché conta ancora"
            title="Un esperimento concettuale, non un verdetto"
            text="Il valore duraturo del test non sta nell’aver fornito una risposta definitiva, ma nell’aver spostato il dibattito da una domanda irrisolvibile a una domanda verificabile. Anche chi lo considera oggi insufficiente come criterio di intelligenza riconosce che quel cambio di prospettiva resta uno dei contributi più influenti di Turing al modo in cui, ancora oggi, si discute di macchine e pensiero."
        />
    </x-turing.article.body>

    <x-turing.article.callout kicker="In sintesi" title="Una domanda resa verificabile">
        <p>
            Il contributo di Turing non è aver risposto alla domanda «le macchine possono pensare?», ma aver
            mostrato come trasformarla in qualcosa che si può osservare, discutere e mettere alla prova.
        </p>
    </x-turing.article.callout>

    <x-turing.article.callout kicker="Nota editoriale" title="Un gioco di società diventato esperimento scientifico">
        <p>
            Il formato del test — indovinare un’identità nascosta attraverso domande scritte — riprende un gioco di
            società già in uso all’epoca. Turing ne adattò le regole a un problema del tutto diverso, trasformando un
            passatempo in uno degli esperimenti concettuali più discussi della storia dell’informatica.
        </p>
    </x-turing.article.callout>

    <x-turing.article.cta
        kicker="Continua il percorso"
        title="Torna allo speciale o approfondisci"
        text="Rivedi da dove è partito questo percorso o esplora gli altri approfondimenti dedicati ad Alan Turing."
        :actions="[
            ['label' => 'Torna allo speciale', 'url' => route('turing')],
            ['label' => 'La macchina universale', 'url' => route('turing.computation')],
            // Percorso letterale, non route('turing.ai'): quel nome e' duplicato
            // in App\Providers\TuringServiceProvider (route /turing/ia), che
            // vince la risoluzione. Stessa scelta gia' fatta in computation.blade.php.
            ['label' => 'L’IA moderna', 'url' => '/turing/ai'],
        ]"
    />

</div>
@endsection
