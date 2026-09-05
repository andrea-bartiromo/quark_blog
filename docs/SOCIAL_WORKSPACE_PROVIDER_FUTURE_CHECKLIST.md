# Checklist progettuale — futura integrazione provider (non implementata)

Esclusivamente progettuale: **nessun punto di questa checklist è
implementato da questa missione**. Nessun codice, migration o
configurazione qui descritti esistono nel repository — questo è un
documento di design per una fase futura, separata, con approvazione
umana dedicata.

## Collegamento tra i due ledger

- [ ] Decidere come `social_drafts` (editoriale) e `social_publications`
      (delivery) si aggancino esplicitamente — probabilmente una
      colonna `social_publication_id` nullable aggiunta a `social_drafts`
      con una migration dedicata, mai il contrario.
- [ ] Il passaggio da "scheduled" (editoriale) a un vero tentativo di
      invio deve restare un'azione umana esplicita o un job schedulato
      separato, mai un side-effect implicito di una transizione di stato
      editoriale.

## Credenziali e secret

- [ ] Storage separato per credenziali (mai in `social_drafts`).
- [ ] Rotazione e revoca documentate per canale.
- [ ] Nessun secret loggato, nemmeno negli errori (stesso standard già
      applicato in `SocialPublication::sanitizedError()`).

## Ambiente

- [ ] Staging con provider di test prima di qualunque credenziale reale.
- [ ] Un invio di prova reale richiede conferma umana separata, un solo
      destinatario/pagina verificata, mai un batch.

## Affidabilità

- [ ] Idempotenza: stessa chiave logica (articolo+canale+evento) già
      presente in `social_publications`, da riusare — non reinventare.
- [ ] Rate limit per canale, rispettoso dei limiti reali della
      piattaforma (da documentare per canale al momento dell'integrazione).
- [ ] Retry limitati con backoff (pattern già presente in
      `PublishSocialDistribution`, riusabile).

## Esito e audit

- [ ] `remote_id`/`remote_url`/`succeeded_at` restano su
      `social_publications`, mai duplicati su `social_drafts`.
- [ ] Ogni tentativo (successo o fallimento) genera una riga di audit
      leggibile, riusando `ActivityLog` come già fa questa V1.
- [ ] Nessun payload provider completo persistito in chiaro.

## Autorizzazione umana

- [ ] Il passaggio da bozza approvata/programmata a invio reale non deve
      mai essere automatico senza un'ultima conferma umana esplicita,
      distinta dall'approvazione editoriale — sono due decisioni diverse
      (contenuto vs. pubblicazione reale).
- [ ] Kill switch globale e per canale, verificato prima di ogni
      attivazione (pattern già in uso: `SOCIAL_DISTRIBUTION_ENABLED`,
      `SOCIAL_FACEBOOK_ENABLED`).

## Privacy

- [ ] Nessun dato personale del lettore coinvolto (questa integrazione
      riguarda solo contenuto editoriale pubblico).
- [ ] Verificare che il provider non richieda permessi più ampi del
      necessario (principio del minimo privilegio).
