# Mission 05 — GitHub Actions Health Audit

**Stato: BLOCKED_WITH_EVIDENCE.** Nessun file di workflow è stato modificato:
i dati raccolti mostrano che il problema non è nella configurazione dei
workflow di questo repository.

## Il sintomo

Ogni run osservato durante il batch precedente e in questo completa in
2–4 secondi indipendentemente dal contenuto della PR, con conclusione
`failure` — troppo veloce per aver eseguito un solo step reale (checkout,
setup PHP, `composer install`, ecc. richiedono ordini di minuti nei
workflow di questo repository).

## Evidenza raccolta

Run reale ispezionato: `Tests` workflow, run
[`32818765347`](https://github.com/andrea-bartiromo/quark_blog/actions/runs/32818765347)
(push su `main`, commit `f9fdb3fe`, 2026-08-25T06:50:46Z → 06:50:50Z).

Tre job in quel run, tutti `status: completed` / `conclusion: failure` in
~2 secondi ciascuno:

| Job | Runner assegnato | Tempo fatturato |
|---|---|---|
| PHP 8.4 (ubuntu-latest) | `runner_id: 0`, `runner_name: ""` | 0 ms |
| Windows media safety (windows-latest) | `runner_id: 0`, `runner_name: ""` | 0 ms |
| MariaDB 11.4 production compatibility (ubuntu-latest) | `runner_id: 0`, `runner_name: ""` | 0 ms |

`get_workflow_run_usage` per l'intero run conferma `total_ms: 0` su
entrambe le label (`UBUNTU`, `WINDOWS`) per tutti i job.

**Nessun job ha mai ricevuto un runner assegnato.** Non è un errore dentro
un job (che produrrebbe log scaricabili e un runner_id valido, con tempo
fatturato > 0) — il tentativo di scaricare i log del job fallisce con
HTTP 404, coerente con un job mai eseguito da nessun runner.

Il numero di run osservati per il solo workflow `tests.yml` è 1335 —
questo non è un evento isolato, è lo stato corrente e persistente di
tutta la pipeline CI del repository.

## Conclusione

Questo pattern (job `completed`/`failure` in ~2s, `runner_id: 0`, zero
tempo fatturato, log 404) è la firma di un **runner mai allocato** dal
lato GitHub — non di un workflow YAML rotto. Le cause tipiche di questa
firma sono esterne al contenuto del repository: GitHub Actions disabilitato
a livello di repository/organizzazione, oppure spending limit/minuti
Actions esauriti a livello di account. Nessuna di queste è verificabile né
risolvibile modificando file in questo repository, e questa missione non
tenta di modificare `.github/workflows/*.yml` in assenza di qualunque prova
che il problema sia lì.

**Azione richiesta (fuori dallo scope di questo batch):** un amministratore
dell'account/organizzazione GitHub deve verificare, nelle impostazioni reali
del repository o dell'organizzazione (Settings → Actions → General, e
Settings → Billing), se Actions è abilitato e se esiste capacità di
runner/minuti disponibile.

## Impatto sul resto del batch

Nessuno: questo repository ha già uno standard operativo consolidato di
verifica locale (suite PHPUnit completa, Pint, `git diff --check`, smoke
da browser dove pertinente) usato per decidere ogni merge di questo e del
batch precedente, indipendentemente dallo stato di GitHub Actions.
`docs/TEST_REPEATABILITY_GATE.md` (Missione 04) resta lo strumento
raccomandato per la caccia locale ai flake finché CI non torna
verificabile.
