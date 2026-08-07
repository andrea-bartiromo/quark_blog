{{--
  Collegamenti interni suggeriti (Kairus non modifica mai da solo un
  articolo pubblicato: propone, la redazione decide). Richiede $article
  (può essere null per un articolo non ancora salvato), $linkSuggestions
  (Collection di ArticleLinkSuggestion 'proposed' già persistiti, caricata
  lato server — nessun calcolo pesante al caricamento della pagina, FASE 8)
  e $linkSuggestionRoutePrefix ('admin' o 'redazione').

  "Analizza collegamenti interni" è l'unico punto in cui viene eseguito il
  ranking (ArticleLinkSuggestionService) — mai automaticamente. "Inserisci"
  modifica solo il contenuto nel form (TinyMCE); il salvataggio dell'
  articolo resta un'azione umana esplicita sul pulsante "Salva
  modifiche"/"Crea articolo" del form.
--}}
@php
  $article = $article ?? null;
  $linkSuggestions = $linkSuggestions ?? collect();
@endphp
<div style="background:var(--color-white, #fff);border-radius:var(--radius, 8px);box-shadow:var(--shadow, 0 1px 3px rgba(0,0,0,.08));padding:1.25rem;">
  <div style="font-family:var(--font-ui, inherit);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">
    Collegamenti interni suggeriti
  </div>

  @if(! $article)
    <p class="form-hint" style="margin:0;">
      Disponibile dopo il primo salvataggio dell'articolo.
    </p>
  @else
    <button type="button" id="link-suggestions-analyze-btn" class="btn btn--outline btn--full">
      🔗 Analizza collegamenti interni
    </button>

    <p class="form-hint" style="margin:.5rem 0 0;">
      Kairus propone, non inserisce mai da solo: ogni collegamento va confermato con "Inserisci" e poi salvato con il pulsante di salvataggio del form.
    </p>

    <div id="link-suggestions-status" style="font-size:.72rem;color:#6b7280;margin-top:.5rem;" hidden></div>

    <div id="link-suggestions-list"
         data-analyze-url="{{ route($linkSuggestionRoutePrefix.'.articles.link-suggestions.analyze', $article) }}"
         data-insert-url-template="{{ route($linkSuggestionRoutePrefix.'.articles.link-suggestions.insert', [$article, '__SUGGESTION__']) }}"
         data-ignore-url-template="{{ route($linkSuggestionRoutePrefix.'.articles.link-suggestions.ignore', [$article, '__SUGGESTION__']) }}"
         style="display:flex;flex-direction:column;gap:.6rem;margin-top:.75rem;">
    </div>

    <script type="application/json" id="link-suggestions-initial">{!! json_encode($linkSuggestions->map(fn ($s) => [
      'id' => $s->id,
      'anchor_text' => $s->anchor_text,
      'context_excerpt' => $s->context_excerpt,
      'reason' => $s->reason,
      'confidence_score' => $s->confidence_score,
      'target' => [
        'id' => $s->targetArticle->id,
        'title' => $s->targetArticle->title,
        'url' => route('articolo', $s->targetArticle->slug),
      ],
    ])->values()) !!}</script>
  @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const listEl = document.getElementById('link-suggestions-list');
  if (! listEl) {
    return;
  }

  const analyzeBtn = document.getElementById('link-suggestions-analyze-btn');
  const statusEl = document.getElementById('link-suggestions-status');
  const initialDataEl = document.getElementById('link-suggestions-initial');
  const csrfToken = document.querySelector('input[name="_token"]')?.value || '';

  function setStatus(message, isError) {
    if (! statusEl) {
      return;
    }
    statusEl.hidden = ! message;
    statusEl.textContent = message || '';
    statusEl.style.color = isError ? '#991b1b' : '#6b7280';
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }

  function cardHtml(suggestion) {
    return (
      '<div class="link-suggestion-card" data-suggestion-id="' + suggestion.id + '" ' +
      'style="border:1px solid #e5e7eb;border-radius:8px;padding:.75rem;font-size:.8rem;">' +
        '<div style="font-weight:600;color:#111827;">' +
          '→ ' + escapeHtml(suggestion.target.title) +
        '</div>' +
        '<div style="margin:.35rem 0;color:#374151;">' +
          'Anchor: <span style="background:#f0fdfa;padding:.05rem .35rem;border-radius:4px;">' + escapeHtml(suggestion.anchor_text) + '</span>' +
        '</div>' +
        (suggestion.context_excerpt
          ? '<div style="color:#6b7280;font-style:italic;margin-bottom:.35rem;">' + escapeHtml(suggestion.context_excerpt) + '</div>'
          : ''
        ) +
        '<div style="color:#6b7280;margin-bottom:.5rem;">' +
          escapeHtml(suggestion.reason) + ' · confidenza ' + escapeHtml(suggestion.confidence_score) + '/100' +
        '</div>' +
        '<div style="display:flex;gap:.5rem;">' +
          '<button type="button" class="btn btn--primary" data-link-suggestion-action="insert" style="font-size:.75rem;padding:.4rem .75rem;">Inserisci</button>' +
          '<button type="button" class="btn btn--outline" data-link-suggestion-action="ignore" style="font-size:.75rem;padding:.4rem .75rem;">Ignora</button>' +
        '</div>' +
        '<div class="link-suggestion-card__error" style="color:#991b1b;margin-top:.4rem;" hidden></div>' +
      '</div>'
    );
  }

  function render(suggestions) {
    if (! suggestions.length) {
      listEl.innerHTML = '<p class="form-hint" style="margin:0;">Nessun collegamento suggerito al momento.</p>';
      return;
    }

    listEl.innerHTML = suggestions.map(cardHtml).join('');
  }

  function removeCard(id) {
    const card = listEl.querySelector('[data-suggestion-id="' + id + '"]');
    if (card) {
      card.remove();
    }
    if (! listEl.querySelector('[data-suggestion-id]')) {
      listEl.innerHTML = '<p class="form-hint" style="margin:0;">Nessun collegamento suggerito al momento.</p>';
    }
  }

  if (initialDataEl) {
    try {
      render(JSON.parse(initialDataEl.textContent));
    } catch (e) {
      render([]);
    }
  }

  if (analyzeBtn) {
    analyzeBtn.addEventListener('click', function () {
      analyzeBtn.disabled = true;
      setStatus('Analisi in corso…', false);

      fetch(listEl.dataset.analyzeUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      })
        .then(function (response) {
          if (! response.ok) {
            throw new Error();
          }
          return response.json();
        })
        .then(function (data) {
          render(data.suggestions || []);
          setStatus(
            (data.suggestions || []).length
              ? (data.suggestions.length) + ' collegamento/i suggerito/i.'
              : 'Nessun collegamento pertinente trovato.',
            false
          );
        })
        .catch(function () {
          setStatus('Analisi non riuscita. Riprova.', true);
        })
        .finally(function () {
          analyzeBtn.disabled = false;
        });
    });
  }

  listEl.addEventListener('click', function (event) {
    const button = event.target.closest('[data-link-suggestion-action]');
    if (! button) {
      return;
    }

    const card = button.closest('[data-suggestion-id]');
    const suggestionId = card.dataset.suggestionId;
    const action = button.dataset.linkSuggestionAction;
    const errorEl = card.querySelector('.link-suggestion-card__error');

    card.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    if (errorEl) {
      errorEl.hidden = true;
    }

    if (action === 'insert') {
      const editor = (typeof tinymce !== 'undefined') ? tinymce.get('body') : null;
      const bodyField = document.getElementById('body');
      const currentBody = editor ? editor.getContent() : (bodyField ? bodyField.value : '');

      fetch(listEl.dataset.insertUrlTemplate.replace('__SUGGESTION__', suggestionId), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ body: currentBody }),
      })
        .then(function (response) {
          return response.json().then(function (data) { return { ok: response.ok, data: data }; });
        })
        .then(function (result) {
          if (! result.ok) {
            throw new Error(result.data.message || 'Inserimento non riuscito.');
          }

          if (editor) {
            editor.setContent(result.data.body);
            editor.save();
          } else if (bodyField) {
            bodyField.value = result.data.body;
          }

          removeCard(suggestionId);
          setStatus('Collegamento inserito nel testo — ricordati di salvare l\'articolo.', false);
        })
        .catch(function (error) {
          if (errorEl) {
            errorEl.hidden = false;
            errorEl.textContent = error.message || 'Inserimento non riuscito.';
          }
          card.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        });

      return;
    }

    if (action === 'ignore') {
      fetch(listEl.dataset.ignoreUrlTemplate.replace('__SUGGESTION__', suggestionId), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      })
        .then(function (response) {
          if (! response.ok) {
            throw new Error();
          }
          removeCard(suggestionId);
        })
        .catch(function () {
          if (errorEl) {
            errorEl.hidden = false;
            errorEl.textContent = 'Operazione non riuscita. Riprova.';
          }
          card.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
        });
    }
  });
});
</script>
