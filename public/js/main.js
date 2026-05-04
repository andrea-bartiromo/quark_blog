/* ============================================================
   IL LABORATORIO — main.js
   Moduli: Nav, Newsletter popup, Cookie bar, Ticker, Commenti
   ============================================================ */

'use strict';

/* ── Utility ───────────────────────────────────────────────── */
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];
const on = (el, ev, fn) => el && el.addEventListener(ev, fn);

/* ── 1. NAV MOBILE ─────────────────────────────────────────── */
function initMobileNav() {
  const toggle = $('.nav-toggle');
  const nav    = $('.main-nav');
  if (!toggle || !nav) return;

  on(toggle, 'click', () => {
    const open = nav.classList.toggle('open');
    toggle.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open);
    document.body.style.overflow = open ? 'hidden' : '';
  });

  // Chiudi cliccando fuori
  on(document, 'click', (e) => {
    if (!toggle.contains(e.target) && !nav.contains(e.target)) {
      nav.classList.remove('open');
      toggle.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
      document.body.style.overflow = '';
    }
  });

  // Marca link attivo
  const path = location.pathname.split('/').pop();
  $$('.main-nav a').forEach(a => {
    const href = a.getAttribute('href').split('/').pop();
    if (href === path || (path === '' && href === 'index.html')) {
      a.classList.add('active');
    }
  });
}

/* ── 2. POPUP NEWSLETTER ───────────────────────────────────── */
function initNewsletterPopup() {
  const overlay = $('#newsletter-overlay');
  if (!overlay) return;

  // Mostra dopo 8s se non già visto
  const seen = localStorage.getItem('nl-popup-seen');
  if (!seen) {
    setTimeout(() => overlay.classList.remove('hidden'), 8000);
  }

  // Chiusura
  const closeBtn = $('.popup__close', overlay);
  on(closeBtn, 'click', closePopup);
  on(overlay, 'click', (e) => { if (e.target === overlay) closePopup(); });

  function closePopup() {
    overlay.classList.add('hidden');
    localStorage.setItem('nl-popup-seen', '1');
  }

  // Pulsante "Newsletter" nell'header
  const headerBtn = $('.btn-newsletter');
  on(headerBtn, 'click', () => {
    overlay.classList.remove('hidden');
    localStorage.removeItem('nl-popup-seen'); // Reset
  });

  // Form con doppia conferma (campo honeypot + validazione)
  const form = $('#newsletter-form');
  on(form, 'submit', handleNewsletterSubmit);
}

async function handleNewsletterSubmit(e) {
  e.preventDefault();
  const form = e.target;

  // Anti-spam: honeypot (campo nascosto deve restare vuoto)
  const honeypot = form.querySelector('[name="website"]');
  if (honeypot && honeypot.value !== '') {
    // Bot rilevato — fallimento silenzioso
    showNewsletterSuccess(form);
    return;
  }

  const email = form.querySelector('[name="email"]').value.trim();
  if (!isValidEmail(email)) {
    showFormError(form, 'Inserisci un indirizzo email valido.');
    return;
  }

  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Invio in corso…';

  try {
    /* 
      Qui integri la tua piattaforma email (Mailchimp, Brevo, ecc.)
      Esempio endpoint Brevo/Sendinblue:
      
      await fetch('https://api.brevo.com/v3/contacts', {
        method: 'POST',
        headers: {
          'api-key': 'TUA_API_KEY',
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          email: email,
          listIds: [1],
          updateEnabled: true
        })
      });
    */

    // Simulazione successo (rimuovere in produzione)
    await new Promise(r => setTimeout(r, 800));
    showNewsletterSuccess(form);

  } catch (err) {
    btn.disabled = false;
    btn.textContent = 'Iscriviti';
    showFormError(form, 'Errore di rete. Riprova tra poco.');
  }
}

function showNewsletterSuccess(form) {
  form.innerHTML = `
    <div style="text-align:center; padding: 1rem 0;">
      <div style="font-size:2.5rem; margin-bottom:0.5rem;">✉️</div>
      <h3 style="font-family:var(--font-display); margin-bottom:0.5rem;">Quasi fatto!</h3>
      <p style="font-size:0.9rem; color:var(--ink-soft);">
        Controlla la tua email e <strong>conferma l'iscrizione</strong> 
        (doppia conferma) per ricevere la nostra newsletter.
      </p>
    </div>
  `;
}

function showFormError(form, msg) {
  let errEl = form.querySelector('.form-error');
  if (!errEl) {
    errEl = document.createElement('p');
    errEl.className = 'form-error';
    errEl.style.cssText = 'color:var(--accent); font-size:0.82rem; margin-top:0.25rem; font-family:var(--font-ui);';
    form.appendChild(errEl);
  }
  errEl.textContent = msg;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/* ── 3. COOKIE BAR ─────────────────────────────────────────── */
function initCookieBar() {
  const bar = $('#cookie-bar');
  if (!bar) return;

  if (localStorage.getItem('cookie-accepted')) {
    bar.classList.add('hidden');
    return;
  }

  const acceptBtn = $('#cookie-accept');
  const rejectBtn = $('#cookie-reject');

  on(acceptBtn, 'click', () => {
    localStorage.setItem('cookie-accepted', 'all');
    bar.classList.add('hidden');
    // Attiva Google Analytics / AdSense qui
    // initAnalytics();
    // initAdSense();
  });

  on(rejectBtn, 'click', () => {
    localStorage.setItem('cookie-accepted', 'essential');
    bar.classList.add('hidden');
  });
}

/* ── 4. TICKER NEWS ────────────────────────────────────────── */
function initTicker() {
  const ticker = $('.ticker__items');
  if (!ticker) return;

  // Duplica elementi per loop seamless
  const clone = ticker.cloneNode(true);
  ticker.parentNode.appendChild(clone);

  // Pausa su hover
  on(ticker.parentNode, 'mouseenter', () => {
    ticker.style.animationPlayState = 'paused';
    if (clone) clone.style.animationPlayState = 'paused';
  });
  on(ticker.parentNode, 'mouseleave', () => {
    ticker.style.animationPlayState = 'running';
    if (clone) clone.style.animationPlayState = 'running';
  });
}

/* ── 5. COMMENTI — Anti-spam e sicurezza ───────────────────── */
function initComments() {
  const form = $('#comment-form');
  if (!form) return;

  // Timestamp: blocca submission troppo rapide (< 5 secondi = bot)
  const loadTime = Date.now();
  form.dataset.loadTime = loadTime;

  on(form, 'submit', handleCommentSubmit);
}

async function handleCommentSubmit(e) {
  e.preventDefault();
  const form = e.target;

  // Anti-spam 1: honeypot
  const honeypot = form.querySelector('[name="url"]');
  if (honeypot && honeypot.value !== '') {
    console.warn('Anti-spam: honeypot triggered');
    showCommentSuccess(); // Fallimento silenzioso
    return;
  }

  // Anti-spam 2: tempo minimo compilazione
  const elapsed = Date.now() - parseInt(form.dataset.loadTime);
  if (elapsed < 3000) {
    showFormError(form, 'Invio troppo rapido. Riprova tra qualche secondo.');
    return;
  }

  const name    = form.querySelector('[name="comment-name"]').value.trim();
  const email   = form.querySelector('[name="comment-email"]').value.trim();
  const text    = form.querySelector('[name="comment-text"]').value.trim();
  const privacy = form.querySelector('[name="privacy"]');

  if (!name || !email || !text) {
    showFormError(form, 'Compila tutti i campi obbligatori.');
    return;
  }
  if (!isValidEmail(email)) {
    showFormError(form, 'Email non valida.');
    return;
  }
  if (privacy && !privacy.checked) {
    showFormError(form, 'Devi accettare la privacy policy per commentare.');
    return;
  }

  // Anti-spam 3: lunghezza minima contenuto
  if (text.length < 15) {
    showFormError(form, 'Il commento è troppo breve.');
    return;
  }

  // Anti-spam 4: URL nel testo (semplice)
  const urlPattern = /(https?:\/\/|www\.)[^\s]+/gi;
  const urlCount = (text.match(urlPattern) || []).length;
  if (urlCount > 2) {
    showFormError(form, 'Il commento contiene troppi link e non può essere accettato.');
    return;
  }

  const btn = form.querySelector('[type="submit"]');
  btn.disabled = true;
  btn.textContent = 'Invio…';

  try {
    /*
      Invia al backend (es. PHP endpoint, Supabase, ecc.)
      I commenti NON devono essere pubblicati immediatamente:
      devono passare per moderazione prima di essere visibili.

      await fetch('/api/comments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, text, articleId: ARTICLE_ID })
      });
    */
    await new Promise(r => setTimeout(r, 600));
    showCommentSuccess();

  } catch (err) {
    btn.disabled = false;
    btn.textContent = 'Invia commento';
    showFormError(form, 'Errore nell\'invio. Riprova.');
  }
}

function showCommentSuccess() {
  const area = $('#comment-form-area');
  if (area) {
    area.innerHTML = `
      <div style="background:var(--paper-warm); border:1px solid var(--border); 
                  border-radius:var(--radius-md); padding:var(--space-md); text-align:center;">
        <p style="font-family:var(--font-ui); font-weight:600; color:var(--ink); margin-bottom:0.35rem;">
          Commento inviato ✓
        </p>
        <p style="font-size:0.85rem; color:var(--ink-muted);">
          Il tuo commento è in attesa di moderazione e sarà pubblicato a breve.
        </p>
      </div>
    `;
  }
}

/* ── 6. CONDIVISIONE SOCIAL ────────────────────────────────── */
function initShareButtons() {
  const url   = encodeURIComponent(location.href);
  const title = encodeURIComponent(document.title);

  const shareMap = {
    '[data-share="facebook"]': `https://www.facebook.com/sharer/sharer.php?u=${url}`,
    '[data-share="twitter"]':  `https://twitter.com/intent/tweet?url=${url}&text=${title}`,
    '[data-share="linkedin"]': `https://www.linkedin.com/shareArticle?mini=true&url=${url}&title=${title}`,
    '[data-share="whatsapp"]': `https://wa.me/?text=${title}%20${url}`,
  };

  Object.entries(shareMap).forEach(([sel, shareUrl]) => {
    $$(sel).forEach(btn => {
      on(btn, 'click', (e) => {
        e.preventDefault();
        window.open(shareUrl, '_blank', 'width=600,height=450,noopener');
      });
    });
  });

  // Copia link
  $$('[data-share="copy"]').forEach(btn => {
    on(btn, 'click', async () => {
      try {
        await navigator.clipboard.writeText(location.href);
        const orig = btn.textContent;
        btn.textContent = '✓ Copiato!';
        setTimeout(() => btn.textContent = orig, 2000);
      } catch {
        showFormError(document.body, 'Copia non supportata dal browser.');
      }
    });
  });
}

/* ── 7. SEARCH ─────────────────────────────────────────────── */
function initSearch() {
  const form = $('.search-bar');
  if (!form) return;

  on(form, 'submit', (e) => {
    e.preventDefault();
    const q = form.querySelector('input').value.trim();
    if (q.length < 2) return;
    // Redirect alla pagina ricerca
    location.href = `/ricerca?q=${encodeURIComponent(q)}`;
  });
}

/* ── 8. LAZY LOADING IMMAGINI ──────────────────────────────── */
function initLazyImages() {
  if (!('IntersectionObserver' in window)) return;

  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        if (img.dataset.src) {
          img.src = img.dataset.src;
          img.removeAttribute('data-src');
        }
        io.unobserve(img);
      }
    });
  }, { rootMargin: '200px' });

  $$('img[data-src]').forEach(img => io.observe(img));
}

/* ── 9. SOCIAL AUTO-POST (nota istruzioni) ─────────────────── */
/*
  La pubblicazione automatica sui social (Facebook, Instagram, Twitter/X)
  richiede una componente SERVER-SIDE (PHP, Node.js, o webhook).
  
  Workflow consigliato:
  1. Quando pubblichi un articolo → chiama un webhook (es. Make.com / Zapier)
  2. Il webhook prende titolo, immagine e URL dell'articolo
  3. Pubblica automaticamente su:
     - Facebook Page (Meta Graph API)
     - Twitter/X (API v2)
     - LinkedIn Company Page (LinkedIn API)
     - Telegram Channel (Bot API — gratuito e immediato)
  
  File: /js/social-webhook.js (separato, lato server)
  
  Vedi documentazione:
  - Meta: https://developers.facebook.com/docs/pages/
  - X/Twitter: https://developer.twitter.com/en/docs/twitter-api
  - LinkedIn: https://learn.microsoft.com/en-us/linkedin/marketing/
  - Telegram: https://core.telegram.org/bots/api
*/

/* ── Inizializzazione ──────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  initMobileNav();
  initNewsletterPopup();
  initCookieBar();
  initTicker();
  initComments();
  initShareButtons();
  initSearch();
  initLazyImages();
});

/* ── Newsletter AJAX (Laravel route) ────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('nl-form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const honey = form.querySelector('[name="website"]');
    if (honey && honey.value !== '') return;

    const email   = form.querySelector('[name="email"]')?.value.trim();
    const privacy = form.querySelector('[name="privacy"]')?.checked;
    const token   = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!email || !privacy) return;

    try {
      const res = await fetch('/newsletter/subscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify({ email, privacy: '1', website: '' })
      });
      const data = await res.json();

      if (data.ok) {
        form.innerHTML = `
          <div style="text-align:center;padding:1rem 0;">
            <p style="font-size:2rem;margin-bottom:.5rem;">✉️</p>
            <strong>Quasi fatto!</strong>
            <p style="font-size:.88rem;color:#3d3d3d;margin-top:.4rem;">Controlla la tua email e conferma l'iscrizione.</p>
          </div>`;
      }
    } catch (err) {
      console.error('Newsletter error:', err);
    }
  });
});

/* ── Commenti AJAX (Laravel route) ──────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('comment-form');
  if (!form) return;

  form.dataset.loadTime = Date.now();

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const honey   = form.querySelector('[name="website"]');
    if (honey && honey.value !== '') return;

    const elapsed = Date.now() - parseInt(form.dataset.loadTime);
    if (elapsed < 3000) { alert('Invio troppo rapido. Riprova.'); return; }

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const data  = new FormData(form);

    try {
      const res = await fetch('/commenti', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token },
        body: data
      });
      const json = await res.json();

      if (json.ok) {
        const area = document.getElementById('comment-form-area');
        if (area) area.innerHTML = `
          <div style="background:var(--color-paper-warm);border:1px solid var(--color-border);
                      border-radius:6px;padding:1rem;text-align:center;">
            <p style="font-weight:600;">Commento inviato ✓</p>
            <p style="font-size:.85rem;color:var(--color-ink-muted);">${json.message}</p>
          </div>`;
      }
    } catch (err) {
      console.error('Comment error:', err);
    }
  });
});
