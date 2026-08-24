{{--
  EDITORIAL RESILIENCE — autosave e recovery locale per il form articolo
  (Admin e Redazione, stesso script). Protegge il lavoro editoriale non
  ancora salvato sul server da chiusura accidentale della scheda, refresh,
  crash del browser, perdita di connessione, session timeout (419) o
  errore di rete durante il submit.

  Cosa protegge: title, excerpt, body, category, secondary_categories,
  status, published_date/time, featured, i campi copertina testuali
  (alt/didascalia/credito/fonte/URL fonte/licenza/nome file dalla
  libreria), SEO/OG/Twitter.

  Cosa NON protegge: il file immagine caricato (cover_image_upload — mai
  serializzabile in modo sicuro/sensato in localStorage: se selezionato,
  ne salviamo solo il nome per avvisare che va riselezionato), il token
  CSRF, qualunque dato di sessione/autenticazione.

  Interamente locale (localStorage del browser): nessuna scrittura sul
  server, nessuna nuova infrastruttura, nessun WebSocket. Vedi
  docs/editor-autosave.md per il design completo.
--}}
<script>
(function () {
  'use strict';

  const form = document.querySelector('[data-editor-autosave-form]');
  if (! form) {
    return;
  }

  const STORAGE_VERSION = 'v1';
  const DEBOUNCE_MS = 1500;
  const TTL_DAYS = 14;
  const TTL_MS = TTL_DAYS * 24 * 60 * 60 * 1000;
  const KEY_PREFIX = 'kairus:editor:';

  const surface = form.dataset.editorSurface || 'unknown';
  const context = form.dataset.editorContext || 'new';
  const serverUpdatedAt = form.dataset.editorServerUpdatedAt || '';
  const userIdMeta = document.querySelector('meta[name="kairus-user-id"]');
  const userId = userIdMeta ? userIdMeta.content : 'anon';

  const draftKey = KEY_PREFIX + STORAGE_VERSION + ':' + surface + ':' + context + ':' + userId;

  // Campi testuali/select semplici (name === id per tutti, verificato sui
  // due form): assenti su Redazione (status, published_date/time,
  // featured) vengono semplicemente saltati, querySelector restituisce
  // null. Volutamente ESCLUSI: cover_image_upload (file, mai
  // serializzato), _token/_method (mai persistiti — FASE 5/18).
  const SIMPLE_FIELD_IDS = [
    'title', 'excerpt', 'body',
    'published_date', 'published_time',
    'cover_image', 'cover_alt', 'cover_caption', 'cover_credit',
    'cover_source', 'cover_source_url', 'cover_license',
    'seo_title', 'seo_description', 'canonical_url', 'robots',
    'og_title', 'og_description', 'og_image',
    'twitter_title', 'twitter_description', 'twitter_image',
  ];

  let storageAvailable = true;
  let hasUnflushedChanges = false;
  let isSubmitting = false;
  let debounceTimer = null;

  function testStorage() {
    try {
      const testKey = KEY_PREFIX + 'quota-test';
      window.localStorage.setItem(testKey, '1');
      window.localStorage.removeItem(testKey);

      return true;
    } catch (e) {
      return false;
    }
  }

  storageAvailable = testStorage();

  // --- Serializzazione form -------------------------------------------

  function fieldValue(id) {
    const field = document.getElementById(id);

    if (! field) {
      return undefined;
    }

    return field.value;
  }

  function serializeForm() {
    const data = {};

    SIMPLE_FIELD_IDS.forEach(function (id) {
      const value = fieldValue(id);

      if (value !== undefined) {
        data[id] = value;
      }
    });

    const categorySelect = form.querySelector('select[name="category"]');
    if (categorySelect) {
      data.category = categorySelect.value;
    }

    const statusSelect = document.getElementById('status');
    if (statusSelect) {
      data.status = statusSelect.value;
    }

    const featuredCheckbox = form.querySelector('input[name="featured"]');
    if (featuredCheckbox) {
      data.featured = featuredCheckbox.checked;
    }

    const secondaryCheckboxes = form.querySelectorAll('input[name="secondary_categories[]"]');
    if (secondaryCheckboxes.length) {
      data.secondary_categories = Array.from(secondaryCheckboxes)
        .filter(function (checkbox) { return checkbox.checked; })
        .map(function (checkbox) { return checkbox.value; });
    }

    // Metadato, non il file stesso (FASE 5/10): permette solo di avvisare
    // "devi riselezionare l'immagine", mai di ripristinarla automaticamente
    // — i browser non permettono di impostare programmaticamente
    // input[type=file].files per ragioni di sicurezza, e serializzare il
    // contenuto binario in base64 in localStorage sarebbe comunque
    // sproporzionato per un file di alcuni MB con quota tipica ~5-10MB.
    const coverUpload = form.querySelector('input[name="cover_image_upload"]');
    data.coverImageFileSelectedName = (coverUpload && coverUpload.files && coverUpload.files[0])
      ? coverUpload.files[0].name
      : null;

    return data;
  }

  function isEmptyDraft(data) {
    return Object.keys(data).every(function (key) {
      const value = data[key];

      if (Array.isArray(value)) {
        return value.length === 0;
      }

      if (typeof value === 'boolean') {
        return value === false;
      }

      return ! value;
    });
  }

  function fieldsEqual(a, b) {
    const keys = new Set(Object.keys(a).concat(Object.keys(b)));

    for (const key of keys) {
      const va = a[key];
      const vb = b[key];

      if (Array.isArray(va) || Array.isArray(vb)) {
        const sa = JSON.stringify((va || []).slice().sort());
        const sb = JSON.stringify((vb || []).slice().sort());
        if (sa !== sb) {
          return false;
        }
        continue;
      }

      if ((va || '') !== (vb || '')) {
        return false;
      }
    }

    return true;
  }

  // --- Storage read/write -----------------------------------------------

  function readDraft() {
    if (! storageAvailable) {
      return null;
    }

    try {
      const raw = window.localStorage.getItem(draftKey);
      if (! raw) {
        return null;
      }

      const parsed = JSON.parse(raw);

      if (! parsed || parsed.version !== STORAGE_VERSION) {
        return null;
      }

      if (Date.now() - parsed.savedAtMs > TTL_MS) {
        window.localStorage.removeItem(draftKey);
        return null;
      }

      return parsed;
    } catch (e) {
      return null;
    }
  }

  function writeDraft() {
    if (! storageAvailable) {
      return;
    }

    const payload = {
      version: STORAGE_VERSION,
      savedAtMs: Date.now(),
      serverUpdatedAtSnapshot: serverUpdatedAt,
      fields: serializeForm(),
    };

    try {
      window.localStorage.setItem(draftKey, JSON.stringify(payload));
      hasUnflushedChanges = false;
      showSavedIndicator(payload.savedAtMs);
    } catch (e) {
      // QuotaExceededError o storage disabilitato a metà sessione (rara,
      // es. modalità privata che revoca l'accesso): non deve MAI rompere
      // l'editor — il salvataggio manuale resta sempre disponibile.
      storageAvailable = false;
      showUnavailableIndicator();
    }
  }

  function clearDraft() {
    if (! storageAvailable) {
      return;
    }

    try {
      window.localStorage.removeItem(draftKey);
    } catch (e) {
      // Nulla da fare: se removeItem fallisce, il TTL la ripulirà comunque.
    }
  }

  // Pulizia opportunistica delle bozze scadute di QUALUNQUE utente/
  // superficie/articolo trovate in questo browser — non solo la chiave
  // corrente (FASE 18: le bozze locali non devono vivere per sempre).
  function sweepExpiredDrafts() {
    if (! storageAvailable) {
      return;
    }

    try {
      const now = Date.now();
      const toRemove = [];

      for (let i = 0; i < window.localStorage.length; i++) {
        const key = window.localStorage.key(i);
        if (! key || key.indexOf(KEY_PREFIX + STORAGE_VERSION + ':') !== 0) {
          continue;
        }

        try {
          const parsed = JSON.parse(window.localStorage.getItem(key));
          if (! parsed || now - parsed.savedAtMs > TTL_MS) {
            toRemove.push(key);
          }
        } catch (e) {
          toRemove.push(key);
        }
      }

      toRemove.forEach(function (key) { window.localStorage.removeItem(key); });
    } catch (e) {
      // Best-effort: un fallimento qui non deve impedire il resto.
    }
  }

  // --- Indicatore "Bozza salvata" (aria-live) ---------------------------

  const statusEl = document.querySelector('[data-editor-autosave-status]');

  function formatTime(ms) {
    return new Date(ms).toLocaleTimeString('it-IT', {hour: '2-digit', minute: '2-digit'});
  }

  function showSavedIndicator(ms) {
    if (! statusEl) {
      return;
    }

    statusEl.textContent = 'Bozza salvata localmente alle ' + formatTime(ms);
    statusEl.hidden = false;
  }

  function showUnavailableIndicator() {
    if (! statusEl) {
      return;
    }

    statusEl.textContent = 'Salvataggio automatico non disponibile in questo browser. Il salvataggio manuale resta comunque disponibile.';
    statusEl.hidden = false;
  }

  // --- Recovery banner ----------------------------------------------------

  const banner = document.querySelector('[data-editor-autosave-banner]');
  const bannerMessage = banner ? banner.querySelector('[data-editor-autosave-banner-message]') : null;
  const restoreButton = banner ? banner.querySelector('[data-editor-autosave-restore]') : null;
  const ignoreButton = banner ? banner.querySelector('[data-editor-autosave-ignore]') : null;
  const discardButton = banner ? banner.querySelector('[data-editor-autosave-discard]') : null;

  function showBanner(draft) {
    if (! banner || ! bannerMessage) {
      return;
    }

    const savedAt = new Date(draft.savedAtMs);
    const when = savedAt.toLocaleDateString('it-IT', {day: 'numeric', month: 'long'})
      + ' alle ' + formatTime(draft.savedAtMs);

    const serverChanged = context !== 'new'
      && serverUpdatedAt
      && draft.serverUpdatedAtSnapshot
      && draft.serverUpdatedAtSnapshot !== serverUpdatedAt;

    bannerMessage.textContent = serverChanged
      ? ('Attenzione: abbiamo trovato modifiche non salvate del ' + when + ', ma l\'articolo sul server è stato modificato da allora (in un\'altra sessione o scheda). Ripristinare la bozza locale sovrascriverebbe quelle modifiche più recenti nel modulo — controlla con attenzione prima di salvare.')
      : ('Abbiamo trovato modifiche non salvate del ' + when + '.');

    banner.hidden = false;
    banner.classList.toggle('editor-autosave-banner--warning', !! serverChanged);

    if (restoreButton) {
      restoreButton.focus();
    }
  }

  function hideBanner() {
    if (! banner) {
      return;
    }

    banner.hidden = true;
  }

  function applyDraftToForm(draft) {
    const fields = draft.fields || {};

    SIMPLE_FIELD_IDS.forEach(function (id) {
      if (! (id in fields)) {
        return;
      }

      const field = document.getElementById(id);
      if (! field) {
        return;
      }

      field.value = fields[id];

      if (id === 'body' && typeof window.tinymce !== 'undefined' && window.tinymce.get('body')) {
        window.tinymce.get('body').setContent(fields[id] || '');
      }
    });

    const categorySelect = form.querySelector('select[name="category"]');
    if (categorySelect && 'category' in fields) {
      categorySelect.value = fields.category;
      categorySelect.dispatchEvent(new Event('change', {bubbles: true}));
    }

    const statusSelect = document.getElementById('status');
    if (statusSelect && 'status' in fields) {
      statusSelect.value = fields.status;
      statusSelect.dispatchEvent(new Event('change', {bubbles: true}));
    }

    const featuredCheckbox = form.querySelector('input[name="featured"]');
    if (featuredCheckbox && 'featured' in fields) {
      featuredCheckbox.checked = !! fields.featured;
    }

    if (Array.isArray(fields.secondary_categories)) {
      form.querySelectorAll('input[name="secondary_categories[]"]').forEach(function (checkbox) {
        checkbox.checked = fields.secondary_categories.indexOf(checkbox.value) !== -1;
        checkbox.dispatchEvent(new Event('change', {bubbles: true}));
      });
    }

    if (fields.coverImageFileSelectedName) {
      const coverUpload = form.querySelector('input[name="cover_image_upload"]');
      if (coverUpload) {
        const hint = document.createElement('p');
        hint.className = 'form-hint';
        hint.setAttribute('data-editor-autosave-file-hint', '');
        hint.textContent = 'La bozza recuperata aveva un\'immagine selezionata (' + fields.coverImageFileSelectedName + '): va riselezionata, i browser non permettono di ripristinarla automaticamente.';
        coverUpload.insertAdjacentElement('afterend', hint);
      }
    }

    if (typeof window.kairusRefreshSeoFallbackPreview === 'function') {
      window.kairusRefreshSeoFallbackPreview();
    }
    if (typeof window.kairusUpdateReadMinutesPreview === 'function') {
      window.kairusUpdateReadMinutesPreview();
    }
  }

  // --- Multi-tab (FASE 11) -----------------------------------------------
  // Nessuna collaborazione realtime: solo un avviso non bloccante se
  // un'altra scheda scrive la STESSA bozza mentre questa è aperta.
  // Last-write-wins, dichiarato esplicitamente qui e in docs/
  // editor-autosave.md — non una vera risoluzione dei conflitti.

  window.addEventListener('storage', function (event) {
    if (event.key !== draftKey || ! statusEl) {
      return;
    }

    statusEl.textContent = 'Questo articolo è stato modificato in un\'altra scheda. L\'ultima bozza salvata (qui o là) sarà quella recuperata al prossimo caricamento.';
    statusEl.hidden = false;
  });

  // --- Wiring cambiamenti --------------------------------------------------

  function scheduleAutosave() {
    hasUnflushedChanges = true;

    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
    }

    debounceTimer = window.setTimeout(writeDraft, DEBOUNCE_MS);
  }

  // Chiamata dal setup TinyMCE di ciascun form (editor.on('change keyup'
  // / 'change input', ...)), esattamente come già avviene per
  // window.kairusRefreshSeoFallbackPreview/kairusUpdateReadMinutesPreview
  // — TinyMCE vive in un iframe separato, i suoi eventi non attraversano
  // mai il listener 'input' delegato sul form sottostante.
  window.kairusNotifyArticleFormChanged = scheduleAutosave;

  form.addEventListener('input', scheduleAutosave);
  form.addEventListener('change', scheduleAutosave);

  // FASE 23 (bonus audit manual-save UX): il pulsante "Crea articolo"/
  // "Salva modifiche" non aveva alcuna protezione da doppio click — un
  // secondo click durante l'invio (rete lenta, TinyMCE che finisce di
  // sincronizzare) poteva inviare due submit. Quick win minimo, stesso
  // listener già necessario per isSubmitting sopra: nessuna richiesta
  // AJAX, il submit nativo procede regolarmente.
  form.addEventListener('submit', function () {
    // Safety net: un submit può avvenire durante la finestra di debounce.
    // Prima di sopprimere beforeunload, sincronizza TinyMCE nel textarea e
    // scrive immediatamente la bozza più recente. Se il POST finisce in 419
    // o in un errore di rete, gli ultimi caratteri restano recuperabili.
    if (typeof window.tinymce !== 'undefined' && window.tinymce.get('body')) {
      window.tinymce.get('body').save();
    }

    if (debounceTimer) {
      window.clearTimeout(debounceTimer);
      debounceTimer = null;
    }

    if (hasUnflushedChanges) {
      writeDraft();
    }

    isSubmitting = true;

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton && ! submitButton.disabled) {
      submitButton.disabled = true;
      submitButton.dataset.originalLabel = submitButton.textContent;
      submitButton.textContent = 'Salvataggio in corso…';
    }
  });

  window.addEventListener('beforeunload', function (event) {
    // Solo se esistono modifiche non ancora scritte nel meccanismo di
    // recovery (FASE 9): se l'ultimo autosave è già passato, i dati sono
    // al sicuro e un popup qui sarebbe rumore. Mai durante un submit
    // intenzionale.
    if (isSubmitting || ! hasUnflushedChanges) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
  });

  // --- Bootstrap -------------------------------------------------------
  //
  // Pulizia dopo salvataggio riuscito (FASE 8): NON qui — create/update
  // reindirizzano entrambi alla lista articoli, mai di nuovo a questo
  // form, quindi il banner di successo con l'id salvato non è mai
  // presente in questa pagina. Quella pulizia vive in
  // partials/kairus-draft-cleanup-script.blade.php, incluso dai layout
  // (admin/redazione) su OGNI pagina di quella superficie — è l'unico
  // punto che intercetta davvero la pagina di destinazione del redirect.

  sweepExpiredDrafts();

  if (! storageAvailable) {
    showUnavailableIndicator();
  } else {
    const draft = readDraft();

    if (draft && ! isEmptyDraft(draft.fields || {})) {
      const currentBaseline = serializeForm();

      if (! fieldsEqual(draft.fields || {}, currentBaseline)) {
        showBanner(draft);
      }
    }
  }

  if (restoreButton) {
    restoreButton.addEventListener('click', function () {
      const draft = readDraft();
      if (draft) {
        applyDraftToForm(draft);
      }
      hideBanner();
      form.querySelector('#title').focus();
    });
  }

  if (ignoreButton) {
    ignoreButton.addEventListener('click', function () {
      // Ignora NON elimina la bozza (FASE 7): resta recuperabile in
      // seguito, semplicemente non la applichiamo ora.
      hideBanner();
    });
  }

  if (discardButton) {
    discardButton.addEventListener('click', function () {
      clearDraft();
      hideBanner();
    });
  }
})();
</script>