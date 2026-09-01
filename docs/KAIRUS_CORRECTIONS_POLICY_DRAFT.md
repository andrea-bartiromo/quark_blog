# Policy delle correzioni — proposta operativa

## Classificazione

| Tipo | Esempio | Registro pubblico |
| --- | --- | --- |
| Refuso | ortografia, punteggiatura, link riparato | no |
| Chiarimento | formulazione resa meno ambigua senza cambiare conclusione | valutazione editor |
| Aggiornamento sostanziale | nuovo dato o fonte che cambia una parte rilevante | sì |
| Correzione scientifica | dato, interpretazione o conclusione errata | sì, esplicito |
| Rettifica | correzione richiesta e verificata relativa a persone/enti | sì, con privacy review |

## Dati minimi

Per una voce pubblica: data, tipo, sintesi comprensibile e parte interessata.
Nel registro interno: versione precedente, motivazione, proponente,
approvatore e timestamp delle transizioni. Email, token, note legali riservate,
IP e dati personali non necessari non devono essere pubblicati.

## Approvazione

- refuso: autore o editor;
- chiarimento: editor quando può cambiare la lettura;
- aggiornamento sostanziale/correzione scientifica/rettifica: editor o admin,
  con secondo controllo quando l'autore coincide con l'approvatore;
- nessuna correzione sostanziale viene retrodatata o cancellata senza una
  nuova voce di audit.

## Visibilità

La nota pubblica deve descrivere cosa è cambiato, non esporre discussioni
interne. Una voce ritirata resta nello storico interno; quella pubblica viene
rimossa solo per ragioni motivate di privacy o sicurezza, registrando la
decisione.

## Compatibilità futura

La policy usa il contratto proposto nell'ADR revisioni editoriali e non
richiede di riutilizzare `updated_at`. Nessun campo o migration viene aggiunto
in questa fase.
