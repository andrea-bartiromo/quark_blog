
<?php $__env->startSection('title', 'I miei articoli'); ?>

<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title">I miei articoli</h1>
  <a href="<?php echo e(route('redazione.articles.create')); ?>" class="btn btn--primary">✍️ Nuovo articolo</a>
</div>

<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Titolo</th>
        <th>Categoria</th>
        <th>Stato</th>
        <th>Views</th>
        <th>Ultima modifica</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $articles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td>
          <div style="font-weight:600;font-size:.85rem;color:#111827;">
            <?php echo e(Str::limit($article->title, 55)); ?>

          </div>
          <?php if($article->status === 'draft' && $article->verification_notes): ?>
          <div style="font-size:.72rem;color:#854d0e;background:#fef9c3;
                      border-radius:4px;padding:.2rem .5rem;margin-top:.3rem;display:inline-block;">
            📋 Nota editor: <?php echo e(Str::limit($article->verification_notes, 60)); ?>

          </div>
          <?php endif; ?>
        </td>
        <td>
          <span class="badge badge--<?php echo e($article->category); ?>">
            <?php echo e(config('laboratorio.categories.'.$article->category)); ?>

          </span>
        </td>
        <td>
          <span class="status status--<?php echo e($article->status); ?>">
            <?php if($article->status === 'published'): ?> ✅ Pubblicato
            <?php elseif($article->status === 'review'): ?> ⏳ In revisione
            <?php else: ?> 📄 Bozza <?php endif; ?>
          </span>
        </td>
        <td style="font-size:.82rem;color:#6b7280;">
          <?php echo e(number_format($article->views, 0, ',', '.')); ?>

        </td>
        <td style="font-size:.78rem;color:#6b7280;">
          <?php echo e($article->updated_at->diffForHumans()); ?>

        </td>
        <td>
          <div class="actions">
            <?php if($article->status === 'published'): ?>
              <a href="<?php echo e(route('articolo', $article->slug)); ?>" target="_blank"
                 class="btn btn--secondary btn--sm">Leggi</a>
            <?php else: ?>
              <a href="<?php echo e(route('redazione.articles.edit', $article)); ?>"
                 class="btn btn--secondary btn--sm">Modifica</a>
              <form method="POST" action="<?php echo e(route('redazione.articles.destroy', $article)); ?>"
                    onsubmit="return confirm('Eliminare questo articolo?')">
                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                <button type="submit" class="btn btn--danger btn--sm">Elimina</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr>
        <td colspan="6" style="text-align:center;padding:2.5rem;color:#6b7280;">
          <p style="font-size:1.5rem;margin-bottom:.5rem;">✍️</p>
          <p>Non hai ancora scritto nessun articolo.</p>
          <a href="<?php echo e(route('redazione.articles.create')); ?>" class="btn btn--primary" style="margin-top:.75rem;">
            Scrivi il primo
          </a>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if($articles->hasPages()): ?>
<div style="margin-top:1rem;"><?php echo e($articles->links('components.pagination')); ?></div>
<?php endif; ?>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.redazione', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/redazione/articles.blade.php ENDPATH**/ ?>