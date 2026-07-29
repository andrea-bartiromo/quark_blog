# ROADMAP_V1 — Quark Blog, verso la v1.0

| Campo | Valore |
|---|---|
| Data | 2026-07-29 |
| Ambito | Solo attività necessarie al rilascio. Nessuna nuova funzionalità, nessun refactoring estetico, nessun nuovo documento non indispensabile. |
| Metodo | Revisione diretta del repository su `main` (HEAD `93870c0`): grep mirati (TODO/FIXME/placeholder), lettura di `deploy.sh`, `robots.txt`, `.htaccess`, `.env.example`, `routes/console.php`, footer/pagine statiche, esecuzione della suite di test completa. Include anche gli esiti già verificati nei 6 audit dello Speciale Turing (`docs/02_Turing_Audit/`), non ripetuti qui da zero. |

Suite di test: **557/557 passati** (0 falliti) — nessun bug bloccante rilevato a livello di test automatici.

---

## CRITICO — impedisce il rilascio

### 1. Configurazione `.env` di produzione mancante
- **Descrizione.** `.env.example` contiene solo placeholder (`APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost`, credenziali SMTP fittizie `your-email@example.com`). `deploy.sh` stesso si interrompe con errore se `APP_DEBUG=true` viene rilevato in produzione. Senza un `.env` reale il sito non può essere messo online in modo sicuro.
- **Tempo stimato.** 20 min (compilazione) + tempo di attesa per credenziali SMTP reali se non già disponibili.
- **Impatto.** Massimo: senza questo, il deploy si blocca per costruzione (`deploy.sh` termina con `exit 1`).
- **Dipendenze.** Dominio reale, credenziali SMTP di produzione.
- **Bloccante.** Sì.

### 2. Cron job `schedule:run` non risulta impostato
- **Descrizione.** `routes/console.php` definisce 5 task schedulati reali e già scritti: backup automatico giornaliero del database (02:00), invio newsletter settimanale (giovedì 09:00), raccolta automatica notizie via RSS+AI (lunedì/giovedì 09:30), pulizia cache settimanale. Nessuno di questi gira senza il cron di sistema (`* * * * * php artisan schedule:run`), esplicitamente richiesto come passo manuale in `deploy.sh` ma non verificabile da codice.
- **Tempo stimato.** 5 min.
- **Impatto.** Alto: senza cron, **zero backup automatici del database** dal giorno del lancio — rischio concreto di perdita dati in caso di incidente.
- **Dipendenze.** Accesso SSH/pannello del server di produzione.
- **Bloccante.** Sì (per il solo backup; newsletter/automazione notizie sono funzionali ma non bloccanti in senso stretto).

---

## ALTO — fortemente consigliato prima del rilascio

### 3. `robots.txt` contiene il placeholder letterale "DOMINIO"
- **Descrizione.** `public/robots.txt` include `Sitemap: https://DOMINIO/sitemap.xml` e `https://DOMINIO/news-sitemap.xml` — un segnaposto testuale mai sostituito, come indicato dal commento nel file stesso ("Prima del lancio sostituire DOMINIO con il dominio reale"). Le sitemap generate dinamicamente da `SeoController` sono corrette (usano `config('app.url')`); solo il file statico `robots.txt` ha il valore hardcoded.
- **Tempo stimato.** 5 min.
- **Impatto.** Alto: i motori di ricerca non troverebbero la sitemap al primo crawl.
- **Dipendenze.** Nessuna, richiede solo di conoscere il dominio finale.
- **Bloccante.** No.

### 4. Redirect forzato HTTPS disattivato in `.htaccess`
- **Descrizione.** Il blocco `RewriteRule` per il redirect HTTP→HTTPS in `public/.htaccess` è commentato ("Forza HTTPS (abilitare in produzione)"), così come l'header `Strict-Transport-Security`.
- **Tempo stimato.** 5 min (decommentare + verificare che il server abbia certificato TLS attivo).
- **Impatto.** Alto: sicurezza e SEO (Google penalizza contenuto non forzato su HTTPS).
- **Dipendenze.** Certificato TLS attivo sul dominio di produzione.
- **Bloccante.** No, ma da fare contestualmente al deploy.

### 5. Bug di contrasto WCAG AA condiviso da 4 pagine (`turing.css`)
- **Descrizione.** Già misurato e confermato via CDP + Lighthouse/axe-core negli audit dello Speciale Turing: `.turing-article-breadcrumb a` (3.26:1), `[aria-current="page"]` (2.36:1), `.turing-terminal-card span` (4.09:1) — tutti sotto la soglia AA 4.5:1. Un solo intervento su `turing.css` corregge hub, `/turing/computation`, `/turing/intelligence`, `/turing/legacy` insieme.
- **Tempo stimato.** 30 min.
- **Impatto.** Alto: accessibilità, 4 pagine impattate da un'unica causa.
- **Dipendenze.** Nessuna.
- **Bloccante.** No.

### 6. Bug di contrasto grave e specifico su `/turing/ai` (`.ai-light-text`)
- **Descrizione.** Collisione di specificità CSS (`.ai-copy p` batte `.ai-light-text`) produce un contrasto **1.42:1** su 2 istanze su 3 — il difetto di accessibilità più grave riscontrato in tutto il progetto.
- **Tempo stimato.** 15 min.
- **Impatto.** Alto: accessibilità, singolo file.
- **Dipendenze.** Nessuna.
- **Bloccante.** No.

### 7. Tre link interni errati in `legacy.blade.php` (righe 36-64)
- **Descrizione.** Due dei tre link teaser di rilancio dalla pagina di chiusura dello Speciale sono errati: puntano a `route('turing').'#macchina-universale'` invece di `route('turing.computation')`, e a `route('turing.ai')` invece di `route('turing.intelligence')` (il testo circostante discute esplicitamente il saggio del 1950). Rompono il flusso di navigazione nel punto in cui la pagina dovrebbe rilanciare le altre.
- **Tempo stimato.** 10 min.
- **Impatto.** Alto: UX/navigazione, bug concreto e riproducibile.
- **Dipendenze.** Nessuna.
- **Bloccante.** No.

---

## MEDIO — può essere rimandato

### 8. Pagina `/turing/ai`: contenuto editoriale troppo sintetico
- **Descrizione.** ≈400 parole di testo narrativo, la più bassa dell'intero Speciale, con approfondimento insufficiente sulla distinzione fra imitazione e comprensione nei LLM. Già segnalato nell'audit editoriale dedicato.
- **Tempo stimato.** 3-4 h di scrittura.
- **Impatto.** Medio: qualità editoriale, non blocca la fruibilità della pagina.
- **Dipendenze.** Audit_AI_Editoriale_v1.0.md.
- **Bloccante.** No.

### 9. `/turing/enigma` e `/turing/ai` non migrate al Design System
- **Descrizione.** ~360 righe di CSS inline ciascuna, duplicate indipendentemente, zero riuso di `<x-turing.article.*>`. Debito architetturale noto, non un bug funzionale.
- **Tempo stimato.** 1-2 giorni per pagina.
- **Impatto.** Medio: manutenibilità futura, nessun impatto sulla fruibilità attuale.
- **Dipendenze.** Punto 8 (va fatto prima di riscrivere il codice, per non riscrivere i contenuti due volte).
- **Bloccante.** No.

### 10. Google Analytics 4 e AdSense non attivi
- **Descrizione.** Script GA4/AdSense presenti in `head.blade.php` ma interamente commentati, con ID placeholder (`G-XXXXXXXXXX`, `ca-pub-XXXXXXXXXXXXXXXXX`).
- **Tempo stimato.** 15 min una volta ottenuti gli ID reali.
- **Impatto.** Medio: nessun impatto funzionale, solo assenza di dati di traffico/monetizzazione dal lancio.
- **Dipendenze.** Account GA4/AdSense attivi.
- **Bloccante.** No.

### 11. Nessun audit SEO dedicato allo Speciale Turing
- **Descrizione.** Solo un punteggio Lighthouse SEO generico (96/100) raccolto durante gli audit tecnici; mai eseguita una verifica dedicata (meta tag per pagina, dati strutturati, canonical).
- **Tempo stimato.** 2 h.
- **Impatto.** Medio.
- **Dipendenze.** Nessuna.
- **Bloccante.** No.

### 12. Riferimento a P.IVA nel footer, richiesto da `deploy.sh`, assente nel codice
- **Descrizione.** `deploy.sh` elenca "Aggiornare P.IVA nel footer" fra i passi manuali finali, ma `footer.blade.php` non contiene alcun riferimento fiscale da aggiornare — va aggiunto ex novo se richiesto legalmente per l'operatività del progetto.
- **Tempo stimato.** 15 min (se il dato è disponibile).
- **Impatto.** Medio: conformità legale/business, non tecnico.
- **Dipendenze.** Dato fiscale dal founder.
- **Bloccante.** No (salvo obbligo legale non verificabile da codice).

---

## FUTURO — dopo la v1.0

### 13. Migrazione completa di Enigma/AI al Design System (dopo la riscrittura contenuti, punto 8)
### 14. Capitoli 4-10 del Manuale di Identità Visiva, Volumi II-IV
### 15. Completamento dei documenti segnaposto in `docs/03_Turing_Produzione/`, `docs/05_Turing_Fonti/`, `docs/06_Turing_Release/Report_SEO_v0.1.md`
### 16. Riconciliazione fra `docs/PROJECT_BOOK.md` (canonico, fino a Decision #012) e `docs/00_Governance/Project_Book_v3.1.docx` (fino a #024, struttura riorganizzata) — decisione editoriale, non tecnica

---

## Tabella riepilogativa

| Stato | Attività | Priorità | Stimata |
|---|---|---|---|
| ❌ | Configurazione `.env` di produzione | Critica | 20 min |
| ❌ | Cron `schedule:run` sul server (backup giornaliero) | Critica | 5 min |
| ❌ | Sostituire "DOMINIO" in `robots.txt` | Alta | 5 min |
| ❌ | Abilitare redirect HTTPS in `.htaccess` | Alta | 5 min |
| ❌ | Fix contrasto condiviso `turing.css` (4 pagine) | Alta | 30 min |
| ❌ | Fix contrasto `.ai-light-text` (1.42:1) | Alta | 15 min |
| ❌ | Fix 3 link errati in `legacy.blade.php` | Alta | 10 min |
| ⚠️ | Ampliamento contenuti `/turing/ai` | Media | 3-4 h |
| ⚠️ | Migrazione Design System Enigma/AI | Media | 1-2 gg |
| ⚠️ | Attivazione GA4/AdSense | Media | 15 min |
| ⚠️ | Audit SEO dedicato Speciale Turing | Media | 2 h |
| ⚠️ | P.IVA nel footer | Media | 15 min |
| ✅ | Suite di test automatici | Completata | 557/557 |
| ✅ | Sitemap dinamica (usa `config('app.url')`, non hardcoded) | Completata | - |
| ✅ | Pagine legali (privacy/cookie/termini/rettifiche/chi-siamo/contatti) presenti e non stub | Completata | - |

## Attività con il miglior rapporto impatto/tempo

**Fix dei 3 link errati in `legacy.blade.php`** (10 minuti, righe 36-64, già completamente diagnosticato con la correzione esatta da applicare): è il singolo intervento che ripara di più con il minor tempo, perché corregge un bug di navigazione concreto e visibile a ogni lettore che arriva in fondo allo Speciale Turing — a pari merito, sul fronte puramente infrastrutturale, con la sostituzione del placeholder "DOMINIO" in `robots.txt` (5 minuti, impatto SEO immediato dal giorno del lancio).
