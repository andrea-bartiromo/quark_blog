{{--
  Markup del banner di recovery e dell'indicatore "Bozza salvata" per il
  form articolo. Nascosti di default (hidden): mostrati via JS da
  partials/article-autosave-script.blade.php solo quando pertinente —
  vedi quel file per il comportamento completo.
--}}
<div data-editor-autosave-banner
     class="editor-autosave-banner"
     role="region"
     aria-live="polite"
     aria-label="Recupero bozza locale"
     hidden
     style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.85rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#92400e;">
  <p data-editor-autosave-banner-message style="margin:0 0 .6rem;"></p>
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <button type="button" data-editor-autosave-restore class="btn btn--primary btn--sm">Ripristina bozza</button>
    <button type="button" data-editor-autosave-ignore class="btn btn--outline btn--sm">Ignora</button>
    <button type="button" data-editor-autosave-discard class="btn btn--outline btn--sm">Elimina bozza</button>
  </div>
</div>

<p data-editor-autosave-status
   aria-live="polite"
   hidden
   style="font-size:.72rem;color:#6b7280;margin:0 0 .75rem;"></p>
