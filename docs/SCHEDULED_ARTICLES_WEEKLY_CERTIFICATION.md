# Certificazione settimanale degli articoli programmati

`editorial:scheduled-certification` produce una fotografia esclusivamente in
lettura degli articoli `scheduled` compresi nei successivi 14 giorni. Non salva
file, non aggiorna modelli, non pubblica e non chiama provider esterni.

```bash
php artisan editorial:scheduled-certification
php artisan editorial:scheduled-certification --json
```

Per una verifica deterministica è disponibile `--from=<ISO-8601>`; `--days` è
limitato a 1–31. Le date macchina sono UTC e il report include anche la data in
`Europe/Rome`.

Per ogni articolo il comando riusa il servizio canonico Content Health e mostra:
warning, Percorsi, Concept, presenza fonti, collisioni sul medesimo istante e
l'aspettativa pubblica `404_until_publication`. Le relazioni sono eager-loaded:
il numero di query non cresce per articolo. L'ordinamento è `published_at, id`.

Il report è una certificazione, non un gate di pubblicazione. Warning, collisioni
o assenze richiedono una decisione umana; il comando non corregge dati. Prima
dell'uso production eseguire il test mirato e verificare che Social resti OFF.
