{{--
    Kairus Editorial Foundations V1 — Missione 12.
    Stato vuoto: l'icona è puramente decorativa (aria-hidden, disegnata solo
    in CSS — nessuna dipendenza esterna) e non è mai l'unico veicolo di
    significato: titolo e messaggio testuale sono sempre presenti. Nessun
    bottone viene renderizzato a meno che il chiamante non passi lo slot
    "action".
--}}
@props([
    'title',
    'message' => null,
    'icon' => 'notice',
])

@php
    $icons = ['search', 'path', 'notice', 'error'];
    $resolvedIcon = in_array($icon, $icons, true) ? $icon : 'notice';
    $iconClass = 'kairus-empty-state--'.$resolvedIcon;
@endphp

<div {{ $attributes->class(['kairus-empty-state', $iconClass]) }}>
    <span class="kairus-empty-state__icon" aria-hidden="true"></span>

    <h2 class="kairus-empty-state__title">{{ $title }}</h2>

    @if($message)
        <p class="kairus-empty-state__message">{{ $message }}</p>
    @endif

    @isset($action)
        <div class="kairus-empty-state__action">{{ $action }}</div>
    @endisset
</div>
