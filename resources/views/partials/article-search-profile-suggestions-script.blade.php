{{--
  Cantiere 2 (programma "Kairus Organic Discovery"): bozze di
  suggerimento per il profilo di ricerca editoriale, ricavate SOLO
  localmente da titolo/estratto/heading già presenti nella pagina — mai
  una chiamata esterna, mai IA, mai scritte nei campi finché l'editor non
  clicca esplicitamente "Usa" su un singolo suggerimento (stesso principio
  di "mai un valore scritto senza conferma" già seguito da
  partials/article-seo-fallback-script.blade.php per i fallback SEO).
--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  const primaryQueryField = document.getElementById('sp_primary_query');

  if (! primaryQueryField) {
    return; // Questa vista non ha la sezione profilo di ricerca.
  }

  function stripHtml(html) {
    return new DOMParser().parseFromString(html || '', 'text/html').body.textContent || '';
  }

  function headingsFromBody() {
    const bodyField = document.getElementById('body');
    if (! bodyField) {
      return [];
    }

    const doc = new DOMParser().parseFromString(bodyField.value || '', 'text/html');

    return Array.from(doc.querySelectorAll('h2, h3'))
      .map((node) => (node.textContent || '').trim())
      .filter(Boolean);
  }

  function fieldValue(id) {
    const field = document.getElementById(id);
    return field ? field.value.trim() : '';
  }

  function appendLine(textareaId, text) {
    const field = document.getElementById(textareaId);
    if (! field || ! text) {
      return;
    }

    const existing = field.value.split(/\r\n|\r|\n/).map((line) => line.trim()).filter(Boolean);
    if (existing.includes(text)) {
      return; // Già presente: nessun duplicato.
    }

    field.value = existing.concat([text]).join('\n');
    field.dispatchEvent(new Event('input', {bubbles: true}));
  }

  function renderSuggestions() {
    const container = document.getElementById('sp-suggestions');
    const wrapper = document.getElementById('sp-suggestions-wrapper');
    if (! container || ! wrapper) {
      return;
    }

    container.innerHTML = '';

    const title = fieldValue('title');
    const excerpt = fieldValue('excerpt');
    const headings = headingsFromBody();

    if (! title && ! excerpt && headings.length === 0) {
      wrapper.hidden = true;
      return;
    }

    wrapper.hidden = false;

    const items = [];
    if (title) {
      items.push({label: 'Query primaria (dal titolo)', text: title, target: 'sp_primary_query', mode: 'set'});
    }
    if (excerpt) {
      items.push({label: 'Intento primario (dal sommario)', text: excerpt, target: 'sp_primary_intent', mode: 'set'});
    }
    headings.forEach((heading) => {
      const isQuestion = heading.endsWith('?');
      items.push({
        label: isQuestion ? 'Domanda lettore (da heading)' : 'Query secondaria (da heading)',
        text: heading,
        target: isQuestion ? 'sp_reader_questions' : 'sp_secondary_queries',
        mode: 'append',
      });
    });

    items.forEach((item) => {
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;gap:.5rem;align-items:center;font-size:.76rem;color:#374151;margin-bottom:.35rem;';

      const text = document.createElement('span');
      text.textContent = item.label + ': “' + item.text + '”';
      text.style.flex = '1';

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn btn--outline btn--sm';
      button.textContent = 'Usa';
      button.addEventListener('click', function () {
        if (item.mode === 'set') {
          const field = document.getElementById(item.target);
          if (field && ! field.value.trim()) {
            field.value = item.text;
            field.dispatchEvent(new Event('input', {bubbles: true}));
          }
        } else {
          appendLine(item.target, item.text);
        }
      });

      row.appendChild(text);
      row.appendChild(button);
      container.appendChild(row);
    });
  }

  ['title', 'excerpt'].forEach((id) => {
    const field = document.getElementById(id);
    if (field) {
      field.addEventListener('input', renderSuggestions);
    }
  });

  // Esposta globalmente per lo stesso motivo di
  // window.kairusRefreshSeoFallbackPreview: il setup TinyMCE chiama
  // editor.save() (mai un evento 'input' nativo su #body), quindi va
  // richiamata esplicitamente dopo ogni save().
  window.kairusRefreshSearchProfileSuggestions = renderSuggestions;

  renderSuggestions();
});
</script>
