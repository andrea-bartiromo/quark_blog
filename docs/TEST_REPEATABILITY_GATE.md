# Test Repeatability Gate

**Missione 04** del secondo batch autonomo KAIRUS (Fase A — Test Reliability / CI Foundation).

## Perché esiste

`PublicSurfaceResponsiveImageTest` ha un flake noto e documentato: passa
sempre in isolamento, ma può fallire dentro la suite completa. Prima di
questa missione, l'unico modo per catturarlo era rieseguire manualmente
`php artisan test` più volte per intero (~230s a run) — costoso, e senza
alcuna garanzia di prenderlo (7 run consecutivi in questa sessione non
l'hanno riprodotto).

`scripts/test-repeatability-gate.sh` rende questo controllo un comando
singolo, ripetibile, utilizzabile sia su un sottoinsieme mirato (economico:
pochi secondi a run) sia sull'intera suite.

## Uso

```bash
# Sottoinsieme mirato — economico, va bene anche con --times alto
scripts/test-repeatability-gate.sh --filter=PublicSurfaceResponsiveImageTest --times=30

# Intera suite — costoso, usare con --times basso
scripts/test-repeatability-gate.sh --full --times=3
```

Esce con codice `0` solo se **ogni** run passa. Al primo run fallito si
ferma (fail-fast) e stampa il log completo di quel run — un singolo
fallimento è già prova sufficiente di flakiness, continuare non aggiunge
informazione.

## Cosa NON fa

Non identifica la causa di un flake, solo la sua esistenza/frequenza. Una
volta che un run fallisce, l'investigazione della causa radice resta un
lavoro manuale (vedi `docs/MISSION_50_NIGHT_RELEASE_GATE_HANDOFF.md` per
un esempio di quell'indagine sulla stessa classe di test).
