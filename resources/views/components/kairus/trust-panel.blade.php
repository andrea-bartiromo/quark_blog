{{--
    Kairus Editorial Foundations V1 — Missione 11.
    Pannello Trust: renderizza solo gli slot realmente passati, nessun dato
    inventato (nessuna certificazione, data o fonte di default) e nessuna
    logica di parsing o persistenza — puro assemblaggio di ciò che il
    chiamante fornisce già pronto (fonti, aggiornamenti, rettifiche già
    disponibili altrove nel sito).
--}}
@props([])

@php
    $hasAny = isset($sources) || isset($updated) || isset($corrections) || isset($author);
@endphp

@if($hasAny)
<aside {{ $attributes->class(['kairus-trust-panel']) }} aria-label="Trasparenza editoriale">
    <dl class="kairus-trust-panel__list">
        @isset($sources)
            <div class="kairus-trust-panel__row">
                <dt>Fonti</dt>
                <dd>{{ $sources }}</dd>
            </div>
        @endisset

        @isset($updated)
            <div class="kairus-trust-panel__row">
                <dt>Aggiornamento</dt>
                <dd>{{ $updated }}</dd>
            </div>
        @endisset

        @isset($corrections)
            <div class="kairus-trust-panel__row">
                <dt>Rettifiche</dt>
                <dd>{{ $corrections }}</dd>
            </div>
        @endisset

        @isset($author)
            <div class="kairus-trust-panel__row">
                <dt>Autore</dt>
                <dd>{{ $author }}</dd>
            </div>
        @endisset
    </dl>
</aside>
@endif
