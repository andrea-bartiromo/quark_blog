{{--
    Kairus Editorial Foundations V1 — Missione 13.
    Guscio form: non renderizza mai un <form> proprio — action, method,
    CSRF, validazione e consenso restano interamente nel <form> reale che il
    chiamante mette nello slot "form". Fieldset, label e messaggi di errore
    sono responsabilità del chiamante: questo componente fornisce solo
    intestazione, layout e uno slot "aside" facoltativo.
--}}
@props([
    'title',
    'lead' => null,
    'status' => 'default',
])

@php
    $statuses = ['default', 'success', 'error'];
    $resolvedStatus = in_array($status, $statuses, true) ? $status : 'default';
    $statusClass = 'kairus-form-shell--'.$resolvedStatus;
@endphp

<section {{ $attributes->class(['kairus-form-shell', $statusClass]) }}>
    <div class="kairus-form-shell__intro">
        <h2 class="kairus-form-shell__title">{{ $title }}</h2>

        @if($lead)
            <p class="kairus-form-shell__lead">{{ $lead }}</p>
        @endif
    </div>

    <div @class(['kairus-form-shell__layout', 'kairus-form-shell__layout--with-aside' => isset($aside)])>
        <div class="kairus-form-shell__form">
            {{ $form ?? '' }}
        </div>

        @isset($aside)
            <aside class="kairus-form-shell__aside">{{ $aside }}</aside>
        @endisset
    </div>
</section>
