{{-- Cookie banner GDPR — Kairus --}}
<div id="cookie-bar"
     style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:9999;
            background:#111827;color:rgba(255,255,255,.85);padding:1rem 1.5rem;
            box-shadow:0 -4px 24px rgba(0,0,0,.2);">

  {{-- Vista base --}}
  <div id="cookie-simple"
       style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;justify-content:space-between;">
    <p style="font-size:.82rem;margin:0;line-height:1.5;flex:1;min-width:200px;">
      Utilizziamo cookie tecnici e, previo consenso, cookie analytics e pubblicitari.
      <a href="{{ route('cookie') }}" style="color:#5eead4;">Leggi la Cookie Policy</a>.
    </p>

    <div style="display:flex;gap:.5rem;flex-wrap:wrap;flex-shrink:0;">
      <button type="button"
              onclick="cookieCustomize()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Personalizza
      </button>

      <button type="button"
              onclick="cookieReject()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Solo essenziali
      </button>

      <button type="button"
              onclick="cookieAcceptAll()"
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
        <input type="checkbox"
               id="cookie-analytics"
               style="accent-color:#0d9488;">
        Analytics
        <span style="color:rgba(255,255,255,.45);font-size:.7rem;">
          (Google Analytics)
        </span>
      </label>

      <label style="display:flex;align-items:center;gap:.4rem;cursor:pointer;font-size:.82rem;">
        <input type="checkbox"
               id="cookie-ads"
               style="accent-color:#0d9488;">
        Pubblicità
        <span style="color:rgba(255,255,255,.45);font-size:.7rem;">
          (AdSense)
        </span>
      </label>
    </div>

    <div style="display:flex;gap:.5rem;">
      <button type="button"
              onclick="cookieSavePrefs()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:#0d9488;color:white;border:none;font-family:inherit;">
        Salva preferenze
      </button>

      <button type="button"
              onclick="cookieBack()"
              style="padding:.4rem .85rem;border-radius:6px;font-size:.75rem;font-weight:600;
                     cursor:pointer;background:transparent;color:rgba(255,255,255,.6);
                     border:1px solid rgba(255,255,255,.2);font-family:inherit;">
        Indietro
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  var COOKIE_KEY = 'kairus_cookie_consent';
  var COOKIE_DURATION = 365;

  function getCookie(name) {
    var match = document.cookie.match(
      '(^|;)\\s*' + name + '\\s*=\\s*([^;]+)'
    );

    if (!match) {
      return null;
    }

    try {
      return JSON.parse(decodeURIComponent(match.pop()));
    } catch (error) {
      return null;
    }
  }

  function setCookie(name, value, days) {
    var expires = new Date();

    expires.setTime(
      expires.getTime() + days * 24 * 60 * 60 * 1000
    );

    document.cookie =
      name + '=' + encodeURIComponent(JSON.stringify(value)) +
      ';expires=' + expires.toUTCString() +
      ';path=/' +
      ';SameSite=Lax' +
      ';Secure';
  }

  function applyConsent(prefs, sendPageView) {
    if (typeof window.gtag !== 'function') {
      return;
    }

    gtag('consent', 'update', {
      analytics_storage: prefs.analytics ? 'granted' : 'denied',
      ad_storage: prefs.ads ? 'granted' : 'denied',
      ad_user_data: prefs.ads ? 'granted' : 'denied',
      ad_personalization: prefs.ads ? 'granted' : 'denied'
    });

    /*
     * Invia la visualizzazione della pagina corrente subito dopo
     * l'accettazione dei cookie Analytics.
     */
    if (prefs.analytics && sendPageView) {
      gtag('event', 'page_view', {
        page_title: document.title,
        page_location: window.location.href,
        page_path: window.location.pathname
      });
    }
  }

  function hideBanner() {
    var banner = document.getElementById('cookie-bar');

    if (banner) {
      banner.style.display = 'none';
    }
  }

  function showBanner() {
    var banner = document.getElementById('cookie-bar');

    if (banner) {
      banner.style.display = 'block';
    }
  }

  function setCheckboxes(prefs) {
    var analyticsCheckbox = document.getElementById('cookie-analytics');
    var adsCheckbox = document.getElementById('cookie-ads');

    if (analyticsCheckbox) {
      analyticsCheckbox.checked = Boolean(prefs && prefs.analytics);
    }

    if (adsCheckbox) {
      adsCheckbox.checked = Boolean(prefs && prefs.ads);
    }
  }

  var consent = getCookie(COOKIE_KEY);

  if (!consent) {
    setTimeout(showBanner, 1000);
  } else {
    /*
     * Le preferenze esistenti vengono applicate al caricamento.
     * Non inviamo manualmente page_view perché gtag('config')
     * gestisce già la visualizzazione dopo l'aggiornamento del consenso.
     */
    applyConsent(consent, false);
    setCheckboxes(consent);
  }

  window.cookieAcceptAll = function () {
    var prefs = {
      analytics: true,
      ads: true,
      saved: Date.now()
    };

    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    setCheckboxes(prefs);
    applyConsent(prefs, true);
    hideBanner();
  };

  window.cookieReject = function () {
    var prefs = {
      analytics: false,
      ads: false,
      saved: Date.now()
    };

    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    setCheckboxes(prefs);
    applyConsent(prefs, false);
    hideBanner();
  };

  window.cookieCustomize = function () {
    var simple = document.getElementById('cookie-simple');
    var advanced = document.getElementById('cookie-advanced');

    setCheckboxes(getCookie(COOKIE_KEY));

    if (simple) {
      simple.style.display = 'none';
    }

    if (advanced) {
      advanced.style.display = 'block';
    }
  };

  window.cookieBack = function () {
    var simple = document.getElementById('cookie-simple');
    var advanced = document.getElementById('cookie-advanced');

    if (simple) {
      simple.style.display = 'flex';
    }

    if (advanced) {
      advanced.style.display = 'none';
    }
  };

  window.cookieSavePrefs = function () {
    var analyticsCheckbox = document.getElementById('cookie-analytics');
    var adsCheckbox = document.getElementById('cookie-ads');

    var prefs = {
      analytics: Boolean(
        analyticsCheckbox && analyticsCheckbox.checked
      ),
      ads: Boolean(
        adsCheckbox && adsCheckbox.checked
      ),
      saved: Date.now()
    };

    setCookie(COOKIE_KEY, prefs, COOKIE_DURATION);
    applyConsent(prefs, prefs.analytics);
    hideBanner();
  };
})();
</script>