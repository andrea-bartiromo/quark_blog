# Pagine Trust pubbliche — Metodologia (V1)

## Cosa introduce

Una nuova pagina statica server-rendered `/metodologia` (route `metodologia`),
seguendo esattamente il pattern già in uso da `chi-siamo.blade.php` e
`rettifiche.blade.php`: stesse classi CSS (`public-hero`,
`premium-static-section`, `premium-copy-card`, `premium-steps`,
`premium-cta-band`), nessun CSS nuovo, nessuna migration, nessun form,
nessuna raccolta dati.

## Cosa NON introduce

L'audit read-only (B-05) aveva già stabilito che una pagina "Correzioni"
esiste ed è completa: `rettifiche.blade.php` (policy di rettifica,
istruzioni di segnalazione, storico onesto). Questa missione **non la
duplica né la ricrea** — la nuova pagina Metodologia rimanda a
`/rettifiche` con un link esplicito invece di ripeterne il contenuto.

## Contenuto

Espande (senza duplicare verbatim) la sezione "Filosofia editoriale" +
"Protocollo di verifica" già presente su `chi-siamo.blade.php`, con link
esplicito di rimando a quella pagina. Copre:

- Come si sceglie cosa raccontare.
- Il protocollo di verifica in dettaglio (stessi 4 step di chi-siamo,
  ripetuti qui perché centrali al tema della pagina — non un contenuto
  ridondante nel senso "thin", ma la stessa informazione approfondita nel
  contesto giusto).
- Consenso scientifico vs. incertezza.
- Ruolo dell'automazione nel processo editoriale.
- Indipendenza editoriale (dichiarazione conservativa: nessuna
  sponsorizzazione oggi, impegno di trasparenza se introdotta in
  futuro — nessun claim su cosa accadrà, solo un impegno di processo).

Nessun claim assoluto, nessuna certificazione, nessun consiglio medico,
nessuna promessa di SLA — coerente con l'istruzione esplicita della
missione B-19.

## Integrazione

- **Footer** (`components/footer.blade.php`, colonna "Kairus"): link
  aggiunto accanto a "Rettifiche", stesso punto di integrazione già
  esistente e già usato dalle altre pagine statiche.
- **Sitemap** (`SeoController::sitemap()`): aggiunta all'array `$pages`
  esistente, stessa priorità/frequenza di `/chi-siamo` (0.5, monthly) —
  l'unico meccanismo per pagine statiche indicizzabili già presente nel
  repository. Le pagine puramente legali (`/privacy`, `/cookie`,
  `/termini`) restano deliberatamente fuori dalla sitemap, comportamento
  preesistente non toccato: Metodologia è più vicina per natura a
  `/chi-siamo` (contenuto editoriale sostanziale) che a quelle.

## Test

`tests/Feature/TrustPolicyMetodologiaPageTest.php`: raggiungibilità e SEO
di base (title/canonical/description), singolo `<h1>`, link verso
`/rettifiche` e `/chi-siamo`, presenza nel footer, presenza in sitemap,
non-duplicazione del contenuto di `/rettifiche`.

## Limiti

Il contenuto redazionale (scelte su cosa dire riguardo automazione,
indipendenza, ecc.) è una bozza ragionevole basata sui fatti già
verificabili nel codice/documentazione esistente (protocollo di verifica
già pubblicato, nessuna sponsorizzazione nel codice attuale). Non
sostituisce una revisione editoriale umana del testo prima della
pubblicazione in produzione — questa V1 fornisce la struttura tecnica e
un contenuto di partenza conservativo, non un testo definitivo approvato.
