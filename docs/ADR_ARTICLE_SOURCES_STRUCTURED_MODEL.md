# ADR — evoluzione incrementale delle fonti articolo

**Stato:** proposto, non implementato
**Decisione:** mantenere il campo legacy durante una futura introduzione di
fonti strutturate e richiedere revisione umana del backfill.

## Contesto

`primary_sources` è testo libero e non può alimentare in modo affidabile
structured data, filtri, audit di freshness o responsabilità editoriale. La
presentazione pubblica introdotta nella Missione 19 lo rende leggibile senza
pretendere che sia già strutturato.

## Modello futuro

| Campo | Vincolo proposto |
| --- | --- |
| `article_id` | FK, cascade delete |
| `title` | obbligatorio |
| `creator` | autore o ente, nullable |
| `url` | HTTP(S), nullable |
| `doi` | DOI normalizzato, nullable |
| `source_type` | vocabolario controllato |
| `published_at` | data fonte, nullable |
| `accessed_at` | data consultazione, nullable |
| `is_primary` | boolean |
| `reviewed_by` | FK utente, nullable |
| `reviewed_at` | timestamp, nullable |
| `notes` | note interne, mai pubbliche automaticamente |

Almeno uno tra URL, DOI e descrizione bibliografica deve essere presente.
Email, token, payload provider e note interne non appartengono al contratto
pubblico.

## Piano incrementale e reversibile

1. aggiungere la tabella senza rimuovere `primary_sources`;
2. introdurre lettura duale con preferenza per record strutturati;
3. generare una preview di backfill, senza scritture;
4. far approvare ogni mapping a una persona della redazione;
5. scrivere record strutturati mantenendo il testo legacy;
6. confrontare rendering e copertura;
7. rendere il nuovo editor la fonte primaria soltanto dopo copertura completa;
8. valutare la rimozione del legacy in una release separata e reversibile.

Il rollback consiste nel disattivare la lettura strutturata: il campo legacy
resta intatto per tutta la transizione.

## API interna proposta

- `ArticleSources::forPublicArticle(Article)` restituisce solo campi pubblici;
- `ArticleSources::audit(Article)` include completezza e revisione;
- `ArticleSourcesBackfill::preview()` non scrive;
- `ArticleSourcesBackfill::applyApproved(mappingId)` richiede mapping approvato
  e produce audit log.

## Decisioni escluse

Nessun punteggio automatico di affidabilità, import automatico, scraping,
backfill fuzzy, generazione di fonti con IA o migration in questa fase.
