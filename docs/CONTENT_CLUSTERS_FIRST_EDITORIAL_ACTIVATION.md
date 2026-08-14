# Content Clusters — First Editorial Activation Plan

**Prepared:** 2026-08-14  
**Repository baseline:** `main @ 4afdb310d085e0b4e92b6f54fbe1d2bfff54dd9e`  
**Purpose:** prepare the first human-reviewed editorial activation of Percorsi after production deployment.

This document is a planning artifact only. It does **not** authorize a production backfill, automatic activation, automatic membership assignment, article publication, or any Phase 2D runtime behavior.

## Source of truth and evidence classes

The canonical repository source for the initial four Percorsi is `config/content-clusters-initial.php`. It defines the Percorso slug/name, one proposed pillar, a fixed article order and the intended `primary` membership. The repository does **not** contain a current copy of the production article catalog for the 20 mapped slugs, so production existence/publication state must be verified by a human before any apply/activation action.

Evidence labels used below:

- `EXACT_MAPPING` — the article/Percorso pair is explicitly present in the versioned initial mapping.
- `STRONG_EDITORIAL_MATCH` — supported by additional clear repository/editorial evidence, but not explicitly mapped.
- `CATEGORY_SIGNAL_ONLY` — only the current suggestion engine category-cohort signal supports the pair.
- `AMBIGUOUS` — evidence is insufficient or conflicting; human judgment is required.
- `NOT_RECOMMENDED` — repository evidence argues against inclusion.

For the first activation, this plan recommends **only the 20 `EXACT_MAPPING` pairs already versioned**. It does not promote category-only suggestions or infer new memberships.

## Mandatory safety checklist before activating any Percorso

1. Keep the Percorso **inactive** while assembling it.
2. Verify every mapped slug resolves to the intended article in the admin catalog.
3. Verify every article intended for the public Percorso is currently `published`; do not use `--allow-non-published` for first activation.
4. Resolve every preview `MISSING` or `BLOCKED` result before any apply action.
5. Confirm article order, pillar and primary membership manually.
6. Add short/editorial descriptions deliberately; the initial mapping does not version these fields.
7. Confirm at least one public article exists before setting `is_active=true`.
8. Review the public detail and an article continuation state after activation.
9. Never treat suggestion confidence as an automatic-accept threshold.

Current public policy note: an active Percorso with zero published articles can still resolve as a valid detail page while remaining absent from the sitemap. Therefore the operational rule for first activation is: **do not activate an empty Percorso**.

---

## IA spiegata

- **Slug:** `ia-spiegata`
- **Name:** IA spiegata
- **Activation readiness:** `READY_FOR_HUMAN_REVIEWED_PREVIEW`
- **Proposed pillar:** `intelligenza-artificiale-da-turing-a-chatgpt` — `EXACT_MAPPING`
- **Proposed primary:** same article — `EXACT_MAPPING`

### Description drafts

**Short description draft — `HUMAN_DECISION_REQUIRED`**  
Un percorso per capire i concetti fondamentali dell'intelligenza artificiale, dal quadro storico ai modelli che imparano dai dati.

**Editorial description draft — `HUMAN_DECISION_REQUIRED`**  
Una sequenza introduttiva che parte dal contesto dell'intelligenza artificiale, passa per machine learning, deep learning e reti neurali e chiude con un'applicazione concreta nei videogiochi. Il testo finale deve essere approvato editorialmente; queste descrizioni non fanno parte del mapping versionato.

### Ordered article plan

| Pos. | Article slug | Primary | Evidence | Editorial note |
|---:|---|:---:|---|---|
| 1 | `intelligenza-artificiale-da-turing-a-chatgpt` | YES | `EXACT_MAPPING` | Pillar candidate and entry point. |
| 2 | `machine-learning-come-imparano-le-macchine` | NO | `EXACT_MAPPING` | Core learning concept. |
| 3 | `deep-learning-reti-neurali-profonde` | NO | `EXACT_MAPPING` | Progresses from ML to deep learning. |
| 4 | `reti-neurali-spiegate` | NO | `EXACT_MAPPING` | Supporting conceptual explanation. |
| 5 | `intelligenza-artificiale-nei-videogiochi` | NO | `EXACT_MAPPING` | Applied closing example. |

### Missing / ambiguous / excluded

- **Missing articles:** `HUMAN_DECISION_REQUIRED` — repository-only analysis cannot confirm current production existence/status. The dry-run/admin catalog must verify all five slugs.
- **Additional articles:** none recommended automatically. New candidates from category evidence remain suggestions, not memberships.
- **Editorial ambiguity:** review whether the progression `deep-learning` before `reti-neurali-spiegate` is the preferred learning order; preserve the versioned order unless the editor deliberately changes it.

---

## Spazio

- **Slug:** `spazio`
- **Name:** Spazio
- **Activation readiness:** `READY_FOR_HUMAN_REVIEWED_PREVIEW`
- **Proposed pillar:** `buchi-neri-cosa-sono-come-si-formano` — `EXACT_MAPPING`
- **Proposed primary:** same article — `EXACT_MAPPING`

### Description drafts

**Short description draft — `HUMAN_DECISION_REQUIRED`**  
Un percorso tra buchi neri, espansione cosmica, esopianeti, materia oscura e relatività generale.

**Editorial description draft — `HUMAN_DECISION_REQUIRED`**  
Un itinerario che usa i buchi neri come punto d'ingresso e allarga progressivamente lo sguardo alla struttura e all'evoluzione dell'Universo, ai mondi extrasolari, alla materia oscura e alla geometria dello spaziotempo. Il testo finale richiede approvazione editoriale.

### Ordered article plan

| Pos. | Article slug | Primary | Evidence | Editorial note |
|---:|---|:---:|---|---|
| 1 | `buchi-neri-cosa-sono-come-si-formano` | YES | `EXACT_MAPPING` | Pillar candidate and accessible entry point. |
| 2 | `universo-in-espansione` | NO | `EXACT_MAPPING` | Broadens to cosmology. |
| 3 | `esopianeti-mondi-oltre-sistema-solare` | NO | `EXACT_MAPPING` | Observational/applied exploration. |
| 4 | `materia-oscura-enigma-universo` | NO | `EXACT_MAPPING` | Deeper cosmological open question. |
| 5 | `teoria-relativita-generale-curvatura-spaziotempo` | NO | `EXACT_MAPPING` | More theoretical closing step. |

### Missing / ambiguous / excluded

- **Missing articles:** `HUMAN_DECISION_REQUIRED` — verify all five production slugs/statuses.
- **Additional articles:** none automatically recommended.
- **Editorial ambiguity:** the current order mixes cosmology, exoplanets and theory; it is a valid versioned sequence but the editor should confirm this is the desired pedagogical progression before activation.

---

## Scienza quotidiana

- **Slug:** `scienza-quotidiana`
- **Name:** Scienza quotidiana
- **Activation readiness:** `READY_FOR_HUMAN_REVIEWED_PREVIEW`
- **Proposed pillar:** `why-science-matters-scienza-quotidiana` — `EXACT_MAPPING`
- **Proposed primary:** same article — `EXACT_MAPPING`

### Description drafts

**Short description draft — `HUMAN_DECISION_REQUIRED`**  
La fisica e la scienza dietro fenomeni e oggetti che incontriamo ogni giorno.

**Editorial description draft — `HUMAN_DECISION_REQUIRED`**  
Un percorso pensato per mostrare come concetti scientifici generali emergano nell'esperienza quotidiana: colori del cielo, galleggiamento del ghiaccio, arcobaleni e funzionamento del microonde. Il testo finale deve essere approvato dall'editor.

### Ordered article plan

| Pos. | Article slug | Primary | Evidence | Editorial note |
|---:|---|:---:|---|---|
| 1 | `why-science-matters-scienza-quotidiana` | YES | `EXACT_MAPPING` | Pillar candidate / editorial framing. |
| 2 | `perche-cielo-blu-tramonto-rosso` | NO | `EXACT_MAPPING` | Everyday optical phenomenon. |
| 3 | `perche-ghiaccio-galleggia` | NO | `EXACT_MAPPING` | Everyday material/thermodynamic phenomenon. |
| 4 | `perche-vediamo-un-arcobaleno` | NO | `EXACT_MAPPING` | Second optical example. |
| 5 | `come-funziona-forno-microonde` | NO | `EXACT_MAPPING` | Applied household technology. |

### Missing / ambiguous / excluded

- **Missing articles:** `HUMAN_DECISION_REQUIRED` — verify all five production slugs/statuses.
- **Additional articles:** do not add from broad category affinity alone during first activation.
- **Editorial ambiguity:** none blocking in the versioned sequence; final wording and scope remain a human editorial decision.

---

## Energia e batterie

- **Slug:** `energia-batterie`
- **Name:** Energia e batterie
- **Activation readiness:** `READY_FOR_HUMAN_REVIEWED_PREVIEW`
- **Proposed pillar:** `come-funziona-batteria-ioni-litio` — `EXACT_MAPPING`
- **Proposed primary:** same article — `EXACT_MAPPING`

### Description drafts

**Short description draft — `HUMAN_DECISION_REQUIRED`**  
Come funzionano le batterie moderne, dai componenti interni alla ricarica rapida e allo stato solido.

**Editorial description draft — `HUMAN_DECISION_REQUIRED`**  
Un percorso che parte dal funzionamento delle batterie agli ioni di litio, entra nei componenti elettrochimici e prosegue verso limiti, alternative allo stato solido e ricarica rapida. Il testo definitivo richiede approvazione editoriale.

### Ordered article plan

| Pos. | Article slug | Primary | Evidence | Editorial note |
|---:|---|:---:|---|---|
| 1 | `come-funziona-batteria-ioni-litio` | YES | `EXACT_MAPPING` | Pillar candidate and foundation. |
| 2 | `dentro-una-batteria-anodo-catodo-elettrolita-separatore` | NO | `EXACT_MAPPING` | Component-level deepening. |
| 3 | `perche-calze-legate-ioni-litio` | NO | `EXACT_MAPPING` | `HUMAN_DECISION_REQUIRED`: verify the intended article/title behind this unusual canonical slug before membership. Do not silently rename it. |
| 4 | `batterie-stato-solido-sfide-vantaggi` | NO | `EXACT_MAPPING` | Alternative technology / trade-offs. |
| 5 | `come-funziona-ricarica-rapida` | NO | `EXACT_MAPPING` | Applied charging behavior. |

### Missing / ambiguous / excluded

- **Missing articles:** `HUMAN_DECISION_REQUIRED` — verify all five production slugs/statuses.
- **Specific ambiguity:** `perche-calze-legate-ioni-litio` must be checked manually against the intended production article. It is versioned and therefore an `EXACT_MAPPING`, but its wording is unusual enough that the plan must not assume it is error-free.
- **Additional articles:** none automatically recommended.

---

## How to interpret suggestion confidence during first activation

The current engine has evidence semantics, not a formal product threshold for automatic action:

- **90–100:** treat as strong evidence requiring human review. The current exact versioned mapping produces confidence `100`.
- **70–89:** useful review band, but the current canonical rules do not need to produce a score in this interval.
- **<70:** weak/supporting evidence. Current category-only evidence produces confidence `65` and should be treated as a prompt to inspect, not as a membership instruction.

`NO_PRODUCT_THRESHOLD_DEFINED`

There is no approved score at which a suggestion should be accepted automatically. Accept/reject remains an editor action.

## Recommended first-session sequence

For each Percorso, one at a time:

1. Review the versioned plan above.
2. Verify mapped article slugs/statuses in the admin catalog or an authorized preview.
3. Create/keep the Percorso inactive.
4. Enter approved metadata/descriptions.
5. Add only verified `EXACT_MAPPING` members in the intended order.
6. Confirm a single intended primary and published pillar.
7. Inspect suggestions separately; do not use them to silently expand the initial membership.
8. Confirm public-eligible content count is at least one.
9. Activate only after the editor intentionally approves the final composition.
10. Observe public navigation and second-reading analytics before expanding automation or membership breadth.

## Human decisions required before first public activation

- Approve short and editorial descriptions for all four Percorsi.
- Verify current existence and publication state of each of the 20 mapped article slugs.
- Confirm order/pillar/primary for each Percorso.
- Verify the intended target of `perche-calze-legate-ioni-litio`.
- Decide whether any versioned ordering should be changed for pedagogy; do not change by inference.
- Decide which single Percorso to activate first and when.

No production mutation was performed while preparing this plan.
