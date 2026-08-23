# Newsletter Conversion Quality Plan

Missione 18 — `DESIGN COMPLETE — IMPLEMENTATION DEFERRED` perché esiste lavoro Newsletter/Communication attivo.

## Architettura reale osservata

- `resources/views/articolo.blade.php` include `articles.partials.newsletter-band` nel flusso dell'articolo, dopo Percorso/Continua da qui e prima dei related;
- `layouts/app.blade.php` include globalmente `components.newsletter-popup`, `components.newsletter-alert` e `layouts.partials.newsletter-scripts`;
- popup accessibile con `role=dialog`, `aria-modal`, focus iniziale, focus trap, Escape, overlay e ripristino del focus;
- apertura automatica dopo 30 secondi soltanto se non risultano `newsletter_dismissed`/`newsletter_subscribed` in `localStorage`;
- dismiss memorizzato per 7 giorni;
- successo subscription segnato localmente quando la request contiene `newsletter=ok`;
- article band e popup inviano entrambi alla route esistente `newsletter.subscribe`;
- #278 Communication Campaign Freeze è ancora OPEN e non deve essere toccata.

## Findings

### 1. CTA duplicate nello stesso journey

Su una pagina articolo un lettore può vedere sia il band inline sia il popup globale. Non è automaticamente un bug, ma deve essere misurato: oggi non esiste nel wiring osservato una regola che sopprima il popup quando l'utente ha già raggiunto/interagito col band.

### 2. Frequency handling

Il popup usa una finestra di 7 giorni dopo dismiss, scelta comprensibile ma non accompagnata da evidence conversion/fatigue nel repository. Non proporre di accorciarla o allungarla senza dati.

Nota implementativa da testare prima di qualunque modifica: `clearExpiredNewsletterDismiss()` viene chiamata dopo il controllo iniziale `if (!dismissed && !subscribed)`. Un dismissal scaduto può quindi restare efficace per la request corrente e venire rimosso solo per la successiva navigazione. È un comportamento da confermare con browser test, non da correggere alla cieca in questo batch.

### 3. Accessibility

La base popup è già buona: dialog semantics, focus trap, Escape, overlay close, inert/hidden e focus return. Una futura modifica deve preservare questi invarianti e verificare mobile/zoom/reduced motion.

### 4. Privacy

`newsletter_dismissed` e `newsletter_subscribed` sono first-party `localStorage`; non contengono email. Non aggiungere fingerprinting, cross-session identity o tracking non coerente con il consent model.

### 5. Conversion telemetry

Prima di ottimizzare copy, timing o placement serve un piccolo contratto eventi, senza PII:

- `newsletter_cta_view` con placement `article_band|popup`;
- `newsletter_cta_submit` con placement;
- `newsletter_subscribe_success`;
- `newsletter_subscribe_error`;
- `newsletter_popup_dismiss`.

Gli eventi devono distinguere impression da submit e non registrare email, query completa, IP o user agent come attributi analytics.

### 6. Failure handling

Il piano deve verificare esplicitamente:

- 422 validation;
- email già iscritta;
- network/server failure;
- redirect success/error;
- stato del popup dopo errore;
- nessuna perdita del valore digitato se il submit fallisce;
- alert leggibile da tecnologie assistive.

## Piano incrementale dopo liberazione ownership

1. Browser regression suite sul comportamento attuale, senza modifiche prodotto.
2. Correggere soltanto bug riprodotti (incluso l'eventuale expired-dismiss one-navigation delay).
3. Aggiungere telemetry first-party/consent-compatible solo se esiste già un sink analytics appropriato e libero da ownership.
4. Misurare band vs popup e overlap.
5. Solo con evidence, sperimentare una singola variazione alla volta: timing, suppression dopo interaction, copy o placement.

## Anti-dark-pattern contract

Vietati: countdown, falsa scarsità, checkbox pre-selezionate, auto-subscribe, overlay senza close, riapertura aggressiva nella stessa sessione, manipolazione del browser back, copy ingannevole.

## Safety

Docs-only. Nessun file Newsletter/Communication runtime modificato, nessun invio email, nessuna subscription reale, nessun merge/deploy.
