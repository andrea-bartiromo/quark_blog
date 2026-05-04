<?php $__env->startSection('title', config('laboratorio.name').' — '.config('laboratorio.tagline')); ?>

<?php $__env->startSection('content'); ?>


<?php if($featured): ?>
<section class="hero">
  <div class="container">
    <div class="hero-inner">
      <div>
        <div class="hero-eyebrow">
          ⭐ In evidenza
        </div>
        <h1 class="hero-title">
          <?php echo nl2br(e($featured->title)); ?>

        </h1>
        <p class="hero-excerpt"><?php echo e(Str::limit($featured->excerpt, 180)); ?></p>
        <div class="hero-meta">
          <div class="author-chip">
            <div class="author-avatar">
              <?php echo e(mb_substr($featured->author->name, 0, 2)); ?>

            </div>
            <span class="author-name"><?php echo e($featured->author->name); ?></span>
          </div>
          <span class="meta-sep">·</span>
          <span class="meta-date">
            <?php echo e($featured->published_at->locale('it')->isoFormat('D MMM YYYY')); ?>

          </span>
          <span class="meta-sep">·</span>
          <span class="meta-read"><?php echo e($featured->read_minutes); ?> min</span>
          <span class="meta-tag">
            <?php echo e(config('laboratorio.categories.'.$featured->category)); ?>

          </span>
        </div>
        <div style="margin-top:1.25rem;">
          <a href="<?php echo e(route('articolo', $featured->slug)); ?>"
             class="btn btn--primary" style="font-size:.875rem;">
            Leggi l'articolo →
          </a>
        </div>
      </div>
      <div class="hero-img">
        <img src="<?php echo e(asset('assets/img/'.($featured->cover_image ?? 'hero-placeholder.svg'))); ?>"
             alt="<?php echo e($featured->title); ?>" loading="eager">
      </div>
    </div>
  </div>
</section>
<?php endif; ?>


<div class="container">
  <div class="page-layout">

    <section>

      
      <div class="section-head" style="margin-top:0;">
        <h2>Ultimi articoli</h2>
        <a href="<?php echo e(route('notizie')); ?>">Vedi tutti →</a>
      </div>

      <div class="card-grid" style="margin-bottom:2.5rem;">
        <?php $__currentLoopData = $latest; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $article): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(route('articolo', $article->slug)); ?>" class="card" style="text-decoration:none;">
          <div class="card__img">
            <img src="<?php echo e(asset('assets/img/'.($article->cover_image ?? 'placeholder-1.svg'))); ?>"
                 alt="<?php echo e($article->title); ?>" loading="lazy">
            <span class="card__cat-badge">
              <?php echo e(config('laboratorio.categories.'.$article->category)); ?>

            </span>
          </div>
          <div class="card__body">
            <div class="card__title"><?php echo e($article->title); ?></div>
            <div class="card__excerpt"><?php echo e(Str::limit($article->excerpt, 110)); ?></div>
            <div class="card__footer">
              <div class="card__author">
                <div class="author-avatar"><?php echo e(mb_substr($article->author->name, 0, 2)); ?></div>
                <?php echo e(Str::before($article->author->name, ' ')); ?>

              </div>
              <div class="card__stats">
                <span class="card__read"><?php echo e($article->read_minutes); ?> min</span>
              </div>
            </div>
          </div>
        </a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>

      
      <?php $__currentLoopData = $byCategory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $arts): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($arts->count() > 0): ?>
        <div style="margin-bottom:2.5rem;">
          <div class="section-head">
            <h2><?php echo e(config('laboratorio.categories.'.$slug)); ?></h2>
            <a href="<?php echo e(route('categoria', $slug)); ?>">Vedi tutti →</a>
          </div>
          <?php $__currentLoopData = $arts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $art): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a href="<?php echo e(route('articolo', $art->slug)); ?>" class="article-card" style="text-decoration:none;display:flex;">
            <div class="article-card__thumb">
              <img src="<?php echo e(asset('assets/img/'.($art->cover_image ?? 'placeholder-1.svg'))); ?>"
                   alt="<?php echo e($art->title); ?>" loading="lazy">
            </div>
            <div class="article-card__body">
              <span class="article-card__cat">
                <?php echo e(config('laboratorio.categories.'.$art->category)); ?>

              </span>
              <div class="article-card__title"><?php echo e($art->title); ?></div>
              <div class="article-card__meta">
                <span><?php echo e($art->author->name); ?></span>
                <span>·</span>
                <span><?php echo e($art->published_at->locale('it')->isoFormat('D MMM')); ?></span>
                <span>·</span>
                <span><?php echo e($art->read_minutes); ?> min</span>
              </div>
            </div>
          </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    </section>

    
    <aside>
      <?php echo $__env->make('components.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </aside>

  </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/home.blade.php ENDPATH**/ ?>