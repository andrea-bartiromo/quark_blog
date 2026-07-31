@extends('layouts.app')

@section('title', 'Speciale Turing — In arrivo — Kairus')
@section(
    'description',
    'Il nuovo Speciale di Kairus dedicato ad Alan Turing è in lavorazione: Enigma e Bletchley Park, la macchina universale, il test di Turing, l’intelligenza artificiale moderna e la sua eredità. Torna presto per scoprirlo.'
)
@section('canonical', url('/turing'))
@section('robots', 'index,follow')
@section('og_type', 'website')
@section('og_image', asset('assets/img/turing/hero/turing-hero.webp'))

@section('head')
<link rel="stylesheet" href="{{ asset('css/turing.css') }}">
<link rel="stylesheet" href="{{ asset('css/special-project.css') }}">
<link rel="stylesheet" href="{{ asset('css/turing-coming-soon.css') }}">
@endsection

@section('content')
<div class="turing-page">

    <section class="turing-section">
        <div class="container container--wide">
            <div class="turing-coming-soon__intro">
                <x-special.section-header
                    level="h1"
                    variant="section"
                    align="center"
                    kicker="Speciale Kairus"
                    title="Alan Turing"
                    text="Un nuovo Speciale editoriale dedicato a Enigma, alla nascita del calcolo automatico e alla domanda — ancora aperta — se le macchine possano pensare. È in lavorazione: i capitoli arriveranno uno alla volta nelle prossime settimane."
                />
                <span class="turing-coming-soon__badge"><span>In lavorazione</span></span>
            </div>
        </div>
    </section>

    <section class="turing-section">
        <div class="container container--wide">
            <x-special.section-header
                variant="section"
                align="center"
                kicker="Cosa racconteremo"
                title="Cinque capitoli, una sola storia"
                text="Non sono ancora pubblicati: qui trovi un’anteprima di ciò che lo Speciale coprirà."
            />

            <x-special.feature-cards
                label="Capitoli in lavorazione dello Speciale Turing"
                :cards="[
                    [
                        'label' => '01',
                        'title' => 'Enigma e Bletchley Park',
                        'text' => 'La guerra dei codici: rotori, la Bombe e il lavoro collettivo che nacque a Bletchley Park.',
                    ],
                    [
                        'label' => '02',
                        'title' => 'La macchina universale',
                        'text' => 'Il modello teorico del 1936 che ha reso più precisa l’idea stessa di algoritmo.',
                    ],
                    [
                        'label' => '03',
                        'title' => 'Il test di Turing',
                        'text' => 'Il gioco dell’imitazione e la domanda sulle macchine pensanti.',
                    ],
                    [
                        'label' => '04',
                        'title' => 'L’intelligenza artificiale moderna',
                        'text' => 'Dai modelli linguistici alla nuova attualità di una domanda posta nel 1950.',
                    ],
                    [
                        'label' => '05',
                        'title' => 'L’eredità',
                        'text' => 'La persecuzione, la riabilitazione e l’impatto culturale di una figura diventata simbolo.',
                    ],
                ]"
            />
        </div>
    </section>

    <section class="turing-section turing-section--final">
        <div class="container container--wide">
            <div class="turing-final-card">
                <x-special.section-header
                    variant="final"
                    align="center"
                    kicker="Resta aggiornato"
                    title="Lo Speciale sarà online a breve"
                    text="Torna a trovarci: pubblicheremo i capitoli non appena saranno pronti."
                />
                <div class="turing-actions turing-actions--center">
                    <a href="{{ route('home') }}">Torna alla home</a>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
