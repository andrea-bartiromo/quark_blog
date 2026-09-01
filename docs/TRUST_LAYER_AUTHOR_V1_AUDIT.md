# Missioni 21–22 — Trust Layer autore V1

## Audit del dominio attuale

La pagina `/autore/{user}` esiste già e pubblica nome, foto, biografia,
articoli e `Person` JSON-LD. Il modello `User` dispone di `role`, `bio`,
`photo`, `twitter` e `linkedin`; non dispone di competenze strutturate,
qualifiche certificate o un flag di verifica pubblica del profilo.

Gap verificati:

- ogni account era indicizzabile anche senza bio e senza articoli;
- l'etichetta “Redattore” veniva applicata anche ai collaboratori;
- LinkedIn era raccolto nell'admin ma non pubblicato;
- l'email privata degli editor veniva esposta con un `mailto:`;
- nessun contratto impediva di aggiungere qualifiche non dimostrabili.

## Contratto V1

- `author` → “Collaboratore Kairus”; editor/admin → “Redazione Kairus”;
- profilo senza bio e senza articoli: pagina utilizzabile ma
  `noindex,follow`;
- bio e link social sono mostrati soltanto quando già presenti;
- link esterni limitati a HTTP(S), con protezioni opener/referrer;
- email, ruoli amministrativi dettagliati e dati di autenticazione non sono
  mai pubblici;
- `Person` contiene solo nome, URL, ruolo editoriale, bio e profili dichiarati;
- nessun `knowsAbout`, titolo accademico o qualifica scientifica viene
  inventato in assenza di un campo e di un workflow di verifica.

La route model binding conserva il `404` per utenti inesistenti. Gli articoli
restano filtrati da `Article::published()`.
