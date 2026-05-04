
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $__env->yieldContent('title', config('laboratorio.name').' — '.config('laboratorio.tagline')); ?></title>
  <meta name="description" content="<?php echo $__env->yieldContent('description', config('laboratorio.description', 'Quark è il blog di divulgazione scientifica che spiega la scienza in modo semplice e curioso.')); ?>">
  <meta name="author" content="Andrea Bartiromo">
  <meta name="copyright" content="© <?php echo e(date('Y')); ?> Andrea Bartiromo — Quark">
  <meta name="publisher" content="Quark">
  <link rel="canonical" href="<?php echo e(url()->current()); ?>">
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

  
  <meta property="og:site_name" content="<?php echo e(config('laboratorio.name')); ?>">
  <meta property="og:type"      content="<?php echo $__env->yieldContent('og_type','website'); ?>">
  <meta property="og:title"     content="<?php echo $__env->yieldContent('title', config('laboratorio.name')); ?>">
  <meta property="og:description" content="<?php echo $__env->yieldContent('description', config('laboratorio.tagline')); ?>">
  <meta property="og:url"       content="<?php echo e(url()->current()); ?>">
  <?php echo $__env->yieldContent('og_image'); ?>
  <meta name="twitter:card" content="summary_large_image">

  
  <link rel="icon" type="image/svg+xml" href="<?php echo e(asset('assets/icons/favicon.svg')); ?>">

  
  <link rel="alternate" type="application/rss+xml"
        title="<?php echo e(config('laboratorio.name')); ?> — Feed RSS"
        href="<?php echo e(route('feed')); ?>">

  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Fraunces:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">

  
  

  <?php echo $__env->yieldContent('head'); ?>
</head>
<body>

<?php echo $__env->make('components.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.ticker', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.category-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<main>
  <?php echo $__env->yieldContent('content'); ?>
</main>

<?php echo $__env->make('components.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.cookie-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('components.newsletter-popup', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/layouts/app.blade.php ENDPATH**/ ?>