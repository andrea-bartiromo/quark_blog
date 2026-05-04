<?php $__env->startSection('title', 'Verifica editoriale'); ?>

<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title">Verifica editoriale</h1>
  <div style="font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-muted);">
    Controlla che ogni articolo sia verificato sulla fonte primaria prima della pubblicazione
  </div>
</div>


<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
  <?php
    $counts = [
        'unverified'    => $articles->where('verification_status','unverified')->count(),
        'in_progress'   => $articles->where('verification_status','in_progress')->count(),
        'verified'      => $articles->where('verification_status','verified')->count(),
        'needs_update'  => $articles->where('verification_status','needs_update')->count(),
    ];
    $labels = ['unverified' => 'Non verificati', 'in_progress' => 'In verifica', 'verified' => 'Verificati', 'needs_update' => 'Da aggiornare'];
    $colors = ['unverified' => ['#fef2f2','#ef4444'], 'in_progress' => ['#fffbeb','#d97706'], 'verified' => ['#f0fdf4','#16a34a'], 'needs_update' => ['#eef2ff','#4f46e5']];
  ?>

  <?php $__currentLoopData = $counts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status => $count): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div style="background:<?php echo e($colors[$status][0]); ?>;border-radius:var(--radius);padding:1rem;text-align:center;border:1px solid <?php echo e($colors[$status][1]); ?>22;">
    <div style="font-family:var(--font-display);font-size:2rem;font-weight:900;color:<?php echo e($colors[$status][1]); ?>;line-height:1;"><?php echo e($count); ?></div>
    <div style="font-family:var(--font-ui);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:<?php echo e($colors[$status][1]); ?>;margin-top:.25rem;"><?php echo e($labels[$status]); ?></div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<?php $publishedUnverified = $articles->where('status','published')->whereIn('verification_status',['unverified','in_progress'])->count(); ?>
<?php if($publishedUnverified > 0): ?>
<div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:var(--radius);padding:1rem 1.25rem;
            margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem;">
  <span style="font-size:1.2rem;">⚠️</span>
  <div>
    <strong style="font-family:var(--font-ui);font-size:.85rem;color:#dc2626;">
      <?php echo e($publishedUnverified); ?> articoli pubblicati non ancora verificati sulla fonte primaria.
    </strong>
    <p style="font-family:var(--font-ui);font-size:.78rem;color:#7f1d1d;margin:.1rem 0 0;">
      Verifica le fonti prima che il sito vada online. Per ogni articolo, apri la fonte primaria indicata e controlla il dato chiave.
    </p>
  </div>
</div>
<?php endif; ?>


<div style="display:flex;flex-direction:column;gap:.75rem;">
  <?php $__currentLoopData = $articles->sortBy(fn($a) => ['unverified'=>0,'in_progress'=>1,'needs_update'=>2,'verified'=>3][$a->verification_status]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <?php $vc = $colors[$article->verification_status]; ?>
  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);
              border-left:4px solid <?php echo e($vc[1]); ?>;overflow:hidden;">

    <div style="display:grid;grid-template-columns:1fr auto;gap:1rem;padding:1rem 1.25rem;align-items:start;">

      
      <div>
        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;flex-wrap:wrap;">
          <span class="badge badge--<?php echo e($article->category); ?>" style="font-size:.65rem;">
            <?php echo e(config('laboratorio.categories.'.$article->category)); ?>

          </span>
          <span class="status status--<?php echo e($article->status === 'published' ? 'published' : 'draft'); ?>"
                style="font-size:.65rem;"><?php echo e($article->status); ?></span>
          <span style="font-family:var(--font-ui);font-size:.7rem;font-weight:700;
                       text-transform:uppercase;letter-spacing:.06em;
                       color:<?php echo e($vc[1]); ?>;background:<?php echo e($vc[0]); ?>;
                       padding:.15rem .5rem;border-radius:3px;">
            <?php echo e(\App\Models\Article::$verificationLabels[$article->verification_status]); ?>

          </span>
        </div>

        <div style="font-family:var(--font-display);font-size:.95rem;font-weight:700;color:var(--color-ink);margin-bottom:.3rem;">
          <?php echo e($article->title); ?>

        </div>

        <?php if($article->verification_notes): ?>
        <div style="font-family:var(--font-ui);font-size:.78rem;color:var(--color-ink-soft);margin-bottom:.3rem;">
          📋 <?php echo e($article->verification_notes); ?>

        </div>
        <?php endif; ?>

        <?php if($article->primary_sources): ?>
        <div style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);">
          🔗 <strong>Fonti:</strong> <?php echo e($article->primary_sources); ?>

        </div>
        <?php endif; ?>

        <?php if($article->verified_at): ?>
        <div style="font-family:var(--font-ui);font-size:.68rem;color:var(--color-ink-muted);margin-top:.25rem;">
          ✓ Verificato da <?php echo e($article->verified_by); ?> il <?php echo e($article->verified_at->format('d/m/Y H:i')); ?>

        </div>
        <?php endif; ?>
      </div>

      
      <div style="display:flex;gap:.5rem;align-items:center;flex-shrink:0;">
        <a href="<?php echo e(route('articolo', $article->slug)); ?>" target="_blank"
           style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);
                  text-decoration:none;padding:.3rem .5rem;border:1px solid var(--color-border);
                  border-radius:3px;" title="Leggi l'articolo">
          👁
        </a>
        <a href="<?php echo e(route('admin.articles.edit', $article)); ?>"
           style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);
                  text-decoration:none;padding:.3rem .5rem;border:1px solid var(--color-border);
                  border-radius:3px;" title="Modifica articolo">
          ✏
        </a>
      </div>
    </div>

    
    <details style="border-top:1px solid var(--color-border);">
      <summary style="padding:.6rem 1.25rem;font-family:var(--font-ui);font-size:.78rem;
                      font-weight:600;color:var(--color-ink-muted);cursor:pointer;
                      list-style:none;display:flex;align-items:center;gap:.4rem;">
        <span>▸</span> Aggiorna stato verifica
      </summary>
      <div style="padding:1rem 1.25rem;background:var(--color-paper-warm);">
        <form method="POST" action="<?php echo e(route('admin.articles.verify', $article)); ?>">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="font-size:.72rem;">Stato verifica</label>
              <select class="form-select" name="verification_status" style="font-size:.82rem;">
                <?php $__currentLoopData = \App\Models\Article::$verificationLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($val); ?>" <?php echo e($article->verification_status === $val ? 'selected' : ''); ?>>
                    <?php echo e($label); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
              <label class="form-label" style="font-size:.72rem;">Fonti primarie verificate</label>
              <input class="form-input" type="text" name="primary_sources"
                     value="<?php echo e($article->primary_sources); ?>"
                     placeholder="es. terna.it, ANSA 21/01/2026"
                     style="font-size:.78rem;">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:.75rem;">
            <label class="form-label" style="font-size:.72rem;">Note di verifica</label>
            <textarea class="form-textarea" name="verification_notes"
                      style="min-height:60px;font-size:.78rem;"
                      placeholder="Cosa hai verificato, su quale fonte, quando…"><?php echo e($article->verification_notes); ?></textarea>
          </div>
          <button type="submit" class="btn btn--primary" style="font-size:.78rem;">
            Salva stato verifica
          </button>
        </form>
      </div>
    </details>

  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/verification.blade.php ENDPATH**/ ?>