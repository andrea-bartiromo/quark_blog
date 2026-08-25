# Local Release Check

**Missione 06** del secondo batch autonomo KAIRUS (Fase A — Test
Reliability / CI Foundation).

## Perché esiste

GitHub Actions su questo repository non alloca runner da almeno 1335 run
consecutivi — ogni check completa in 2-4s senza mai eseguire nulla (vedi
`docs/MISSION_05_CI_HEALTH_AUDIT.md` per l'evidenza raccolta). Finché non è
dimostrabilmente ripristinato, la verifica locale resta l'unico standard
usato per decidere un merge in questo repository — è quanto già applicato,
a mano, per ognuna delle PR di questo e del batch precedente.

`scripts/local-release-check.sh` raccoglie in un solo comando la stessa
sequenza già eseguita manualmente prima di ogni merge: suite PHPUnit, Pint
(sola verifica), `git diff --check`, e il gate di drift degli asset di
deploy quando `DEPLOY_SERVED_PUBLIC_ROOT` è configurato.

## Uso

```bash
scripts/local-release-check.sh                    # suite completa
scripts/local-release-check.sh --filter=Pattern    # solo un sottoinsieme
```

Esce con codice `0` solo se ogni controllo passa. A differenza di
`scripts/test-repeatability-gate.sh` (Missione 04, che si ferma al primo
run rosso per cacciare un flake), qui l'obiettivo è un riepilogo completo
pre-merge: tutti i controlli vengono eseguiti anche dopo un fallimento, e
il riepilogo finale elenca lo stato di ciascuno.

## Cosa NON fa

Non sostituisce una ripetizione mirata contro un flake noto (per quella
usa `scripts/test-repeatability-gate.sh`), e non esegue i test browser
Playwright della suite (restano un passaggio manuale separato quando la
missione tocca l'interfaccia, come già stabilito nel batch precedente).
