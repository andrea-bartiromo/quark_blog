{{--
    Cantiere 31 (programma 100-cantieri Kairus). Workflow "presa in
    carico"/"ignorato" per un singolo finding — stesso pattern già in
    produzione in admin.search-opportunities.index (select che si
    autoinvia via onchange, un'unica riga POST per finding). Riusato
    identicamente nelle sei tabelle di admin.public-health-dashboard così
    da non ripetere sei volte lo stesso markup.
--}}
@props(['domain', 'findingKey', 'status', 'statusOptions'])

<form method="POST" action="{{ route('admin.public-health.update-status') }}">
  @csrf
  <input type="hidden" name="domain" value="{{ $domain }}">
  <input type="hidden" name="finding_key" value="{{ $findingKey }}">
  <label class="sr-only" for="finding-status-{{ Str::slug($findingKey) }}">Stato per {{ $findingKey }}</label>
  <select id="finding-status-{{ Str::slug($findingKey) }}" name="status" class="form-select" onchange="this.form.submit()">
    @foreach($statusOptions as $value => $label)
      <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
    @endforeach
  </select>
</form>
