# Workspace Social Admin V1 — rollback

## Applicativo (disabilitare/rimuovere la UI)

Rimuovere il blocco route in `routes/web.php` (`distribuzione-social/bozze`
e il relativo `use App\Http\Controllers\Admin\SocialDraftController;`) e
il link aggiunto in `resources/views/admin/social-distribution/index.blade.php`
è sufficiente a nascondere completamente il workspace. **Non richiede
alcuna azione di pulizia lato invio**: nessun job, provider o credenziale
è mai stato coinvolto — non c'è nulla da "fermare in corso".

## Dati

Le bozze restano un ledger interno inerte: nessun processo esterno le
legge o le consuma. Disabilitare la UI non lascia nulla "a metà" — non
esiste alcuna coda, alcun webhook, alcun processo asincrono agganciato a
`social_drafts`.

Per rimuovere anche lo schema (solo se esplicitamente richiesto, azione
distinta e più invasiva di un rollback applicativo):

```
php artisan migrate:rollback --path=database/migrations/2026_09_02_120000_create_social_drafts_table.php
```

La migration ha un `down()` completo (`Schema::dropIfExists('social_drafts')`).
**Da eseguire solo con decisione esplicita**, mai come parte di un
rollback applicativo di routine — cancella ogni bozza esistente in modo
non recuperabile.

## Nessun impatto su articoli

Nessun dato articolo viene mai modificato da questa funzionalità (foreign
key `restrict on delete`: un articolo con bozze collegate non può essere
cancellato finché le bozze non vengono gestite prima) — quindi non c'è
mai nulla da "ripristinare" sul lato articoli in un rollback.

## Nessun impatto su social_publications

Tabella, model, job e listener del ledger di delivery esistente restano
del tutto invariati da questa missione — un rollback del workspace non
tocca in alcun modo la pipeline di invio automatico (comunque disattivata
di default via `SOCIAL_DISTRIBUTION_ENABLED=false`).

## Verifica post-rollback

Dopo un rollback applicativo (route rimosse): `/admin/distribuzione-social/bozze`
deve rispondere 404, `/admin/distribuzione-social` (generatore link UTM
esistente) deve continuare a funzionare invariato.
