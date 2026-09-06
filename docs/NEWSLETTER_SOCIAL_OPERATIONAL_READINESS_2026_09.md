# Readiness operativa Newsletter/Social — 2026-09 (Prompt 116-120)

## Newsletter — già live, un gap operativo trovato e corretto

L'invio settimanale (`newsletter:send`) è **già schedulato in produzione**
ogni giovedì alle 9:00 Europe/Rome (`routes/console.php`), protetto da un
claim `Cache::add` idempotente per iscritto/settimana (attivo anche per
l'invio manuale "Invia ora", che passa dallo stesso comando). Aveva già
un `--dry-run`.

**Gap trovato**: nessun modo di fermare l'invio (schedulato o manuale)
senza un cambio di codice e un deploy — a differenza della distribuzione
Social (sotto), che ha già un interruttore `SOCIAL_DISTRIBUTION_ENABLED`.
Il sistema piu' a rischio (invii reali, gia' in produzione) aveva un
controllo operativo piu' debole di quello attualmente inerte.

**Corretto**: `NEWSLETTER_SEND_ENABLED` (default `true`, nessun cambiamento
al comportamento attuale). Controllato per primo in
`SendWeeklyNewsletter::handle()`, prima di leggere qualunque articolo o
iscritto — ferma sia lo scheduler sia "Invia ora" con un unico switch,
mai due percorsi che potrebbero disallinearsi. Corretto anche un difetto
UX collegato: `Admin\NewsletterPreviewController::send()` mostrava
sempre "Newsletter inviata!" indipendentemente dall'esito reale del
comando (incluso un invio bloccato dall'interruttore) — ora riflette
l'exit code reale.

## Social — correttamente non pronto, e correttamente disattivato

`config/social_distribution.php`: `SOCIAL_DISTRIBUTION_ENABLED` default
`false`, `SOCIAL_FACEBOOK_ENABLED` default `false`, il provider Instagram
è tuttora `FakeSocialProvider` (nessun provider reale implementato). La
pipeline di *delivery* automatico (`social_publications`) esiste ma è
spenta di default: nessun invio reale può accadere senza che un operatore
la attivi esplicitamente.

Il nuovo Social Workspace editoriale (PR #522, non ancora mergeata,
verificato in Prompt 111-115) è deliberatamente **scollegato** da questa
pipeline: nessun comando/scheduler consuma mai una bozza "scheduled" (
verificato via grep, nessuna corrispondenza). Anche se mergeata oggi,
questa PR non renderebbe possibile alcun invio Social reale — resta un
passo distinto, futuro e non implementato
(`docs/SOCIAL_WORKSPACE_PROVIDER_FUTURE_CHECKLIST.md`).

**Nessuna azione richiesta qui**: la readiness "corretta" per Social oggi
è "non pronto, e correttamente segnalato come tale" — non serve un
interruttore aggiuntivo per un sistema già spento di default con un
provider reale che non esiste.

## Cosa questo readiness check non ha fatto

- Non ha abilitato Social Distribution né Facebook/Instagram — resta una
  decisione operativa separata, esplicitamente fuori perimetro.
- Non ha modificato l'eleggibilità o la cadenza dell'invio newsletter
  esistente — solo aggiunto un modo di fermarlo, di default trasparente.
- Non ha toccato dati di produzione né inviato alcuna newsletter/email
  reale da questa sessione.

## Esito

Un gap operativo reale trovato e corretto (newsletter: nessun kill switch
per il sistema già live) con un fix additivo e a default invariato.
Social confermato correttamente non pronto e correttamente disattivato —
nessuna azione necessaria. Suite completa verde (vedi commit).
