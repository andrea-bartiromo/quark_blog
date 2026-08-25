# Missione 37 — Image readiness

**Batch**: KAIRUS — Operazioni editoriali (Missioni 25–74), Fase E — Editorial
Quality & Readiness.
**Esito**: `VERIFIED_ALREADY_PRESENT` (+ una copertura di regressione HTTP
mancante, genuinamente utile, aggiunta).

## Requisito

Verificare che lo stato di "prontezza" delle immagini editoriali (presenza
cover, testo alternativo, attribuzione — credito/fonte/licenza, immagini nel
corpo) sia calcolato correttamente e reso operativamente visibile
(outcome, non un'implementazione specifica imposta dalla spec).

## Stato reale trovato

La capacità è già completa e stratificata su più livelli, tutti già su
`main` prima di questa missione:

1. **`ArticleContentHealthService`**: calcola già, per ogni articolo
   pubblicato/programmato, `cover` (presenza — ESSENTIAL, HIGH priority nel
   dashboard), `cover_alt` (testo alternativo) e `cover_attribution`
   (credito + fonte/URL fonte).
2. **`SourceImageAttributionHealthService`**: calcola in modo più granulare
   `cover_alt`, `cover_credit_source`, `cover_source_url`, `cover_license`,
   `external_body_images` (immagini esterne nel body senza attribution
   verificabile) ed `editorial_sources`.
3. **Wiring nel dashboard**: `EditorialOperationsDashboardService` combina
   già entrambi i servizi in `da_sistemare`, con priorità HIGH quando il
   finding rientra tra `cover`/`summary`/`sources` (content health) o
   `external_body_images` (attribution) — vocabolario HIGH/MEDIUM già
   condiviso col resto della dashboard (Fase F di un batch precedente).
4. **`EditorialQualityChecker`** (categoria `CATEGORY_MEDIA`: `coverCheck`
   ESSENTIAL, `coverAltCheck` e `bodyImagesAltCheck` RECOMMENDED): già
   esposto sia per singolo articolo (card "Qualità editoriale" nella pagina
   di modifica) sia sitewide (`/admin/qualita-editoriale`, Missione 35 di
   questo batch).

Nessuna duplicazione necessaria: la combinazione di queste quattro capacità
copre già l'intero outcome richiesto dalla Missione 37.

## Gap reale trovato e colmato

Il livello SERVIZIO era testato in profondità (unit test dedicati per
`ArticleContentHealthService` e `SourceImageAttributionHealthService`, più
`test_editor_sees_da_rivedere_status_when_a_real_problem_exists` che prova
solo il conteggio riassuntivo in KPI), ma nessun test HTTP aveva mai provato
che una riga di warning legata specificamente all'immagine di copertina
comparisse davvero — con titolo, link di modifica, badge di priorità e
motivo — nella sezione "Da sistemare" della pagina reale. Stesso pattern di
gap già trovato e colmato per Percorsi (Missione 31) e per i metadati SEO
(Missione 36).

Aggiunto
`test_editor_sees_a_missing_cover_warning_row_rendered_in_the_da_sistemare_section`
in `tests/Feature/Admin/EditorialOperationsDashboardControllerTest.php`: crea
un articolo pubblicato senza `cover_image` e verifica che titolo, link di
modifica, badge HIGH e la label "Copertina" compaiano nell'HTML reale della
pagina.

Nessuna modifica al servizio o alla vista è stata necessaria: si tratta di
sola copertura di regressione su un comportamento già corretto.

## Verifica

- `php artisan test --filter=EditorialOperationsDashboard|SourceImageAttribution|EditorialQuality|ArticleContentHealth`
  — 169/169 passed.
- Nessuna nuova query, nessuna nuova regola di dominio, nessun nuovo file di
  produzione modificato.
