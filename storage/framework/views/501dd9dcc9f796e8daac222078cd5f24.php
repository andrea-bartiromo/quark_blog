<?php $__env->startSection('title', $article ? 'Modifica articolo' : 'Nuovo articolo'); ?>
<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title"><?php echo e($article ? 'Modifica articolo' : 'Nuovo articolo'); ?></h1>
  <a href="<?php echo e(route('admin.articles')); ?>" class="btn btn--outline">← Torna alla lista</a>
</div>

<?php if($errors->any()): ?>
<div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;font-family:var(--font-ui);font-size:.85rem;color:var(--color-accent);">
  <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <p><?php echo e($e); ?></p> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>

<form method="POST"
      action="<?php echo e($article ? route('admin.articles.update',$article) : route('admin.articles.store')); ?>"
      enctype="multipart/form-data"
      style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">
  <?php echo csrf_field(); ?>
  <?php if($article): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.5rem;">

      <div class="form-group">
        <label class="form-label" for="title">Titolo *</label>
        <input class="form-input" type="text" id="title" name="title"
               value="<?php echo e(old('title', $article->title ?? '')); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="excerpt">Sommario (max 300 caratteri)</label>
        <textarea class="form-textarea" id="excerpt" name="excerpt"
                  style="min-height:80px;" maxlength="300"><?php echo e(old('excerpt', $article->excerpt ?? '')); ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label" for="body">Testo articolo *</label>
        <textarea class="form-textarea" id="body" name="body"
                  style="min-height:400px;" required><?php echo e(old('body', $article->body ?? '')); ?></textarea>
        <small style="font-size:.72rem;color:#6b7280;">
          Usa la barra degli strumenti per formattare il testo.
        </small>
      </div>

    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:1rem;">

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Pubblica</div>

      <div class="form-group">
        <label class="form-label" for="status">Stato</label>
        <select class="form-select" id="status" name="status">
          <option value="draft"     <?php echo e(old('status', $article->status ?? '') === 'draft'     ? 'selected' : ''); ?>>Bozza</option>
          <option value="review"    <?php echo e(old('status', $article->status ?? '') === 'review'    ? 'selected' : ''); ?>>In revisione</option>
          <option value="published" <?php echo e(old('status', $article->status ?? '') === 'published' ? 'selected' : ''); ?>>Pubblicato</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-checkbox">
          <input type="checkbox" name="featured" value="1"
                 <?php echo e(old('featured', $article->featured ?? false) ? 'checked' : ''); ?>>
          Articolo in evidenza (hero homepage)
        </label>
      </div>

      <button type="submit" class="btn btn--primary btn--full">
        <?php echo e($article ? 'Salva modifiche' : 'Crea articolo'); ?>

      </button>
    </div>

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Categoria *</div>
      <select class="form-select" name="category" required>
        <option value="">Seleziona categoria…</option>
        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($slug); ?>"
                  <?php echo e(old('category', $article->category ?? '') === $slug ? 'selected' : ''); ?>>
            <?php echo e($label); ?>

          </option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>

    <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.25rem;">
      <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:1rem;">Media</div>

      <div class="form-group">
        <label class="form-label">Immagine copertina</label>

        <?php if(!empty($article?->cover_image)): ?>
        <div style="margin-bottom:.75rem;">
          <img src="<?php echo e(asset('assets/img/'.$article->cover_image)); ?>"
               alt="Copertina attuale"
               style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"
               onerror="this.style.display='none'">
          <div style="font-size:.65rem;color:#6b7280;margin-top:.25rem;">
            Attuale: <?php echo e($article->cover_image); ?>

          </div>
        </div>
        <?php endif; ?>

        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:.5rem;">
          <div style="font-size:.72rem;font-weight:600;color:#111827;margin-bottom:.5rem;">
            Carica nuova immagine
          </div>
          <div style="display:grid;grid-template-columns:1fr auto;gap:.5rem;align-items:center;">
            <input type="file" name="cover_image_upload"
                   accept="image/jpeg,image/png,image/webp"
                   style="font-size:.82rem;padding:.4rem;border:1px solid #e5e7eb;border-radius:6px;background:#fff;width:100%;">
            <div style="font-size:.7rem;color:#6b7280;white-space:nowrap;">
              max 5 MB
            </div>
          </div>
          <div style="font-size:.68rem;color:#6b7280;margin-top:.35rem;">
            Seleziona un file per sostituire l'immagine attuale. Il salvataggio avviene cliccando "Salva modifiche".
          </div>
        </div>

        <input class="form-input" type="text" id="cover_image" name="cover_image"
               placeholder="oppure inserisci il nome del file dalla libreria media"
               value="<?php echo e(old('cover_image', $article->cover_image ?? '')); ?>"
               style="font-size:.82rem;">

        <div style="margin-top:.4rem;">
          <a href="<?php echo e(route('admin.media')); ?>" target="_blank"
             style="font-size:.72rem;color:#0d9488;">
            📁 Libreria media →
          </a>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="read_minutes">Minuti di lettura</label>
        <input class="form-input" type="number" id="read_minutes" name="read_minutes"
               min="1" max="60" value="<?php echo e(old('read_minutes', $article->read_minutes ?? 5)); ?>">
      </div>
    </div>

  </div>
</form>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof tinymce === 'undefined') {
    console.error('TinyMCE non caricato: controlla CSP o CDN.');
    return;
  }

  tinymce.init({
    selector: '#body',
    height: 650,
    menubar: 'file edit view insert format tools table help',
    branding: false,
    promotion: false,
    resize: true,
    plugins: 'advlist autolink lists link image media table code preview fullscreen searchreplace visualblocks wordcount charmap anchor codesample',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat | code preview fullscreen',
    block_formats: 'Paragrafo=p; Titolo 1=h1; Titolo 2=h2; Titolo 3=h3; Citazione=blockquote',
    font_size_formats: '12px 14px 16px 18px 20px 24px 28px 32px',
    convert_urls: false,
    relative_urls: false,
    remove_script_host: false,
    setup: function (editor) {
      editor.on('change keyup', function () {
        editor.save();
      });
    },
    content_style: `
      body {
        font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
        font-size: 16px;
        line-height: 1.8;
        color: #374151;
        padding: 1rem;
      }

      h1, h2, h3 {
        color: #111827;
        font-family: Georgia, serif;
      }

      img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
      }

      blockquote {
        border-left: 3px solid #0d9488;
        margin: 1.5rem 0;
        padding: 0.75rem 1.25rem;
        background: #f0fdfa;
        font-style: italic;
      }

      table {
        border-collapse: collapse;
        width: 100%;
      }

      table td,
      table th {
        border: 1px solid #e5e7eb;
        padding: 0.5rem 0.75rem;
      }

      table th {
        background: #f9fafb;
        font-weight: 600;
      }
    `
  });
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/article-form.blade.php ENDPATH**/ ?>