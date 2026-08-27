# Missione 64 — Content Graph Phase G browser/release gate

## Esito

**BLOCKED_WITH_EVIDENCE**

Final `main` SHA auditato: `f513b21cf23513d649ee92992d38315dfb72b4d2`.

Il gate non viene dichiarato verde. GitHub Actions resta un
`CI_EXTERNAL_BLOCKER` fino al 01/09/2026 e il connettore GitHub disponibile
in questa sessione consente letture/scritture repository ma non un checkout
autenticato eseguibile. Il tentativo di clone HTTPS ha restituito:

```text
fatal: could not read Username for 'https://github.com': No such device or address
```

Non esistono quindi evidence reali di esecuzione sul final SHA per PHPUnit,
Pint, local-release-check o Playwright. I test sono presenti e revisionabili,
ma “test scritto” non equivale a “test passato”.

## Missioni della Fase G

| Missione | Esito | PR | Merge commit |
|---|---|---|---|
| 55 — diagnostics baseline | IMPLEMENTED | #441 | `b841b6e49de166a4953b3f9c701373a441a9233d` |
| 56 — Concept health | IMPLEMENTED | #442 | `b30fd8bcc19360ddd2c2004c33e9448e0945ecde` |
| 57 — answerable coverage | IMPLEMENTED | #443 | `464dd5543f575a2d7c4cc80ab4f54dde9f594397` |
| 58 — alias integrity | IMPLEMENTED | #444 | `6bfb1079d83827dfd55ef9d683f5408aa0b330b1` |
| 59 — question target integrity | IMPLEMENTED | #445 | `e5351cc3aae43b23bc5e84dd2a52c0ebece353b5` |
| 60 — Article↔Concept diagnostics | IMPLEMENTED | #446 | `a3a0b70de1d1471f8073c80e82c09a5bf585ca5e` |
| 61 — operational summary V2 | IMPLEMENTED | #447 | `7b87af00e9b6261fb49deb15a26955d4345e7aa6` |
| 62 — admin row diagnostics | IMPLEMENTED | #448 | `40889818fce6c2b0f7156cbb08ff59d168ee9e3f` |
| 63 — actionable queue | IMPLEMENTED | #449 | `f513b21cf23513d649ee92992d38315dfb72b4d2` |
| 64 — browser/release gate | BLOCKED_WITH_EVIDENCE | questa PR | pending |

## Copertura presente nel repository

### Focused PHP tests

- `ConceptHealthServiceTest`
- `PublicAnswerableQuestionCoverageServiceTest`
- `ConceptAliasIntegrityServiceTest`
- `ConceptQuestionIntegrityAuditTest`
- `ArticleConceptDiagnosticsServiceTest`
- `ContentGraphOperationalSummaryServiceTest`
- `ConceptRowDiagnosticsTest`
- `EditorialOperationsContentGraphQueueTest`
- estensioni a `EditorialOperationsDashboardServiceTest`

I test includono immutabilità, codici diagnostici, stati articolo
draft/review/scheduled/published, FK `nullOnDelete`, bounding liste e query
budget/no N+1.

### Browser tests

`tests/browser/content-graph-admin.spec.js` copre il flusso reale:

- `/admin/concetti`;
- create/edit Concept;
- alias round-trip;
- Article ↔ Concept in entrambe le direzioni;
- Question draft → approved → pubblicamente answerable;
- diagnostica per riga e CTA “Verifica”;
- viewport 390, 768 e 1440;
- overflow orizzontale;
- `pageerror`;
- unexpected `console.error`;
- focus essenziale sulla CTA.

La copertura Operazioni editoriali Content Graph è presente a livello HTTP; il
gate browser dedicato della nuova sezione deve ancora essere eseguito/esteso
sul final SHA prima della certificazione.

## Comandi obbligatori sul final SHA

```bash
git fetch origin
git switch main
git pull --ff-only origin main
test "$(git rev-parse HEAD)" = "f513b21cf23513d649ee92992d38315dfb72b4d2"
test -z "$(git status --porcelain)"

php artisan test --filter=ContentGraph
php artisan test tests/Feature/Admin/ConceptRowDiagnosticsTest.php
php artisan test tests/Feature/Admin/EditorialOperationsContentGraphQueueTest.php
php artisan test tests/Feature/EditorialOperationsDashboardServiceTest.php
php artisan test

./vendor/bin/pint --test
git diff --check
scripts/local-release-check.sh

npx playwright test tests/browser/content-graph-admin.spec.js
```

Se è disponibile MariaDB reale, ripetere almeno focused Content Graph tests
sulla configurazione MariaDB usata dal progetto. Non esistono migration nella
Fase G, ma le query `withCount/withExists`, gli scope e `nullOnDelete`
devono restare cross-engine.

## Checklist browser manuale/automatica

Per 390 / 768 / 1440 verificare:

- nessun overflow dell'intero documento;
- nessun `pageerror`;
- nessun unexpected `console.error`;
- form HTML senza annidamenti invalidi;
- focus visibile e attivazione keyboard delle CTA;
- `details/summary` diagnostici accessibili;
- la coda Operazioni editoriali mostra **cosa / perché / dove**;
- “Pubblicati senza Concept” compare una sola volta;
- `open_problems_total` include soltanto righe actionable;
- stato vuoto: “Nessun problema Content Graph aggiuntivo rilevato”.

## Rischi residui

1. Nessuna suite è stata eseguita sul final SHA in questa sessione.
2. Il browser test della nuova coda Operazioni editoriali è coperto via HTTP,
   ma non ancora da un'asserzione Playwright dedicata.
3. La normalizzazione Unicode NFC/NFKC degli alias non è definita dal prodotto;
   l'audit resta deliberatamente conservativo e non segnala equivalenze
   canoniche Unicode.
4. Il limite delle liste operative è 50; il flag `items_truncated` segnala il
   troncamento, ma va verificato visualmente con dataset sufficientemente ampio.
5. I conteggi query sono testati come bounded, ma non ancora eseguiti su MariaDB
   reale per questa fase.

## Gate di sblocco

La Fase G può essere marcata release-ready soltanto quando tutti i comandi sopra
passano sullo stesso final SHA, oppure su un nuovo SHA contenente esclusivamente
fix emersi dal gate, con report aggiornato e nuova esecuzione completa.

## Stale issues da aggiornare

Dopo il gate verde:

- aggiornare le issue/roadmap che descrivono Content Graph come solo
  “foundation”;
- collegare PR #441–#449;
- indicare che alias integrity Unicode resta una decisione di policy, non un
  bug automaticamente risolto;
- rimuovere il marker `CI_EXTERNAL_BLOCKER` soltanto dopo evidence di runner
  realmente allocato.
