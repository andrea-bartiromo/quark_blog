{{--
  EDITORIAL RESILIENCE — pulizia della bozza locale dopo un salvataggio
  riuscito (FASE 8). Incluso dai layout admin/redazione, ma SOLO quando il
  server ha esplicitamente marcato la richiesta corrente come un salvataggio
  di articolo riuscito (session('kairus_draft_cleanup_context'), letto qui
  da #kairus-draft-cleanup-marker) — mai semplicemente perché un flash
  'success' qualunque è presente sulla pagina. Un'azione non correlata
  all'editor articoli (upload media, modifica categoria, aggiornamento
  profilo...) può mostrare il proprio messaggio di successo sulla stessa
  superficie senza mai far scattare questa pulizia.

  store()/update() di entrambi i controller reindirizzano sempre alla lista
  articoli, mai di nuovo al form appena inviato — quindi questo script,
  incluso su ogni pagina della superficie, è l'unico punto che intercetta
  davvero la pagina di destinazione del redirect. Vedi
  partials/article-autosave-script.blade.php per l'autosave vero e proprio
  (quello vive solo sul form).
--}}
<script>
(function () {
  'use strict';

  const marker = document.getElementById('kairus-draft-cleanup-marker');
  if (! marker) {
    return;
  }

  try {
    const surface = marker.dataset.editorSurface;
    const context = marker.dataset.draftCleanupContext;
    if (! surface || ! context) {
      return;
    }

    const userIdMeta = document.querySelector('meta[name="kairus-user-id"]');
    const userId = userIdMeta ? userIdMeta.content : 'anon';
    const prefix = 'kairus:editor:v1:' + surface + ':';

    // context è 'new' dopo una creazione riuscita (svuota solo lo slot
    // "nuovo articolo" di questo utente) oppure l'id numerico dell'arti-
    // colo dopo un aggiornamento riuscito (svuota solo la bozza di QUEL
    // articolo) — mai entrambi indiscriminatamente: un aggiornamento non
    // deve cancellare una bozza "nuovo articolo" ancora in corso in
    // un'altra scheda.
    window.localStorage.removeItem(prefix + context + ':' + userId);
  } catch (e) {
    // localStorage non disponibile: nessuna bozza da pulire, nessun
    // impatto sul resto della pagina.
  }
})();
</script>
