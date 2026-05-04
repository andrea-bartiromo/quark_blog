<?php $__env->startSection('title', 'Dashboard'); ?>

<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title">Dashboard</h1>
  <span class="text-muted" style="font-size:.78rem;">
    <?php echo e(now()->locale('it')->isoFormat('dddd D MMMM YYYY')); ?>

  </span>
</div>


<?php if($stats['unverified'] > 0): ?>
<div class="alert-warning" style="background:#fef2f2;border:1px solid #fca5a5;
     border-radius:8px;padding:.85rem 1.1rem;margin-bottom:1.25rem;
     display:flex;align-items:center;gap:.75rem;">
  <span>⚠️</span>
  <div style="flex:1;font-size:.82rem;">
    <strong style="color:#dc2626;"><?php echo e($stats['unverified']); ?> articoli pubblicati</strong>
    <span style="color:#7f1d1d;"> non ancora verificati sulla fonte primaria.</span>
  </div>
  <a href="<?php echo e(route('admin.verification')); ?>"
     style="background:#dc2626;color:#fff;padding:.35rem .85rem;border-radius:4px;
            font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;">
    Verifica ora
  </a>
</div>
<?php endif; ?>


<div class="dash-grid-5" style="display:grid;grid-template-columns:repeat(5,1fr);
     gap:1rem;margin-bottom:1.5rem;">
  <?php $__currentLoopData = [
    ['label'=>'Pubblicati',    'value'=>$stats['published'],                              'icon'=>'📝', 'color'=>'#0d9488', 'href'=>route('admin.articles')],
    ['label'=>'Bozze',         'value'=>$stats['drafts'],                                 'icon'=>'📄', 'color'=>'#6b7280', 'href'=>route('admin.articles')],
    ['label'=>'Newsletter',    'value'=>$stats['newsletter'],                             'icon'=>'📧', 'color'=>'#2563eb', 'href'=>route('admin.newsletter')],
    ['label'=>'Commenti',      'value'=>$stats['comments'],                               'icon'=>'💬', 'color'=>'#d97706', 'href'=>route('admin.comments')],
    ['label'=>'Letture totali','value'=>number_format($stats['total_views'],0,',','.'),  'icon'=>'👁',  'color'=>'#16a34a', 'href'=>'#'],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <a href="<?php echo e($c['href']); ?>"
     style="display:block;background:#fff;border-radius:8px;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1rem;
            text-decoration:none;border-top:3px solid <?php echo e($c['color']); ?>;">
    <div style="font-size:1.3rem;margin-bottom:.3rem;"><?php echo e($c['icon']); ?></div>
    <div style="font-size:1.8rem;font-weight:900;color:<?php echo e($c['color']); ?>;line-height:1;">
      <?php echo e($c['value']); ?>

    </div>
    <div style="font-size:.65rem;font-weight:700;text-transform:uppercase;
                letter-spacing:.07em;color:#6b7280;margin-top:.2rem;">
      <?php echo e($c['label']); ?>

    </div>
  </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">

  
  <div style="background:#fff;border-radius:8px;
              box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;
               letter-spacing:.1em;color:#6b7280;margin:0 0 1rem;">
      🏆 Più letti
    </h2>
    <?php $__currentLoopData = $topArticles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $art): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="display:flex;align-items:center;gap:.6rem;padding:.55rem 0;
                border-bottom:1px solid #f3f4f6;">
      <div style="font-size:1.1rem;font-weight:900;color:#e5e7eb;
                  width:1.3rem;text-align:center;flex-shrink:0;">
        <?php echo e($i + 1); ?>

      </div>
      <div style="flex:1;min-width:0;">
        <a href="<?php echo e(route('articolo', $art->slug)); ?>" target="_blank"
           style="font-size:.78rem;font-weight:600;color:#111827;text-decoration:none;
                  display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?php echo e($art->title); ?>

        </a>
        <span class="badge badge--<?php echo e($art->category); ?>" style="font-size:.57rem;margin-top:.15rem;display:inline-block;">
          <?php echo e(config('laboratorio.categories.'.$art->category)); ?>

        </span>
      </div>
      <span style="font-size:.82rem;font-weight:700;color:#6b7280;flex-shrink:0;">
        <?php echo e(number_format($art->views, 0, ',', '.')); ?>

      </span>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  
  <div style="background:#fff;border-radius:8px;
              box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;
               letter-spacing:.1em;color:#6b7280;margin:0 0 1rem;">
      📊 Distribuzione
    </h2>
    <?php $maxC = $byCategory->max('count') ?: 1; ?>
    <?php $__currentLoopData = $byCategory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="margin-bottom:.7rem;">
      <div style="display:flex;justify-content:space-between;margin-bottom:.2rem;">
        <span style="font-size:.75rem;font-weight:600;color:#111827;">
          <?php echo e(config('laboratorio.categories.'.$cat->category)); ?>

        </span>
        <span style="font-size:.68rem;color:#6b7280;">
          <?php echo e($cat->count); ?> · <?php echo e(number_format($cat->views, 0, ',', '.')); ?>

        </span>
      </div>
      <div style="background:#f3f4f6;border-radius:4px;height:5px;">
        <div style="background:#0d9488;height:100%;border-radius:4px;
                    width:<?php echo e(round(($cat->count/$maxC)*100)); ?>%;"></div>
      </div>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>


<div style="background:#fff;border-radius:8px;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);padding:1.25rem;margin-bottom:1rem;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;">
    <h2 style="font-size:.7rem;font-weight:700;text-transform:uppercase;
               letter-spacing:.1em;color:#6b7280;margin:0;">
      🕐 Attività recente
    </h2>
    <a href="<?php echo e(route('admin.articles')); ?>"
       style="font-size:.72rem;color:#0d9488;text-decoration:none;">
      Vedi tutti →
    </a>
  </div>
  <div style="display:flex;flex-direction:column;gap:.4rem;">
    <?php $__currentLoopData = $recentArticles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $art): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div style="display:flex;align-items:center;gap:.6rem;padding:.45rem .6rem;
                border-radius:4px;background:#f9fafb;">
      <span style="font-size:.85rem;"><?php echo e($art->status === 'published' ? '✅' : '📝'); ?></span>
      <div style="flex:1;min-width:0;">
        <a href="<?php echo e(route('admin.articles.edit', $art)); ?>"
           style="font-size:.78rem;font-weight:600;color:#111827;text-decoration:none;
                  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;">
          <?php echo e($art->title); ?>

        </a>
        <span style="font-size:.63rem;color:#6b7280;">
          <?php echo e($art->author->name); ?> · <?php echo e($art->created_at->locale('it')->diffForHumans()); ?>

          <?php if($art->status==='published' && in_array($art->verification_status,['unverified','in_progress'])): ?>
            · <span style="color:#ef4444;font-weight:700;">⚠ da verificare</span>
          <?php endif; ?>
        </span>
      </div>
      <span class="badge badge--<?php echo e($art->category); ?>" style="font-size:.57rem;flex-shrink:0;">
        <?php echo e(config('laboratorio.categories.'.$art->category)); ?>

      </span>
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>
</div>


<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.65rem;">
  <?php $__currentLoopData = [
    ['label'=>'Nuovo articolo',      'icon'=>'✏️', 'route'=>'admin.articles.create', 'primary'=>true],
    ['label'=>'Verifica editoriale', 'icon'=>'✅', 'route'=>'admin.verification',    'primary'=>false],
    ['label'=>'Commenti',            'icon'=>'💬', 'route'=>'admin.comments',         'primary'=>false],
    ['label'=>'Newsletter',          'icon'=>'📧', 'route'=>'admin.newsletter',       'primary'=>false],
    ['label'=>'Libreria media',      'icon'=>'🖼',  'route'=>'admin.media',            'primary'=>false],
    ['label'=>'Suggerimenti AI',     'icon'=>'🤖', 'route'=>'admin.suggestions',      'primary'=>false],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <a href="<?php echo e(route($a['route'])); ?>"
     style="display:flex;align-items:center;gap:.5rem;padding:.7rem .9rem;
            border-radius:8px;text-decoration:none;font-size:.8rem;font-weight:600;
            box-shadow:0 1px 3px rgba(0,0,0,0.08);
            background:<?php echo e($a['primary'] ? '#0d9488' : '#ffffff'); ?>;
            color:<?php echo e($a['primary'] ? '#ffffff' : '#111827'); ?>;
            border:1px solid <?php echo e($a['primary'] ? '#0d9488' : '#e5e7eb'); ?>;">
    <span><?php echo e($a['icon']); ?></span> <?php echo e($a['label']); ?>

  </a>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/dashboard.blade.php ENDPATH**/ ?>