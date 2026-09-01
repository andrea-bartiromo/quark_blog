# Newsletter — failure privacy audit

## Finding

Il percorso legacy `SendNewsletterJob` scriveva nel log sia l'indirizzo del
destinatario sia il messaggio arbitrario dell'eccezione provider. Il messaggio
può includere header, token o payload e quindi non è un confine privacy sicuro.

Il percorso Communication 2.0 usa già motivi strutturati (`render_exception`,
`subscriber_not_eligible`, `campaign_not_sendable`, `sender_invalid` e classi
di esito) e provider fake/null nei test. Il finding applicativo era circoscritto
al job settimanale legacy.

## Correzione minima

Il log conserva soltanto un messaggio costante, l'ID interno del subscriber e
la classe PHP dell'errore. L'eccezione continua a propagarsi, la claim viene
rilasciata e la semantica di retry resta invariata. Nessuna email viene inviata
dai test; nessun token, email, header o testo provider viene persistito nel log.

Questa correzione non effettua il cut-over dal legacy a Communication 2.0 e non
modifica eligibility, freeze, destinatari, campagne o configurazione provider.
