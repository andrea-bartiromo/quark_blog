<?php $__env->startSection('title','Articoli'); ?>
<?php $__env->startSection('content'); ?>

<div class="admin-topbar">
  <h1 class="admin-page-title">Articoli</h1>
  <a href="<?php echo e(route('admin.articles.create')); ?>" class="btn btn--primary">+ Nuovo articolo</a>
</div>

<div class="admin-table-wrap">
  <table class="admin-table">
    <thead>
      <tr>
        <th></th>
        <th>Titolo</th>
        <th>Categoria</th>
        <th>Autore</th>
        <th>Stato</th>
        <th>Views</th>
        <th>Data</th>
        <th>Azioni</th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $articles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <tr>
        <td>
          <img class="article-thumb"
               src="<?php echo e(asset('assets/img/'.($article->cover_image ?? 'placeholder-1.jpg'))); ?>"
               alt="">
        </td>
        <td class="article-title-cell"><?php echo e(Str::limit($article->title,55)); ?></td>
        <td><?php echo e($article->category); ?></td>
        <td><?php echo e($article->author->name); ?></td>
        <td><span class="status status--<?php echo e($article->status); ?>"><?php echo e($article->status); ?></span></td>
        <td><?php echo e(number_format($article->views)); ?></td>
        <td><?php echo e($article->created_at->format('d/m/Y')); ?></td>
        <td>
          <div class="actions">
            <a href="<?php echo e(route('admin.articles.edit', $article)); ?>" class="action-btn">Modifica</a>
            <a href="<?php echo e(route('articolo', $article->slug)); ?>" target="_blank" class="action-btn">Vedi</a>
            <form method="POST" action="<?php echo e(route('admin.articles.destroy', $article)); ?>"
                  onsubmit="return confirm('Eliminare questo articolo?')" style="display:inline;">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button type="submit" class="action-btn action-btn--danger">Elimina</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/articles.blade.php ENDPATH**/ ?>