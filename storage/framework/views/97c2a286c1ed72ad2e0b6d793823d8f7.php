
<?php $__env->startSection('title', isset($article) ? 'Modifica articolo' : 'Nuovo articolo'); ?>

<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title"><?php echo e(isset($article) ? 'Modifica articolo' : 'Nuovo articolo'); ?></h1>
  <a href="<?php echo e(route('redazione.articles')); ?>" class="btn btn--outline">← I miei articoli</a>
</div>


<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  ℹ️ Quando salvi, l'articolo viene inviato automaticamente in <strong>revisione all'editor</strong>.
  Riceverai una email quando verrà approvato o se richiede modifiche.
</div>

<?php if($errors->any()): ?>
<div style="background:#fef0f0;border:1px solid #fcd0cc;border-radius:6px;
            padding:.75rem 1rem;margin-bottom:1rem;color:#991b1b;font-size:.85rem;">
  <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?> <p><?php echo e($e); ?></p> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php endif; ?>

<form method="POST"
      action="<?php echo e(isset($article) ? route('redazione.articles.update', $article) : route('redazione.articles.store')); ?>"
      enctype="multipart/form-data"
      style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">
  <?php echo csrf_field(); ?>
  <?php if(isset($article)): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>

  
  <div style="display:flex;flex-direction:column;gap:1rem;">
    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.5rem;">

      <div class="form-group">
        <label class="form-label">Titolo *</label>
        <input class="form-input" type="text" name="title"
               value="<?php echo e(old('title', $article->title ?? '')); ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Sommario (max 300 caratteri)</label>
        <textarea class="form-textarea" name="excerpt"
                  style="min-height:80px;" maxlength="300"><?php echo e(old('excerpt', $article->excerpt ?? '')); ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Testo articolo *</label>
        <textarea class="form-textarea" id="body" name="body"
                  style="min-height:400px;" required><?php echo e(old('body', $article->body ?? '')); ?></textarea>
        <small style="font-size:.72rem;color:#6b7280;">
          Usa la barra degli strumenti per formattare. Inserisci le fonti alla fine del testo dopo "---".
        </small>
      </div>
    </div>
  </div>

  
  <div style="display:flex;flex-direction:column;gap:1rem;">

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Invia</div>
      <button type="submit" class="btn btn--primary btn--full">
        <?php echo e(isset($article) ? '📤 Aggiorna e invia in revisione' : '📤 Invia in revisione'); ?>

      </button>
      <p style="font-size:.7rem;color:#6b7280;margin-top:.5rem;text-align:center;">
        L'editor riceverà una notifica
      </p>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Categoria *</div>
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

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:1rem;">Immagine copertina</div>

      <?php if(!empty($article?->cover_image)): ?>
      <div style="margin-bottom:.75rem;">
        <img src="<?php echo e(asset('assets/img/'.$article->cover_image)); ?>"
             alt="Copertina attuale"
             style="width:100%;max-height:160px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;"
             onerror="this.style.display='none'">
        <div style="font-size:.65rem;color:#6b7280;margin-top:.25rem;"><?php echo e($article->cover_image); ?></div>
      </div>
      <?php endif; ?>

      <input type="file" name="cover_image_upload"
             accept="image/jpeg,image/png,image/webp"
             style="font-size:.78rem;padding:.4rem;border:1px solid #e5e7eb;
                    border-radius:6px;background:#fff;width:100%;margin-bottom:.5rem;">
      <div style="font-size:.68rem;color:#6b7280;">Max 5 MB — JPEG, PNG, WebP</div>
    </div>

    <div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:1.25rem;">
      <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                  letter-spacing:.1em;margin-bottom:.75rem;">Linee guida editoriali</div>
      <?php $__currentLoopData = [
        'Verifica ogni dato sulla fonte primaria',
        'Separa le fonti con --- alla fine del testo',
        'Usa titoli H2 e H3 per strutturare',
        'Aggiungi sempre un sommario chiaro',
        'Evita linguaggio sensazionalistico',
      ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $guideline): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div style="display:flex;gap:.4rem;font-size:.72rem;color:#374151;margin-bottom:.3rem;">
        <span style="color:#0d9488;flex-shrink:0;">✓</span> <?php echo e($guideline); ?>

      </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

  </div>
</form>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
tinymce.init({
  selector: '#body',
  height: 500,
  menubar: false,
  language: 'it',
  promotion: false,
  branding: false,
  plugins: ['anchor','autolink','lists','link','searchreplace','wordcount','fullscreen','preview'],
  toolbar: 'undo redo | blocks | bold italic | bullist numlist | link | removeformat | fullscreen preview',
  block_formats: 'Paragrafo=p; Titolo 2=h2; Titolo 3=h3; Citazione=blockquote',
  content_style: `
    body { font-family:'Plus Jakarta Sans',system-ui,sans-serif; font-size:16px;
           line-height:1.8; color:#374151; max-width:720px; margin:1rem auto; }
    h2 { font-size:1.4rem; color:#111827; margin:1.5rem 0 .5rem; }
    h3 { font-size:1.1rem; color:#111827; margin:1rem 0 .4rem; }
    blockquote { border-left:3px solid #0d9488; margin:1rem 0;
                 padding:.75rem 1rem; background:#f0fdfa; font-style:italic; }
    a { color:#0d9488; }
  `,
  setup: function(editor) {
    editor.on('change input', function() { editor.save(); });
  }
});
</script>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.redazione', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/redazione/article-form.blade.php ENDPATH**/ ?>