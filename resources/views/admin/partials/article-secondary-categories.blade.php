@php
  $secondaryCategoryOptions = \App\Models\Category::query()
      ->active()
      ->ordered()
      ->get(['id', 'name', 'slug']);

  $selectedSecondaryIds = collect(old(
      'secondary_categories',
      $article ? $article->secondaryCategories->pluck('id')->all() : []
  ))->map(fn ($id) => (int) $id)->all();
@endphp

<div style="border-top:1px solid #e5e7eb;margin-top:1rem;padding-top:1rem;">
  <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:.35rem;">
    Categorie secondarie
  </div>
  <p style="font-size:.72rem;color:#6b7280;margin:0 0 .75rem;line-height:1.5;">
    Facoltative. Puoi associarne al massimo 2 oltre alla categoria principale.
  </p>

  <div style="display:flex;flex-direction:column;gap:.55rem;" data-secondary-categories>
    @foreach($secondaryCategoryOptions as $secondaryCategory)
      <label class="form-checkbox" style="display:flex;gap:.55rem;align-items:flex-start;">
        <input
          type="checkbox"
          name="secondary_categories[]"
          value="{{ $secondaryCategory->id }}"
          data-category-slug="{{ $secondaryCategory->slug }}"
          {{ in_array($secondaryCategory->id, $selectedSecondaryIds, true) ? 'checked' : '' }}
        >
        <span>
          <strong style="font-size:.82rem;">{{ $secondaryCategory->name }}</strong>
          <span style="display:block;font-size:.66rem;color:#9ca3af;margin-top:.08rem;">{{ $secondaryCategory->slug }}</span>
        </span>
      </label>
    @endforeach
  </div>

  <div data-secondary-category-count style="font-size:.68rem;color:#6b7280;margin-top:.7rem;">0 di 2 selezionate</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const primarySelect = document.querySelector('select[name="category"]');
  const container = document.querySelector('[data-secondary-categories]');
  const counter = document.querySelector('[data-secondary-category-count]');

  if (! primarySelect || ! container) {
    return;
  }

  const checkboxes = Array.from(container.querySelectorAll('input[type="checkbox"]'));

  function refreshSecondaryCategories() {
    const primarySlug = primarySelect.value;
    const checked = checkboxes.filter(function (checkbox) { return checkbox.checked; });

    checkboxes.forEach(function (checkbox) {
      const isPrimary = checkbox.dataset.categorySlug === primarySlug;
      const limitReached = checked.length >= 2 && ! checkbox.checked;

      if (isPrimary && checkbox.checked) {
        checkbox.checked = false;
      }

      checkbox.disabled = isPrimary || limitReached;
      checkbox.closest('label').style.opacity = checkbox.disabled && ! checkbox.checked ? '.48' : '1';
    });

    const selectedCount = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
    if (counter) {
      counter.textContent = selectedCount + ' di 2 selezionate';
    }
  }

  primarySelect.addEventListener('change', refreshSecondaryCategories);
  checkboxes.forEach(function (checkbox) {
    checkbox.addEventListener('change', refreshSecondaryCategories);
  });

  refreshSecondaryCategories();
});
</script>
