# Analytics Hygiene — Kairus

Meccanismo applicativo che impedisce l'invio di eventi Google Analytics 4
quando la richiesta proviene da traffico interno/tecnico (il proprietario
durante sviluppo, amministrazione o verifica manuale), mantenendo il
tracciamento invariato per i visitatori reali. Nasce dalla missione
Analytics Hygiene di agosto 2026.

## Indice

1. [Architettura](#1-architettura)
2. [Criteri di esclusione](#2-criteri-di-esclusione)
3. [Comportamento per ambiente](#3-comportamento-per-ambiente)
4. [Attivare/disattivare l'esclusione sul proprio browser](#4-attivaredisattivare-lesclusione-sul-proprio-browser)
5. [Come verificare che GA4 non venga caricato](#5-come-verificare-che-ga4-non-venga-caricato)
6. [Passaggi manuali nella console GA4](#6-passaggi-manuali-nella-console-ga4)
7. [Troubleshooting](#7-troubleshooting)
8. [Rollback](#8-rollback)
9. [Limiti noti](#9-limiti-noti)

---

## 1. Architettura

Un solo punto di iniezione dello script GA4 in tutto il progetto:
`resources/views/layouts/partials/head.blade.php`, incluso esclusivamente
da `layouts/app.blade.php` (il layout del frontend pubblico). Le aree
Admin (`layouts/admin.blade.php`) e Redazione (`layouts/redazione.blade.php`)
non includono mai questo partial e non hanno mai caricato GA4, prima o
dopo questa missione — verificato leggendo entrambi i layout per intero,
non assunto, e coperto da regression test (`AnalyticsHygieneTest`).

```
Richiesta → layouts/app.blade.php → partials/head.blade.php
                                          │
                                          ▼
                     AnalyticsExclusionService::shouldLoadAnalytics($request)
                                          │
                         ┌────────────────┼────────────────┐
                         ▼                ▼                ▼
                  Measurement ID     Ambiente          Cookie di
                  configurato?    abilitato?          esclusione
                         │                │            presente?
                         └────────────────┴────────────────┘
                                          │
                              true → <script gtag.js>
                              false → nessuno script emesso
```

**`App\Services\AnalyticsExclusionService`** è l'unico punto da cui si
decide se emettere lo script. Principio guida: **non caricare** GA4
piuttosto che caricarlo e filtrare gli eventi dopo — per una richiesta
esclusa, letteralmente nessuna riga di `gtag.js` viene renderizzata nella
risposta HTML.

**`App\Http\Controllers\Admin\AnalyticsExclusionController`** espone due
azioni (`exclude`, `reactivate`), protette dallo stesso gruppo di
middleware `['auth', 'editor']` di tutto il resto del pannello admin —
nessuna nuova autorizzazione introdotta.

**`config/analytics.php`** centralizza Measurement ID, interruttore per
ambiente e durata del cookie di esclusione — nessun valore hardcoded
altrove nel codice.

## 2. Criteri di esclusione

Lo script GA4 viene emesso solo se **tutte** queste condizioni sono vere:

1. `config('analytics.measurement_id')` non è vuoto (fallback esplicito:
   nessun `gtag('config', '', ...)` con ID vuoto o mancante);
2. Analytics è abilitato per l'ambiente corrente (vedi §3);
3. il browser della richiesta **non** porta il cookie
   `kairus_analytics_excluded`.

Il cookie è **first-party**, `HttpOnly` (mai leggibile/scrivibile da
JavaScript lato client — l'unico modo per impostarlo è la rotta admin
protetta), `SameSite=Lax`, e `Secure` quando la richiesta corrente è
HTTPS. Il suo valore è un timestamp ISO 8601 di quando l'esclusione è
stata attivata — **non** un identificativo dell'amministratore, non
collegato in alcun modo all'utente autenticato oltre al controllo di
autorizzazione al momento dell'attivazione. La sola presenza di un
valore non vuoto significa "escluso": nessuna scrittura sul database,
nessuno stato lato server oltre al cookie stesso.

Deliberatamente **non basato sull'IP**: IP dinamici, reti mobili, VPN o
cambi di connessione renderebbero quel criterio fragile — vedi §6 per un
meccanismo IP-based opzionale e complementare, mai sostitutivo, a livello
di console GA4.

Deliberatamente un **cookie**, non la sessione Laravel: il proprietario
visita spesso il sito pubblico anche da disconnesso (sessione admin
scaduta, browser diverso dopo il login altrove) — un flag legato alla
sola sessione applicativa smetterebbe di proteggere esattamente nei casi
che questa missione vuole coprire. Il cookie sopravvive al logout e a
qualunque scadenza di sessione, per la durata configurata (§4).

## 3. Comportamento per ambiente

| Ambiente (`config('app.env')`) | Default (`ANALYTICS_ENABLED` non impostato) |
|---|---|
| `production` | **Abilitato** |
| `local`, `testing`, qualunque altro | **Disabilitato** |

Nessuna azione richiesta per la sicurezza di default: lavorare in locale
o eseguire `php artisan test` non può contaminare accidentalmente la
proprietà GA4 reale, perché l'ambiente stesso blocca il caricamento a
monte, prima ancora di valutare il cookie di esclusione.

`ANALYTICS_ENABLED` in `.env` è un override esplicito che vince sempre
sul rilevamento automatico:

- `ANALYTICS_ENABLED=true` — abilita anche fuori produzione (es. per
  verificare GA4 una tantum su uno staging pubblico);
- `ANALYTICS_ENABLED=false` — disabilita anche in produzione, senza
  deploy (stesso pattern già usato da `MEDIA_AUTO_WEBP_ON_UPLOAD` nella
  missione WebP: un interruttore reversibile in `.env`).

I test PHPUnit non effettuano mai una richiesta di rete reale verso
Google: i test Feature di Laravel non eseguono JavaScript (verificano
solo l'HTML restituito), e nessun codice server-side in questo progetto
chiama l'API Measurement Protocol di GA4 — verificato con una ricerca
esplicita nel codice PHP, nessun risultato. La sicurezza dell'ambiente
`testing` (tabella sopra) è comunque una seconda barriera indipendente,
non l'unica.

## 4. Attivare/disattivare l'esclusione sul proprio browser

Nel pannello Admin → **Profilo** (`/admin/profilo`), sezione "Analytics
su questo browser":

- **Escludi questo browser dalle statistiche** — imposta il cookie di
  esclusione (durata di default: 730 giorni / 2 anni, configurabile con
  `ANALYTICS_EXCLUSION_COOKIE_DAYS`).
- **Riattiva tracciamento** — rimuove il cookie, GA4 torna a caricarsi
  su quel browser al prossimo caricamento pagina.

Richiede autenticazione con ruolo `editor` o `admin` (stessa
autorizzazione di tutto il pannello admin). Riguarda **solo il browser
che effettua la richiesta**: va ripetuto su ogni browser/dispositivo
usato per amministrare o testare il sito (es. Chrome e Firefox sullo
stesso PC sono due esclusioni separate).

Nessun intervento sul database è mai necessario per attivare o
riattivare: l'intero stato vive nel cookie del browser.

## 5. Come verificare che GA4 non venga caricato

1. **Pagina profilo admin** — mostra "ESCLUSO" o "ATTIVO" per il browser
   corrente, con la data di attivazione se escluso.
2. **View-source / DevTools → Elements** — cercare
   `googletagmanager.com`: assente = nessuno script emesso, non solo
   nascosto via CSS/JS.
3. **DevTools → Network** — con il filtro `google-analytics` o
   `collect`: nessuna richiesta per un browser escluso o in un ambiente
   non-production.
4. **DevTools → Application → Cookies** — verificare la presenza/assenza
   di `kairus_analytics_excluded` sul dominio.

## 6. Passaggi manuali nella console GA4

Nessuna configurazione GA4-side è necessaria per il funzionamento di
questo meccanismo: l'esclusione avviene interamente lato applicazione,
prima che qualunque dato lasci il browser. I passaggi seguenti sono
**opzionali**, una rete di sicurezza complementare — da eseguire
manualmente nella console Google Analytics, non implementabili da
codice:

1. **Regola di traffico interno basata su IP** (facoltativa, secondaria):
   Amministrazione → Impostazioni struttura dati → Traffico interno → Crea
   → aggiungere l'IP (o l'intervallo) della rete/ufficio da cui si lavora
   abitualmente. Copre il caso limite di una primissima visita al sito
   pubblico da un browser che non ha ancora attivato l'esclusione
   applicativa (vedi §9, Limiti noti) — mai il meccanismo primario, per
   le ragioni di fragilità dell'IP già discusse in §2.
2. Dopo aver creato la regola, in Amministrazione → Impostazioni dati →
   Filtri dati, impostare il filtro "Traffico interno" prima su
   **Test**, verificare per qualche giorno che classifichi
   correttamente solo il traffico atteso, poi passare a **Attivo** per
   escluderlo davvero dai report.
3. **Verifica del Measurement ID in produzione**: dopo il deploy,
   confermare che `.env` di produzione abbia `GA4_MEASUREMENT_ID`
   impostato (o assente, nel qual caso viene usato il default
   `G-Y1853N6FZP` già in uso prima di questa missione — vedi
   `config/analytics.php`).

## 7. Troubleshooting

**GA4 non si carica per un visitatore reale in produzione.**
Verificare in ordine: `APP_ENV=production` in `.env` di produzione;
`ANALYTICS_ENABLED` non impostato su `false`; `GA4_MEASUREMENT_ID` non
vuoto; il visitatore non ha (per errore) il cookie
`kairus_analytics_excluded` — improbabile per un visitatore reale, ma
verificabile in DevTools.

**GA4 continua a caricarsi su un browser che ho escluso.**
Il cookie ha `path=/`: verificare di non essere su un dominio/sottodominio
diverso da quello su cui è stata effettuata l'esclusione. Verificare in
DevTools → Application → Cookies che `kairus_analytics_excluded` sia
davvero presente e non scaduto.

**Ho perso l'accesso admin e non posso riattivare Analytics.**
Cancellare manualmente il cookie `kairus_analytics_excluded` dalle
impostazioni del browser (Impostazioni → Privacy → Cookie del sito) — non
richiede accesso admin, essendo un'operazione lato browser.

**I test PHPUnit falliscono dopo una modifica a `config/analytics.php`.**
Verificare `php artisan config:clear` se si è testato manualmente con
`config:cache` attiva — i test stessi non risentono della cache (Laravel
la ignora nell'ambiente di test), ma un ambiente locale con
`config:cache` da un'esecuzione precedente può mascherare modifiche
recenti a `.env`.

## 8. Rollback

Nessuna migration, nessun dato persistito oltre al cookie del browser.
Per tornare al comportamento precedente questa missione (GA4 sempre
caricato, nessuna esclusione possibile):

```bash
git revert <commit-di-questa-missione>
```

Non è necessaria alcuna pulizia database: `AnalyticsExclusionService` non
scrive mai nulla oltre al cookie, e un cookie che il codice non legge più
resta semplicemente inerte nel browser dell'utente fino alla sua naturale
scadenza.

## 9. Limiti noti

- **Il meccanismo protegge solo browser che hanno già attivato
  l'esclusione.** La primissima visita da un browser nuovo (prima di
  autenticarsi e attivare l'esclusione dal profilo) viene tracciata come
  qualunque altro visitatore. Mitigabile con la regola IP opzionale di
  §6, mai eliminabile del tutto da un meccanismo puramente
  applicativo — coerente con la richiesta esplicita della missione di
  non fondare la soluzione sull'IP.
- **Chiunque può escludere il proprio stesso browser manualmente** (via
  DevTools → Application → Cookies, impostando
  `kairus_analytics_excluded` a qualunque valore non vuoto), esattamente
  come potrebbe bloccare `gtag.js` con un ad-blocker. Non è un problema
  di sicurezza: un visitatore influenza solo il conteggio delle proprie
  stesse visite, non può alterare alcuna configurazione applicativa —
  le rotte che il codice espone per farlo restano protette da
  `auth`+`editor`.
- **Un browser condiviso tra più persone** (es. un PC comune) applica
  l'esclusione a chiunque lo usi, non solo all'amministratore. Va escluso
  solo su dispositivi/browser realmente dedicati al lavoro su Kairus.
- **La finestra di consenso GDPR (`components/cookie-bar.blade.php`)
  resta visibile anche su un browser escluso**, poiché riguarda il
  consenso ai cookie in generale, non lo stato di questa esclusione:
  cliccare "Accetta tutto" su un browser escluso non ha alcun effetto
  osservabile (nessuno script `gtag` esiste su cui applicare il
  consenso), comportamento innocuo ma potenzialmente poco intuitivo.
