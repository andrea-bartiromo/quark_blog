<?php $__env->startSection('title', 'Suggerimenti automatici'); ?>

<?php $__env->startSection('content'); ?>


<div style="background:#fffbeb;border:1px solid #fde68a;border-radius:var(--radius);
            padding:.85rem 1.1rem;margin-bottom:1rem;font-family:var(--font-ui);font-size:.82rem;">
  <strong style="color:#92400e;">⚠ Criteri editoriali obbligatori prima di pubblicare una bozza AI:</strong>
  <ol style="margin:.35rem 0 0 1.2rem;padding:0;color:#78350f;line-height:1.7;">
    <li>Apri la fonte originale e verifica i dati chiave sulla fonte primaria</li>
    <li>Controlla che nessun nome, istituto o cifra sia stato inventato dall'AI</li>
    <li>Aggiungi le fonti primarie verificate nel campo apposito</li>
    <li>Dopo la pubblicazione, vai su <a href="<?php echo e(route('admin.verification')); ?>" style="color:var(--color-accent);">Verifica editoriale</a> e segna l'articolo come verificato</li>
  </ol>
</div>

<div class="admin-topbar">
  <h1 class="admin-page-title">Notizie suggerite dall'AI</h1>
  <div style="display:flex;gap:.5rem;align-items:center;">
    <span style="font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-muted);">
      <?php echo e($counts['pending']); ?> in attesa
    </span>
    <form method="POST" action="<?php echo e(route('admin.suggestions.fetch')); ?>" style="display:inline;">
  <?php echo csrf_field(); ?>
  <button type="submit" class="btn btn--primary" style="font-size:.78rem;">
    🔄 Aggiorna ora
  </button>
</form>
  </div>
</div>


<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;">
  <?php $__currentLoopData = [
    ['In attesa', $counts['pending'],   '#fff8e1', '#f57f17'],
    ['Approvati', $counts['approved'],  '#e8f5e9', '#2e7d32'],
    ['Pubblicati',$counts['published'], '#e3f2fd', '#1565c0'],
    ['Scartati',  $counts['rejected'],  '#fce4ec', '#c62828'],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $count, $bg, $color]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div style="background:<?php echo e($bg); ?>;border-radius:var(--radius);padding:1rem;text-align:center;">
    <div style="font-family:var(--font-display);font-size:1.8rem;font-weight:900;color:<?php echo e($color); ?>;line-height:1;"><?php echo e($count); ?></div>
    <div style="font-family:var(--font-ui);font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:<?php echo e($color); ?>;opacity:.8;margin-top:.25rem;"><?php echo e($label); ?></div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<?php if($suggestions->total() === 0): ?>
<div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);padding:2.5rem;text-align:center;max-width:600px;margin:0 auto;">
  <p style="font-size:2rem;margin-bottom:.75rem;">🤖</p>
  <h2 style="font-family:var(--font-display);font-size:1.3rem;font-weight:700;margin-bottom:.5rem;">
    Nessun suggerimento ancora
  </h2>
  <p style="font-size:.9rem;color:var(--color-ink-soft);line-height:1.6;margin-bottom:1.5rem;">
    Il sistema raccoglie automaticamente notizie dai principali feed RSS scientifici italiani
    e genera bozze di articoli usando Claude AI. Clicca "Aggiorna ora" per avviare
    la prima raccolta, oppure attendi la schedulazione automatica (lunedì e giovedì ore 9:00).
  </p>
  <div style="background:var(--color-paper-warm);border-radius:var(--radius);padding:1rem;font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-soft);text-align:left;">
    <strong style="display:block;margin-bottom:.5rem;">Per attivare la generazione AI:</strong>
    Aggiungi la tua API key Anthropic nel file <code style="background:var(--color-border);padding:.1rem .3rem;border-radius:2px;">.env</code>:<br>
    <code style="display:block;margin-top:.35rem;background:var(--color-ink);color:#a8ff78;padding:.5rem .75rem;border-radius:4px;font-size:.82rem;">ANTHROPIC_API_KEY=sk-ant-...</code>
  </div>
</div>
<?php else: ?>


<div style="display:flex;flex-direction:column;gap:1rem;">
  <?php $__currentLoopData = $suggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div style="background:var(--color-white);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;">

    
    <div style="display:flex;align-items:center;justify-content:space-between;
                padding:.75rem 1.25rem;background:var(--color-paper-warm);
                border-bottom:1px solid var(--color-border);">
      <div style="display:flex;align-items:center;gap:.75rem;">
        <span class="badge badge--<?php echo e($suggestion->category); ?>">
          <?php echo e(config('laboratorio.categories.'.$suggestion->category)); ?>

        </span>
        <span style="font-family:var(--font-ui);font-size:.72rem;color:var(--color-ink-muted);">
          📡 <?php echo e($suggestion->source_name); ?> ·
          <?php echo e($suggestion->fetched_at->diffForHumans()); ?>

        </span>
      </div>
      <span class="status status--<?php echo e($suggestion->status === 'published' ? 'published' : ($suggestion->status === 'approved' ? 'published' : 'draft')); ?>"
            style="<?php echo e($suggestion->status === 'rejected' ? 'opacity:.5;' : ''); ?>">
        <?php echo e($suggestion->status); ?>

      </span>
    </div>

    <div style="padding:1.25rem;">

      
      <div style="margin-bottom:1rem;padding:.75rem;background:var(--color-paper-warm);border-radius:var(--radius);">
        <div style="font-family:var(--font-ui);font-size:.65rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;color:var(--color-ink-muted);margin-bottom:.25rem;">
          Fonte originale
        </div>
        <a href="<?php echo e($suggestion->source_url); ?>" target="_blank" rel="noopener"
           style="font-family:var(--font-body);font-size:.9rem;font-weight:600;color:var(--color-accent);
                  text-decoration:underline;text-underline-offset:3px;">
          <?php echo e($suggestion->source_title); ?>

        </a>
        <?php if($suggestion->source_excerpt): ?>
        <p style="font-size:.82rem;color:var(--color-ink-muted);margin-top:.35rem;line-height:1.5;">
          <?php echo e(Str::limit($suggestion->source_excerpt, 200)); ?>

        </p>
        <?php endif; ?>
      </div>

      
      <?php if($suggestion->generated_title): ?>
      <div style="margin-bottom:1rem;">
        <div style="font-family:var(--font-ui);font-size:.65rem;font-weight:700;text-transform:uppercase;
                    letter-spacing:.1em;color:var(--color-ink-muted);margin-bottom:.35rem;">
          ✨ Bozza generata da Claude AI
        </div>
        <div style="font-family:var(--font-display);font-size:1rem;font-weight:700;
                    color:var(--color-ink);margin-bottom:.35rem;">
          <?php echo e($suggestion->generated_title); ?>

        </div>
        <?php if($suggestion->generated_excerpt): ?>
        <p style="font-size:.85rem;color:var(--color-ink-soft);line-height:1.5;margin-bottom:.5rem;
                  font-style:italic;">
          <?php echo e($suggestion->generated_excerpt); ?>

        </p>
        <?php endif; ?>
        <?php if($suggestion->generated_body): ?>
        <p style="font-size:.82rem;color:var(--color-ink-muted);line-height:1.5;">
          <?php echo e(Str::limit($suggestion->generated_body, 300)); ?>

        </p>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-muted);
                  padding:.75rem;background:#fff8e1;border-radius:var(--radius);margin-bottom:1rem;">
        ⚠ Bozza AI non generata — configura ANTHROPIC_API_KEY nel file .env
      </div>
      <?php endif; ?>

      
      <?php if($suggestion->status !== 'rejected' && $suggestion->status !== 'published'): ?>
      <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">

        
        <?php if($suggestion->generated_title): ?>
        <button type="button"
                onclick="document.getElementById('publish-form-<?php echo e($suggestion->id); ?>').classList.toggle('hidden')"
                class="btn btn--primary" style="font-size:.78rem;">
          📝 Crea bozza articolo
        </button>
        <?php endif; ?>

        
        <form method="POST" action="<?php echo e(route('admin.suggestions.approve', $suggestion)); ?>" style="display:inline;">
          <?php echo csrf_field(); ?>
          <button type="submit" class="btn btn--outline"
                  style="color:var(--color-ink);border-color:var(--color-border);font-size:.78rem;">
            ✓ Segna come approvato
          </button>
        </form>

        
        <form method="POST" action="<?php echo e(route('admin.suggestions.destroy', $suggestion)); ?>"
              onsubmit="return confirm('Scartare questo suggerimento?')" style="display:inline;">
          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
          <button type="submit" class="btn btn--ghost"
                  style="color:var(--color-ink-muted);font-size:.78rem;">
            ✕ Scarta
          </button>
        </form>

        
        <a href="<?php echo e($suggestion->source_url); ?>" target="_blank" rel="noopener"
           style="font-family:var(--font-ui);font-size:.75rem;color:var(--color-ink-muted);">
          🔗 Apri fonte originale →
        </a>

      </div>

      
      <?php if($suggestion->generated_title): ?>
      <div id="publish-form-<?php echo e($suggestion->id); ?>" class="hidden"
           style="margin-top:1.25rem;border-top:1px solid var(--color-border);padding-top:1.25rem;">
        <p style="font-family:var(--font-ui);font-size:.82rem;color:var(--color-ink-muted);margin-bottom:1rem;">
          Modifica il titolo e il contenuto prima di creare la bozza. Potrai completare l'articolo dal pannello Articoli.
        </p>
        <form method="POST" action="<?php echo e(route('admin.suggestions.publish', $suggestion)); ?>">
          <?php echo csrf_field(); ?>
          <div class="form-group">
            <label class="form-label">Titolo</label>
            <input class="form-input" type="text" name="title"
                   value="<?php echo e($suggestion->generated_title); ?>" required maxlength="255">
          </div>
          <div class="form-group">
            <label class="form-label">Sommario</label>
            <textarea class="form-textarea" name="excerpt"
                      style="min-height:60px;" maxlength="300"><?php echo e($suggestion->generated_excerpt); ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Testo articolo</label>
            <textarea class="form-textarea" name="body"
                      style="min-height:250px;" required><?php echo e($suggestion->generated_body); ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Categoria</label>
            <select class="form-select" name="category" required>
              <?php $__currentLoopData = config('laboratorio.categories'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($slug); ?>" <?php echo e($suggestion->category === $slug ? 'selected' : ''); ?>>
                  <?php echo e($label); ?>

                </option>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
          </div>
          <button type="submit" class="btn btn--primary">Crea bozza e vai all'editor →</button>
        </form>
      </div>
      <?php endif; ?>

      <?php elseif($suggestion->status === 'published' && $suggestion->article): ?>
      <div style="display:flex;gap:.75rem;">
        <a href="<?php echo e(route('admin.articles.edit', $suggestion->article)); ?>"
           class="btn btn--outline" style="color:var(--color-ink);border-color:var(--color-border);font-size:.78rem;">
          Vai all'articolo →
        </a>
        <a href="<?php echo e(route('articolo', $suggestion->article->slug)); ?>" target="_blank"
           style="font-family:var(--font-ui);font-size:.75rem;color:var(--color-ink-muted);display:flex;align-items:center;">
          Vedi sul sito →
        </a>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>


<div style="margin-top:2rem;">
  <?php echo e($suggestions->links()); ?>

</div>

<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<script>
// Nascondi tutti i form di pubblicazione all'avvio
document.querySelectorAll('[id^="publish-form-"]').forEach(el => el.classList.add('hidden'));
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\Andrea Bartiromo\QUARK_BLOG\resources\views/admin/suggestions.blade.php ENDPATH**/ ?>