# ADR — revisioni editoriali pubbliche degli articoli

**Stato:** proposto, nessuna migration implementata.

## Problema

`updated_at` è tecnico: può cambiare per operazioni non editoriali e non deve
essere presentato come “ultima revisione”. I campi correnti
`verification_status`, `verification_notes`, `verified_at` e `verified_by`
descrivono la verifica esistente, ma non costituiscono uno storico pubblico
di correzioni sostanziali.

## Contratto proposto

Una revisione editoriale pubblicabile richiede:

- articolo;
- tipo: `substantive_update`, `scientific_correction`, `clarification`;
- sintesi pubblica della modifica;
- motivazione interna separata;
- autore della modifica e approvatore;
- data effettiva della revisione;
- stato `draft`, `approved`, `published`, `withdrawn`;
- versione o hash dell'articolo a cui si riferisce.

Refusi, formattazione e manutenzione tecnica non generano una voce pubblica.
Una correzione scientifica, un dato sostituito o una conclusione cambiata sì.

## Autorizzazioni e audit

- autore/collaboratore: propone;
- editor/admin: approva o rifiuta;
- nessuno può approvare la propria correzione sostanziale senza secondo
  revisore quando la policy lo richiede;
- ogni transizione produce un evento append-only;
- note interne, email e identificativi di sessione non diventano pubblici.

## Flusso admin proposto

1. selezione “Registra revisione editoriale”;
2. confronto versione precedente/corrente;
3. scelta del tipo e sintesi pubblica;
4. invio a revisione;
5. approvazione;
6. pubblicazione atomica insieme all'articolo;
7. rendering della data e del changelog essenziale.

Non viene proposta alcuna migration finché il workflow e la policy non sono
approvati dalla redazione.
