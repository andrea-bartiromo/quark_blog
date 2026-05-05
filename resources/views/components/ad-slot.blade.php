{{--
  Componente slot pubblicitario dinamico — Quark
  Uso: @include('components.ad-slot', ['position' => 'sidebar'])
--}}
@php
  $ads = \App\Models\Ad::forPosition($position ?? 'sidebar');
@endphp

@foreach($ads as $ad)
  @if($ad->active)
    <div class="ad-slot ad-slot--{{ $ad->position }}"
         style="margin:{{ $style ?? '1rem 0' }};">
      {!! $ad->render() !!}
    </div>
  @endif
@endforeach