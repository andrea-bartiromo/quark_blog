{{--
    EDITORIAL TRUST (Missione 27) — editor delle fonti strutturate.

    Vive nella pagina di modifica articolo ma è un <form> SEPARATO, fratello
    (non annidato: sarebbe HTML invalido) del form articolo che si chiude più
    in alto. Conseguenza voluta: un errore di validazione qui ricarica la
    pagina con le sole fonti in errore ripopolate da old(), e il corpo
    dell'articolo salvato resta esattamente quello che era — nessuna perdita
    di contenuto esistente.

    Nessun drag-and-drop: l'ordinamento si fa con due pulsanti "Su"/"Giù"
    raggiungibili da tastiera. Il drag-and-drop sarebbe l'unica interazione
    della pagina impossibile da usare senza mouse.
--}}
@php
    $sourceTypeLabels = \App\Models\ArticleSource::$adminTypeLabels;

    // old() ha la precedenza: dopo un errore di validazione l'utente deve
    // ritrovare ESATTAMENTE quello che aveva scritto, non i valori salvati
    // in tabella (che sono lo stato precedente, cioè il lavoro perso).
    $sourceRows = old('sources');

    if (! is_array($sourceRows)) {
        $sourceRows = $articleSources
            ->map(fn ($source) => [
                'id' => $source->id,
                'title' => $source->title,
                'author_or_org' => $source->author_or_org,
                'url' => $source->url,
                'doi' => $source->doi,
                'source_type' => $source->source_type,
                'published_on' => optional($source->published_on)->toDateString(),
                'accessed_on' => optional($source->accessed_on)->toDateString(),
                'editorial_note' => $source->editorial_note,
            ])
            ->values()
            ->all();
    }

    $sourceRows = array_values($sourceRows);

    // Il blocco legacy: tutto ciò che segue il primo '---' nel body viene
    // ancora renderizzato come "Fonti" nella pagina pubblica
    // (articles/partials/body.blade.php). Finché esiste, convive con le
    // fonti strutturate — e la redazione deve saperlo, altrimenti pubblica
    // due elenchi fonti senza accorgersene.
    $hasLegacySourcesBlock = str_contains((string) $article->body, '---');
    $hasLegacyPrimarySources = filled($article->primary_sources);
@endphp

<section class="admin-card" id="fonti-articolo" style="margin-top:1.5rem;" aria-labelledby="article-sources-title">
  <h3 id="article-sources-title" style="margin-top:0;font-size:.95rem;">Fonti dell'articolo</h3>

  <p style="font-size:.8rem;color:var(--color-ink-muted);margin:.35rem 0 1rem;max-width:70ch;">
    Fonti strutturate mostrate al lettore in fondo all'articolo. Ogni fonte ha bisogno di
    almeno un riferimento verificabile: un URL <code>https://</code> oppure un DOI.
    Il <strong>tipo</strong> viene mostrato pubblicamente solo se lo indichi: lascialo su
    «Non specificato» quando non ne sei certo.
  </p>

  @if($hasLegacySourcesBlock || $hasLegacyPrimarySources)
    <div role="note" style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius);padding:.75rem 1rem;margin-bottom:1rem;font-size:.8rem;line-height:1.55;">
      <strong>Fonti in formato precedente ancora presenti.</strong>
      @if($hasLegacySourcesBlock)
        Il corpo dell'articolo contiene <code>---</code>: il testo che segue viene tuttora pubblicato
        come blocco «Fonti» in coda all'articolo.
      @endif
      @if($hasLegacyPrimarySources)
        Il campo «Fonti» del pannello di verifica editoriale è compilato.
      @endif
      Nessuno dei due viene rimosso automaticamente. Se ricopi quelle fonti qui sotto,
      ricordati di togliere il vecchio blocco dal corpo per non pubblicare due elenchi.
    </div>
  @endif

  @error('sources')
    <p role="alert" style="color:#dc2626;font-size:.8rem;margin-bottom:.75rem;">{{ $message }}</p>
  @enderror

  <form method="POST"
        action="{{ route('admin.articles.sources.update', $article) }}"
        id="article-sources-form">
    @csrf
    @method('PUT')

    <ol id="article-sources-list"
        style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:1rem;">
      @foreach($sourceRows as $index => $row)
        @include('admin.partials.article-source-row', [
            'index' => $index,
            'row' => $row,
            'sourceTypeLabels' => $sourceTypeLabels,
        ])
      @endforeach
    </ol>

    {{-- Stato vuoto reale: nessuna riga, nessun elenco finto da riempire. --}}
    <p id="article-sources-empty"
       style="font-size:.82rem;color:var(--color-ink-muted);margin:.75rem 0;{{ $sourceRows === [] ? '' : 'display:none;' }}">
      Nessuna fonte collegata a questo articolo.
    </p>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;">
      <button type="button" class="action-btn" id="article-sources-add">+ Aggiungi fonte</button>
      <button type="submit" class="btn btn-primary">Salva fonti</button>
    </div>

    <p aria-live="polite" id="article-sources-status" class="sr-only"></p>
  </form>

  {{-- Modello di riga usato da "Aggiungi fonte". <template> non viene
       inviato dal form e non è raggiungibile da tastiera finché non viene
       clonato: nessuna riga fantasma nell'ordine di tabulazione. --}}
  <template id="article-source-template">
    @include('admin.partials.article-source-row', [
        'index' => '__INDEX__',
        'row' => [],
        'sourceTypeLabels' => $sourceTypeLabels,
    ])
  </template>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('article-sources-list');
    var template = document.getElementById('article-source-template');
    var addButton = document.getElementById('article-sources-add');
    var emptyMessage = document.getElementById('article-sources-empty');
    var status = document.getElementById('article-sources-status');

    if (!list || !template || !addButton) {
        // Senza JS (o con JS rotto) il form resta comunque utilizzabile:
        // le righe già presenti si modificano e si salvano normalmente.
        return;
    }

    function announce(message) {
        if (status) {
            status.textContent = message;
        }
    }

    /*
     * Rinumera name="sources[N][...]", gli id/for delle etichette e le
     * legend dopo ogni aggiunta, rimozione o spostamento. Senza questo, il
     * server riceverebbe indici con buchi (che PHP trasforma in un array
     * associativo) e le etichette punterebbero a campi sbagliati per uno
     * screen reader.
     */
    function reindex() {
        var rows = list.querySelectorAll('[data-source-row]');

        rows.forEach(function (row, index) {
            row.setAttribute('data-source-index', String(index));

            var legend = row.querySelector('[data-source-legend]');
            if (legend) {
                legend.textContent = 'Fonte ' + (index + 1);
            }

            row.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/sources\[[^\]]*\]/, 'sources[' + index + ']');
            });

            row.querySelectorAll('[data-field-id]').forEach(function (field) {
                var id = 'source-' + index + '-' + field.getAttribute('data-field-id');
                field.id = id;
                var label = row.querySelector('[data-field-for="' + field.getAttribute('data-field-id') + '"]');
                if (label) {
                    label.setAttribute('for', id);
                }
            });

            var up = row.querySelector('[data-source-up]');
            var down = row.querySelector('[data-source-down]');
            if (up) { up.disabled = index === 0; }
            if (down) { down.disabled = index === rows.length - 1; }
        });

        if (emptyMessage) {
            emptyMessage.style.display = rows.length === 0 ? '' : 'none';
        }
    }

    function updatePreview(row) {
        var preview = row.querySelector('[data-source-preview]');
        if (!preview) { return; }

        function value(name) {
            var field = row.querySelector('[data-field-id="' + name + '"]');
            return field ? field.value.trim() : '';
        }

        var title = value('title');
        var who = value('author_or_org');
        var doi = value('doi');
        var url = value('url');

        if (!title && !who && !doi && !url) {
            preview.textContent = 'L’anteprima compare appena compili la fonte.';
            return;
        }

        var parts = [];
        if (title) { parts.push(title); }
        if (who) { parts.push(who); }

        var reference = '';
        if (doi) {
            // Stessa forma nuda prodotta dal normalizzatore lato server;
            // qui è solo un'anteprima, la normalizzazione autorevole resta
            // in PHP.
            reference = 'doi ' + doi.replace(/^https?:\/\/(dx\.)?doi\.org\//i, '').replace(/^doi:/i, '');
        } else if (url) {
            try {
                reference = new URL(url).hostname.replace(/^www\./i, '');
            } catch (error) {
                reference = '';
            }
        }

        if (reference) { parts.push(reference); }

        preview.textContent = parts.join(' · ') || 'Anteprima non disponibile.';
    }

    function bindRow(row) {
        row.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.addEventListener('input', function () { updatePreview(row); });
            field.addEventListener('change', function () { updatePreview(row); });
        });

        updatePreview(row);
    }

    list.addEventListener('click', function (event) {
        var button = event.target.closest('button');
        if (!button || !list.contains(button)) { return; }

        var row = button.closest('[data-source-row]');
        if (!row) { return; }

        if (button.hasAttribute('data-source-remove')) {
            var label = row.querySelector('[data-field-id="title"]');
            var name = label && label.value.trim() ? label.value.trim() : 'senza titolo';
            row.remove();
            reindex();
            announce('Fonte rimossa: ' + name + '. Ricordati di salvare.');
            addButton.focus();
            return;
        }

        if (button.hasAttribute('data-source-up')) {
            var previous = row.previousElementSibling;
            if (previous) {
                list.insertBefore(row, previous);
                reindex();
                announce('Fonte spostata in alto.');
                // Il focus segue la riga spostata: dopo reindex() il
                // pulsante è lo stesso nodo DOM, quindi non serve
                // ritrovarlo per indice.
                button.focus();
            }
            return;
        }

        if (button.hasAttribute('data-source-down')) {
            var next = row.nextElementSibling;
            if (next) {
                list.insertBefore(next, row);
                reindex();
                announce('Fonte spostata in basso.');
                button.focus();
            }
        }
    });

    addButton.addEventListener('click', function () {
        var fragment = template.content.cloneNode(true);
        var row = fragment.querySelector('[data-source-row]');

        list.appendChild(fragment);
        reindex();
        bindRow(row);

        var firstField = row.querySelector('[data-field-id="title"]');
        if (firstField) { firstField.focus(); }

        announce('Nuova fonte aggiunta.');
    });

    list.querySelectorAll('[data-source-row]').forEach(bindRow);
    reindex();
});
</script>
