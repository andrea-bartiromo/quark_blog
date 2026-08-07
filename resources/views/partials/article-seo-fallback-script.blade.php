{{--
  Anteprima live della catena di fallback SEO/Open Graph/Twitter, come
  placeholder — mai come valore scritto nel campo: replica solo in lettura
  ciò che Article::metaTitle()/metaDescription()/metaOgTitle()/... calcolano
  già a runtime (App\Models\Article, sezione "SEO / Meta"), senza duplicare
  quel valore nel DB. "Ripristina automatico" si limita a svuotare il campo
  (torna così, da solo, al fallback) — non scrive nulla.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
  const watchedIds = ['title', 'excerpt', 'body', 'seo_title', 'seo_description'];
  const watchedFields = watchedIds.map((id) => document.getElementById(id)).filter(Boolean);

  if (! document.getElementById('seo_title')) {
    return; // Questa vista non ha la sezione SEO: nulla da fare.
  }

  function fieldValue(id) {
    const field = document.getElementById(id);
    return field ? field.value.trim() : '';
  }

  function stripHtml(html) {
    return new DOMParser().parseFromString(html || '', 'text/html').body.textContent || '';
  }

  // Stessa catena di App\Models\Article::metaDescription(): SEO description
  // esplicita, altrimenti sommario, altrimenti le prime 160 lettere del
  // corpo (tag rimossi).
  function effectiveDescriptionWithoutOverride() {
    const excerpt = fieldValue('excerpt');
    if (excerpt) {
      return excerpt;
    }

    const bodyField = document.getElementById('body');
    if (! bodyField) {
      return '';
    }

    const text = stripHtml(bodyField.value).replace(/\s+/g, ' ').trim();

    return text.slice(0, 160);
  }

  function effectiveTitle() {
    return fieldValue('title');
  }

  function effectiveSeoTitle() {
    return fieldValue('seo_title') || effectiveTitle();
  }

  function effectiveSeoDescription() {
    return fieldValue('seo_description') || effectiveDescriptionWithoutOverride();
  }

  function applyFallback(id, fallbackValue) {
    const field = document.getElementById(id);
    if (! field) {
      return;
    }

    field.placeholder = fallbackValue;

    const resetButton = document.querySelector('[data-reset-for="' + id + '"]');
    if (resetButton) {
      resetButton.hidden = ! field.value.trim();
    }
  }

  function refresh() {
    applyFallback('seo_title', effectiveTitle());
    applyFallback('seo_description', effectiveDescriptionWithoutOverride());
    applyFallback('og_title', effectiveSeoTitle());
    applyFallback('og_description', effectiveSeoDescription());
    applyFallback('twitter_title', effectiveSeoTitle());
    applyFallback('twitter_description', effectiveSeoDescription());
  }

  watchedFields.forEach((field) => field.addEventListener('input', refresh));

  document.querySelectorAll('[data-reset-for]').forEach(function (button) {
    button.addEventListener('click', function () {
      const field = document.getElementById(button.dataset.resetFor);
      if (! field) {
        return;
      }

      field.value = '';
      field.dispatchEvent(new Event('input', {bubbles: true}));
      field.focus();
    });
  });

  refresh();
});
</script>
