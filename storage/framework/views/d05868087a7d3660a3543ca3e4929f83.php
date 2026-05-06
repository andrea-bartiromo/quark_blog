
<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <div>
    <h1 class="admin-page-title">Benvenuto, <?php echo e(auth()->user()->name); ?> 👋</h1>
    <p style="font-size:.82rem;color:#6b7280;margin:0;">
      Area collaboratori di Quark — scrivi articoli e monitorane le performance.
    </p>
  </div>
  <a href="<?php echo e(route('redazione.articles.create')); ?>" class="btn btn--primary">
    ✍️ Scrivi articolo
  </a>
</div>


<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem;">
  <?php $__currentLoopData = [
    ['📝', 'Articoli totali', $stats['total'], '#111827'],
    ['✅', 'Pubblicati', $stats['published'], '#065f46'],
    ['⏳', 'In revisione', $stats['review'], '#854d0e'],
    ['📄', 'Bozze', $stats['draft'], '#6b7280'],
    ['👁', 'Visualizzazioni', number_format($stats['views'], 0, ',', '.'), '#1e40af'],
    ['💬', 'Commenti', $stats['comments'], '#5b21b6'],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $label, $value, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);
              padding:1.1rem;text-align:center;">
    <div style="font-size:1.4rem;margin-bottom:.25rem;"><?php echo e($icon); ?></div>
    <div style="font-size:1.5rem;font-weight:900;color:<?php echo e($color); ?>;"><?php echo e($value); ?></div>
    <div style="font-size:.7rem;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;font-weight:600;"><?php echo e($label); ?></div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<?php if($stats['review'] > 0): ?>
<div style="background:#fef9c3;border:1px solid #fde68a;border-radius:10px;
            padding:.85rem 1.1rem;margin-bottom:1.5rem;font-size:.85rem;color:#854d0e;">
  ⏳ <strong><?php echo e($stats['review']); ?> <?php echo e($stats['review'] === 1 ? 'articolo è' : 'articoli sono'); ?> in attesa di revisione.</strong>
  L'editor li esaminerà e riceverai una email con l'esito.
</div>
<?php endif; ?>


<div style="background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;">
  <div style="padding:.85rem 1.1rem;border-bottom:1px solid #e5e7eb;
              display:flex;justify-content:space-between;align-items:center;">
    <span style="font-size:.7rem;font-weight:700;text-transform:uppercase;
                 letter-spacing:.1em;color:#6b7280;">Ultimi articoli</span>
    <a href="<?php echo e(route('redazione.articles')); ?>" style="font-size:.75rem;color:#0d9488;">Vedi tutti →</a>
  </div>

  <?php $__empty_1 = true; $__currentLoopData = $myArticles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <div style="display:flex;align-items:center;gap:1rem;padding:.85rem 1.1rem;
              border-bottom:1px solid #f3f4f6;">
    <div style="flex:1;min-width:0;">
      <div style="font-size:.85rem;font-weight:600;color:#111827;
                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
        <?php echo e($article->title); ?>

      </div>
      <div style="font-size:.72rem;color:#6b7280;margin-top:.15rem;">
        <?php echo e(config('laboratorio.categories.'.$article->category)); ?>

        · <?php echo e($article->updated_at->diffForHumans()); ?>

      </div>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0;">
      <span class="status status--<?php echo e($article->status); ?>">
        <?php if($article->status === 'published'): ?> Pubblicato
        <?php elseif($article->status === 'review'): ?> In revisione
        <?php else: ?> Bozza <?php endif; ?>
      </span>
      <?php if($article->status !== 'published'): ?>
      <a href="<?php echo e(route('redazione.articles.edit', $article)); ?>"
         class="btn btn--secondary btn--sm">Modifica</a>
      <?php else: ?>
      <a href="<?php echo e(route('articolo', $article->slug)); ?>" target="_blank"
         class="btn btn--secondary btn--sm">Leggi</a>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <div style="padding:2rem;text-align:center;color:#6b7280;">
    <p style="font-size:1.2rem;margin-bottom:.5rem;">✍️</p>
    <p>Non hai ancora scritto nessun articolo.</p>
    <a href="<?php echo e(route('redazione.articles.create')); ?>" class="btn btn--primary" style="margin-top:.75rem;">
      Scrivi il primo articolo
    </a>
  </div>
  <?php endif; ?>
</div>


<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;
            padding:1.25rem;margin-top:1.5rem;">
  <div style="font-size:.82rem;font-weight:700;color:#0f766e;margin-bottom:.75rem;">
    💡 Come funziona il processo di pubblicazione
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem;">
    <?php $__currentLoopData = [
      ['1️⃣', 'Scrivi', 'Crea il tuo articolo con l\'editor. Puoi salvarlo come bozza o inviarlo subito.'],
      ['2️⃣', 'Revisione', 'L\'editor di Quark controlla il contenuto, le fonti e la qualità.'],
      ['3️⃣', 'Pubblicazione', 'Se approvato, ricevi una email e l\'articolo va online. Se richiede modifiche, ti viene rimandato in bozza con note.'],
    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$num, $title, $desc]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="background:#fff;border-radius:8px;padding:.85rem;border:1px solid #99f6e4;">
      <div style="font-size:1.1rem;margin-bottom:.3rem;"><?php echo e($num); ?> <strong style="color:#0f766e;"><?php echo e($title); ?></strong></div>
      <div style="font-size:.75rem;color:#374151;line-height:1.5;"><?php echo e($desc); ?></div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.redazione', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/redazione/dashboard.blade.php ENDPATH**/ ?>