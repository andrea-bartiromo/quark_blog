# Igiene dei corpi articolo

## Incolla come testo semplice

I form Admin e Redazione mantengono l’incolla formattato normale. Il comando
“Incolla testo semplice” arma soltanto il prossimo evento di incolla: conserva i
paragrafi e gli a-capo, codifica i caratteri HTML e scarta la formattazione
proveniente da Word, ChatGPT o pagine web.

## Audit read-only

```bash
php artisan articles:audit-body-contamination
php artisan articles:audit-body-contamination --article=39 --dry-run
php artisan articles:audit-body-contamination --dry-run --json
```

Il comando mostra soltanto ID, titolo e codici dei finding. `--dry-run` aggiunge
hash SHA-256 prima/dopo, numero di nodi ipoteticamente rimossi e un’anteprima
testuale limitata. Non esiste un’opzione `--execute`: nessun articolo viene
modificato, né in locale né in produzione.
