# Mission 01 — PublicSurfaceResponsiveImageTest Flake Investigation

**Stato: BLOCKED_WITH_EVIDENCE (non riprodotto).** Nessuna modifica di codice
spedita per questa missione: un'indagine approfondita non ha trovato il
flake, nonostante uno sforzo di verifica molto superiore a quello che lo
aveva originariamente catturato.

## Il contesto

Il report di fine notte del batch precedente
(`docs/MISSION_50_NIGHT_RELEASE_GATE_HANDOFF.md`) documentava un flake:
`PublicSurfaceResponsiveImageTest` (in particolare i test sull'avatar)
falliva intermittentemente dentro la suite completa, pur passando sempre
in isolamento — osservato 2 volte su 2 run consecutivi dell'intera suite
in quella sessione.

## Cosa è stato fatto in questa missione

**Audit statico** di `PublicSurfaceResponsiveImageTest.php`,
`ResponsiveImageVariantService.php`, `PublicMediaSyncService.php`,
`UsesIsolatedPublicPath.php` e `InteractsWithTestImages.php`: nessuna
condivisione di stato tra test individuata — ogni test usa una directory
pubblica isolata con `uniqid('', true)`, nessuna cache statica, nessun
uso di `Cache::` in nessuno dei servizi coinvolti. Un secondo audit
indipendente (agente dedicato) ha confermato: nessun difetto di
isolamento filesystem/cache in nessuno dei 12 file di test che toccano
questa superficie, nessuna esecuzione parallela/randomizzata configurata
in questo repository.

**Riproduzione empirica**, in due fasi:

| Fase | Run | Esito |
|---|---|---|
| Isolato (`--filter=PublicSurfaceResponsiveImageTest`) | 3 | 3/3 passed |
| Intera suite (`php artisan test`) | 14 completi (+1 interrotto da timeout, non conteggiato) | **14/14 passed** |

I 14 run completi della suite intera coprono un arco di ~50 minuti di
lavoro reale in questa sessione, durante il quale la suite è cresciuta da
3584 a 3604 test (nuove missioni mergiate in mezzo alle ripetizioni) —
non uno scenario statico ripetuto identico, ma la suite reale così come
si evolveva mission dopo mission.

**Totale: 17 esecuzioni pulite (14 intera suite + 3 isolate), 0
riproduzioni** — contro il 2/2 fallito della sessione precedente.

## Perché non è stato "riparato" nulla

Non c'è nulla da correggere senza prima riprodurre il difetto: un fix
scritto contro un meccanismo non osservato sarebbe una supposizione, non
una correzione — esattamente ciò che il mandato di questo batch vieta
("investigare → dimostrare → testare → correggere al minimo", mai
"assumere → riscrivere").

## Ipotesi plausibili per la non-riproduzione (non verificate)

- Il flake potrebbe essere legato a condizioni di carico/memoria di
  sistema specifiche della sessione precedente (es. il provisioning
  MariaDB reale della Missione 47 in esecuzione in parallelo), non
  presenti in modo identico in questa sessione.
- Con `memory_limit=-1` in CLI, un flake da esaurimento memoria reale del
  sistema (non del limite PHP) resta comunque plausibile ma richiederebbe
  strumentazione aggiuntiva per essere colto sul fatto.
- Frequenza intrinsecamente bassa (es. 1 run su 20+): 17 tentativi puliti
  non escludono con certezza matematica un tasso di guasto del 5-10%.

## Strumento lasciato in eredità

`scripts/test-repeatability-gate.sh` (Missione 04) e
`scripts/local-release-check.sh` (Missione 06), entrambi costruiti in
risposta diretta a questa indagine, restano disponibili per chi voglia
riprovare la caccia in futuro con uno sforzo molto minore di quello
manuale investito qui.

## Prossima azione raccomandata

Se il flake si ripresenta in una sessione futura, catturarlo con
`scripts/test-repeatability-gate.sh --full --times=N` e, al primo run
rosso, ispezionare immediatamente lo stato del filesystem
(`storage/framework/testing`, temp dir del sistema) prima di eseguire
qualunque pulizia — l'evidenza va preservata sul momento, non
ricostruita dopo.
