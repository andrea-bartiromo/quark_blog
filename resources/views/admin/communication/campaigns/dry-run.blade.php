@extends('layouts.admin')
@section('title', 'Dry-run — '.$campaign->title)
@section('content')

@php
  $counters = $report->toArray();
  $sample = array_slice($attempts, 0, 20);
@endphp

<div class="admin-topbar">
  <div>
    <a href="{{ route('admin.comunicazione.campaigns.preflight', $campaign) }}" style="font-size:.8rem;color:#6b7280;text-decoration:none;">← Verifica pre-invio</a>
    <h1 class="admin-page-title" style="margin-top:.25rem;">Esito dry-run</h1>
  </div>
</div>

<div class="admin-card" style="max-width:760px;margin-bottom:1.25rem;background:#ecfdf5;border-color:#a7f3d0;">
  <p style="margin:0;color:#065f46;font-size:.85rem;">
    ✅ Simulazione completata su un <strong>provider fake in memoria</strong> (nessuna rete, nessuna email reale), dentro una transazione database annullata alla fine: nessuna riga di campagna o destinatario è stata realmente modificata da questa esecuzione, anche se i numeri sotto mostrano cosa sarebbe successo con un invio vero adesso. Rieseguibile a piacere con lo stesso esito, salvo cambiamenti reali nel frattempo (es. nuove disiscrizioni).
  </p>
</div>

<div class="admin-card" style="max-width:760px;margin-bottom:1.25rem;">
  <h3 style="margin-top:0;font-size:.95rem;">Riepilogo numerico</h3>
  <dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1rem;margin:0;">
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Eleggibili</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;">{{ number_format($counters['eligible']) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Saltati</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;">{{ number_format($counters['skipped']) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Renderizzati</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;">{{ number_format($counters['rendered']) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Accettati (simulati)</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;color:#059669;">{{ number_format($counters['accepted']) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Falliti temporanei</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;color:#d97706;">{{ number_format($counters['transient_failed']) }}</dd>
    </div>
    <div>
      <dt style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#9ca3af;">Falliti definitivi</dt>
      <dd style="margin:.2rem 0 0;font-weight:700;font-size:1.3rem;color:#dc2626;">{{ number_format($counters['permanent_failed']) }}</dd>
    </div>
  </dl>
</div>

@if(!empty($sample))
  <div class="admin-card" style="max-width:760px;margin-bottom:1.25rem;">
    <h3 style="margin-top:0;font-size:.95rem;">Campione destinatari simulati</h3>
    <p style="color:#9ca3af;font-size:.78rem;margin-top:-.5rem;">
      Primi {{ count($sample) }} di {{ count($attempts) }} tentativo/i registrato/i dal provider fake in memoria.
    </p>
    <table class="admin-table">
      <thead><tr><th>Destinatario</th><th>Oggetto</th></tr></thead>
      <tbody>
        @foreach($sample as $message)
          <tr>
            <td>{{ $message->recipientEmail ?? '(segnaposto)' }}</td>
            <td>{{ $message->subject }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif

<form method="POST" action="{{ route('admin.comunicazione.campaigns.dry-run', $campaign) }}">
  @csrf
  <button type="submit" class="btn btn--secondary">🔁 Esegui di nuovo il dry-run</button>
</form>

@endsection
