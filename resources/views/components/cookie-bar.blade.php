{{-- Cookie banner GDPR — Quark --}}
<div id="cookie-bar"
     style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;
            background:#111827;color:rgba(255,255,255,.85);padding:1rem 1.5rem;
            box-shadow:0 -4px 24px rgba(0,0,0,.2);">

  {{-- Vista base --}}
  <div id="cookie-simple" style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;">
    <p style="font-size:.82rem;margin:0;line-height:1.5;flex:1;min-width:200px;">
      Utilizziamo cookie tecnici e, previo consenso, cookie analytics e pubblicitari.
      <a href="{{ route('cookie') }}" style="color:#5eead4;">Leggi la Cookie Policy</a>.
    </p>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;flex-shrink:0;">
      <button onclick="cookieCustomize()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Personalizza
      </button>
      <button onclick="cookieReject()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Solo essenziali
      </button>
      <button onclick="cookieAcceptAll()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:#0d9488;color:white;border:none;font-family:inherit;">
        Accetta tutto
      </button>
    </div>
  </div>

  {{-- Vista personalizzazione --}}
  <div id="cookie-advanced" style="display:none;">
    <p style="font-size:.82rem;margin:0 0 .75rem;color:rgba(255,255,255,.7);">
      Scegli quali cookie accettare. I cookie tecnici sono sempre attivi.
    </p>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-bottom:.85rem;">
      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;">
        <input type="checkbox" id="cookie-analytics" style="accent-color:#0d9488;">
        Analytics <span style="color:rgba(255,255,255,.45);font-size:.7rem;">(Google Analytics)</span>
      </label>
      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;">
        <input type="checkbox" id="cookie-ads" style="accent-color:#0d9488;">
        Pubblicità <span style="color:rgba(255,255,255,.45);font-size:.7rem;">(AdSense)</span>
      </label>
    </div>
    <div style="display:flex;gap:.5rem;">
      <button onclick="cookieSavePrefs()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:#0d9488;color:white;border:none;font-family:inherit;">
        Salva preferenze
      </button>
      <button onclick="cookieBack()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Indietro
      </button>
    </div>
  </div>
</div>

<script>
(function() {
  var COOKIE_KEY = 'quark_cookie_consent';
  var COOKIE_DURATION = 365; // giorni

  function getCookie(name) {
    var v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return v ? JSON.parse(decodeURIComponent(v.pop())) : null;
  }

  function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value))
      + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }

  var consent = getCookie(COOKIE_KEY);

  // Se non ha ancora scelto, mostra il banner dopo 1 secondo
  if (!consent) {
    setTimeout(function() {
      document.getElementById('cookie-bar').style.display = 'block';
    }, 1000);
  } else {
    // Applica le preferenze già salvate
    applyConsent(consent);
  }

  function applyConsent(prefs) {
    // Analytics
    if (prefs.analytics && typeof window.gtag === 'function') {
      gtag('consent', 'update', { analytics_storage: 'granted' });
    }
    // Ads
    if (prefs.ads && typeof window.gtag === 'function') {
      gtag('consent', 'update', { ad_storage: 'granted' });
    }
  }

  function hideBanner() {
    document.getElementById('cookie-bar').style.display = 'none';
  }

  window.cookieAcceptAll = function() {
    var prefs = { analytics: true, ads: true, saved: Date.now() };
    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    applyConsent(prefs);
    hideBanner();
  };

  window.cookieReject = function() {
    var prefs = { analytics: false, ads: false, saved: Date.now() };
    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    hideBanner();
  };

  window.cookieCustomize = function() {
    document.getElementById('cookie-simple').style.display = 'none';
    document.getElementById('cookie-advanced').style.display = 'block';
  };

  window.cookieBack = function() {
    document.getElementById('cookie-simple').style.display = 'flex';
    document.getElementById('cookie-advanced').style.display = 'none';
  };

  window.cookieSavePrefs = function() {
    var prefs = {
      analytics: document.getElementById('cookie-analytics').checked,
      ads: document.getElementById('cookie-ads').checked,
      saved: Date.now()
    };
    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    applyConsent(prefs);
    hideBanner();
  };
})();
</script>