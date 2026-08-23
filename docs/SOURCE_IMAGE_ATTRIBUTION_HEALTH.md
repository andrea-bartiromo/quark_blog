# Source & Image Attribution Health

Missione 14 — diagnostica editoriale read-only.

## Separazione dei domini

`MEDIA ATTRIBUTION` comprende cover, alt, credit, source, source URL, license e immagini esterne nel body.

`EDITORIAL/SCIENTIFIC SOURCES` usa invece il campo `primary_sources` e non viene confuso con `cover_source`.

## Stati

- `OK`
- `WARNING`
- `NOT_APPLICABLE`

Nessuno stato blocca salvataggio o pubblicazione.

## Regole conservative

- cover presente senza alt: warning;
- credit senza source o source senza credit: warning;
- cover senza credit/source: warning;
- source URL compilato ma non HTTP(S)/malformato: warning;
- source URL vuoto: `NOT_APPLICABLE`, perché il dominio corrente non impone un URL per ogni fonte media;
- cover senza license: warning;
- immagini esterne nel body: warning, perché oggi non esiste metadata per-image sufficiente a provarne attribution/licenza;
- `primary_sources` vuoto: warning separato dalla fonte della cover.

## Confini

Nessuna modifica a Article model/form/controller, nessuna integrazione con #280 finché non è su `main`, nessuna migration, nessuna mutation.

Prima del merge: focused tests, full suite, Pint e `git diff --check`. La sessione di authoring online non dichiara `LOCAL GREEN`.
