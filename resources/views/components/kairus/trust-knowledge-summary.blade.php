{{--
    Cantiere 41 (programma "100 cantieri Kairus", dipende dal Cantiere 39).

    Estrae in un componente riusabile e testabile il markup
    consenso/incertezza/cosa_manca finora duplicato inline in
    resources/views/admin/trust-knowledge/preview.blade.php (Cantiere 40) e
    nel prototipo statico originale (missione B-42,
    resources/views/prototypes/cosa-sappiamo-davvero.blade.php).

    Puramente di presentazione, come x-article.primary-sources e
    x-kairus.trust-panel: accetta stringhe già pronte, non un intero
    TrustKnowledgeStatement — resta quindi testabile senza dover costruire
    un modello Eloquent, e riusabile da qualunque chiamante futuro (incluso
    un eventuale componente pubblico, quando/se il gate B-45 verrà
    soddisfatto — si legga il docblock di TrustKnowledgeStatement) senza
    che questo componente stesso apra nulla di pubblico: nessuna query,
    nessuna rotta, nessun dato reale qui dentro.

    white-space:pre-line preserva gli a capo che l'editor inserisce nelle
    textarea multi-riga di consenso/incertezza/cosa_manca (fix Codex,
    Cantiere 40, PR #598) — mantenuto identico qui per non perdere quella
    correzione nell'estrazione.
--}}
@props([
    'consenso',
    'incertezza',
    'cosaManca' => null,
])

<section class="premium-static-section premium-copy-card">
  <h2>Cosa sappiamo con ragionevole certezza</h2>
  <p style="white-space:pre-line">{{ $consenso }}</p>
</section>

<section class="premium-static-section premium-copy-card">
  <h2>Cosa resta incerto o dibattuto</h2>
  <p style="white-space:pre-line">{{ $incertezza }}</p>
</section>

@if($cosaManca)
<section class="premium-static-section premium-copy-card">
  <h2>Cosa manca / limiti di questa risposta</h2>
  <p style="white-space:pre-line">{{ $cosaManca }}</p>
</section>
@endif
