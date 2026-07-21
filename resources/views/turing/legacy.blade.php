@extends('layouts.app')

@section('title', 'L’eredità di Alan Turing — Quark')
@section(
    'description',
    'L’eredità scientifica, tecnologica, istituzionale e culturale di Alan Turing: dalla macchina universale alla persecuzione, dalla riabilitazione alla memoria contemporanea.'
)

@section('head')
    <link rel="stylesheet" href="{{ asset('css/turing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <div class="container container--wide">
        <x-turing.article.breadcrumb :items="[['label' => 'Eredità']]" />
    </div>

    <x-turing.article.hero
        kicker="Eredità"
        title="Il genio inquieto"
        lead="Oltre Enigma e la domanda sull’intelligenza delle macchine, la storia di Alan Turing è anche quella di un uomo perseguitato per ciò che era, riabilitato troppo tardi e diventato, decenni dopo, un simbolo che va oltre l’informatica."
        image="turing-legacy-panel.webp"
    />

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Eredità scientifica"
            title="Una grammatica del calcolo"
            text="Con la macchina universale del 1936, Turing diede alla nozione di algoritmo una forma precisa e generale, mostrando che programma e dati potevano essere trattati nello stesso linguaggio formale. Quella grammatica concettuale è tuttora alla base dell’informatica teorica e del modo in cui pensiamo le macchine programmabili."
        />
        <p>
            <a href="{{ route('turing') }}#macchina-universale">Approfondisci la macchina universale nello speciale</a>
        </p>
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Bletchley Park"
            title="Una guerra vinta anche con la matematica"
            text="Durante la Seconda guerra mondiale, il contributo di Turing alla crittoanalisi di Enigma a Bletchley Park fu decisivo: non il gesto isolato di un genio, ma il risultato di un lavoro collettivo di matematica, ingegneria e organizzazione sotto pressione, che accelerò la nascita del calcolo automatico moderno."
        />
        <p>
            <a href="{{ route('turing.enigma') }}">Leggi la storia completa di Enigma e Bletchley Park</a>
        </p>
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il gioco dell’imitazione"
            title="Una domanda che non si è mai chiusa"
            text="Nel saggio del 1950 Computing Machinery and Intelligence, Turing propose di osservare il comportamento di una macchina in una conversazione invece di definire rigidamente cosa significhi pensare. Il cosiddetto Test di Turing resta un esperimento concettuale, tornato attuale nell’epoca dei modelli linguistici generativi."
        />
        <p>
            <a href="{{ route('turing.ai') }}">Approfondisci il dibattito sull’intelligenza artificiale</a>
        </p>
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="1952"
            title="Una condanna e un prezzo ingiusto"
            text="Nel 1952 Turing fu processato e condannato per omosessualità, allora reato nel Regno Unito. Invece del carcere, accettò un trattamento ormonale imposto dalla condanna e perse l’abilitazione a lavorare per il governo britannico su questioni di sicurezza. Morì nel 1954 a Wilmslow; l’inchiesta dell’epoca ne registrò la causa come suicidio. Quella vicenda va letta nel contesto più ampio della persecuzione delle persone omosessuali nel Regno Unito del dopoguerra, di cui Turing è oggi il caso più noto ma non l’unico."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Il riconoscimento tardivo"
            title="Le scuse, la grazia, la legge"
            text="Nel 2009 il governo britannico ha presentato scuse pubbliche ufficiali per il trattamento subito da Turing. Nel 2013 è arrivata una grazia reale postuma. Nel 2017 è entrata in vigore la legislazione nota informalmente come «Turing Law», che ha esteso il perdono postumo ad altri uomini condannati da norme oggi abrogate per lo stesso reato. Un percorso di riconoscimento progressivo, non un singolo atto risolutivo."
        />
    </x-turing.article.body>

    <x-turing.article.body>
        <x-special.section-header
            variant="panel"
            align="left"
            kicker="Memoria e simbolo"
            title="Da Bletchley Park alla cultura contemporanea"
            text="Il nome di Turing è oggi legato tanto all’informatica quanto alla storia dei diritti delle persone LGBTQ+: è diventato un riferimento nel dibattito pubblico su scienza, identità e giustizia storica, ed è stato oggetto di riconoscimenti istituzionali, opere culturali e iniziative divulgative ben oltre l’ambito accademico in cui aveva lavorato."
        />
    </x-turing.article.body>

    <x-turing.article.callout kicker="In sintesi" title="Perché questa storia resta attuale">
        <p>
            L’eredità di Turing non si esaurisce in un’invenzione tecnica: è anche il racconto di quanto possa costare,
            a una persona reale, essere in anticipo sul proprio tempo — e di quanto lentamente le istituzioni possano
            impiegare a riconoscerlo.
        </p>
    </x-turing.article.callout>

    <x-turing.article.cta
        kicker="Continua il percorso"
        title="Torna allo speciale o approfondisci"
        text="Rivedi da dove è partito questo percorso o esplora gli altri due approfondimenti dedicati ad Alan Turing."
        :actions="[
            ['label' => 'Torna allo speciale', 'url' => route('turing')],
            ['label' => 'Enigma e Bletchley Park', 'url' => route('turing.enigma')],
            ['label' => 'Turing e l’IA moderna', 'url' => route('turing.ai')],
        ]"
    />

</div>
@endsection
