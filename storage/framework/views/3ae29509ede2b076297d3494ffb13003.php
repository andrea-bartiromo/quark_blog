<?php $__env->startSection('title','Newsletter'); ?>
<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title">Newsletter</h1>
  <div style="display:flex;gap:.75rem;align-items:center;">
    <span style="font-size:.78rem;color:#6b7280;">
      <?php echo e($total); ?> iscritti · <?php echo e($confirmed); ?> confermati
    </span>
    <a href="<?php echo e(route('admin.newsletter.export')); ?>"
       class="btn btn--secondary" style="font-size:.78rem;">
      ⬇ Esporta CSV
    </a>
  </div>
</div>


<div style="background:#f0fdfa;border:1px solid #99f6e4;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.82rem;color:#0f766e;">
  <strong>📋 GDPR:</strong> Ogni email inviata agli iscritti deve contenere un link di disiscrizione.
  Gli iscritti possono richiedere la cancellazione anche via
  <a href="<?php echo e(route('contatti')); ?>" target="_blank" style="color:#0d9488;">form di contatto</a>.
  Tu puoi eliminarli manualmente da questo pannello.
</div>

<?php if(session('success')): ?>
<div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;
            padding:.85rem 1.1rem;margin-bottom:1rem;color:#065f46;font-size:.875rem;">
  ✅ <?php echo e(session('success')); ?>

</div>
<?php endif; ?>

<div style="background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.08);overflow:hidden;">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Email</th>
        <th>Stato</th>
        <th>Data iscrizione</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $subscribers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr>
        <td style="font-weight:500;"><?php echo e($sub->email); ?></td>
        <td>
          <span class="status status--<?php echo e($sub->confirmed ? 'published' : 'draft'); ?>">
            <?php echo e($sub->confirmed ? '✓ Confermato' : '⏳ In attesa'); ?>

          </span>
        </td>
        <td style="font-size:.82rem;color:#6b7280;">
          <?php echo e($sub->created_at->format('d/m/Y H:i')); ?>

        </td>
        <td>
          <form method="POST" action="<?php echo e(route('admin.newsletter.destroy', $sub)); ?>"
                onsubmit="return confirm('Eliminare <?php echo e($sub->email); ?> dalla newsletter?')">
            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
            <button type="submit" class="btn btn--danger btn--sm">
              Elimina
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <tr>
        <td colspan="4" style="text-align:center;color:#6b7280;padding:2rem;">
          Nessun iscritto ancora.
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if($subscribers->hasPages()): ?>
<div style="margin-top:1rem;">
  <?php echo e($subscribers->links('components.pagination')); ?>

</div>
<?php endif; ?>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/newsletter.blade.php ENDPATH**/ ?>