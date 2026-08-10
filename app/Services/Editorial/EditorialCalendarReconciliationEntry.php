<?php

namespace App\Services\Editorial;

/**
 * Stato di riconciliazione completo di UNA voce di calendario: il match
 * trovato (vedi EditorialCalendarMatch) più l'eventuale discrepanza tra
 * quanto pianificato e quanto reale. Costruita esclusivamente da
 * EditorialCalendarReconciliationService — vedi lì per come ogni
 * discrepancyType viene deciso.
 */
final readonly class EditorialCalendarReconciliationEntry
{
    /** Nessuna differenza: titolo identico, data allineata, nessuno stato dichiarato in conflitto. */
    public const DISCREPANCY_NONE = 'none';

    /** Match NORMALIZED: il titolo reale differisce solo per forma (maiuscole, punteggiatura, spazi). */
    public const DISCREPANCY_TITLE_MINOR_CHANGE = 'title_minor_change';

    /** Un solo candidato "vicino" ma non identico: probabile riscrittura del titolo, mai confermata automaticamente. */
    public const DISCREPANCY_TITLE_MAJOR_CHANGE = 'title_major_change';

    /** L'articolo risulta programmato/pubblicato PRIMA della data pianificata nel calendario. */
    public const DISCREPANCY_DATE_EARLY = 'date_early';

    /** L'articolo risulta programmato/pubblicato DOPO la data pianificata nel calendario. */
    public const DISCREPANCY_DATE_LATE = 'date_late';

    /** Lo stato dichiarato nel calendario non corrisponde allo stato reale dell'articolo (solo quando lo stato dichiarato è riconoscibile con certezza). */
    public const DISCREPANCY_STATUS_MISMATCH = 'status_mismatch';

    /** Nessun articolo del CMS corrisponde a questa voce. */
    public const DISCREPANCY_MISSING_ARTICLE = 'missing_article';

    /** Più candidati plausibili, nessuno abbastanza certo da proporre come "il" match: richiede una decisione umana. */
    public const DISCREPANCY_REQUIRES_REVIEW = 'requires_review';

    public function __construct(
        public EditorialCalendarMatch $match,
        public string $discrepancyType,
    ) {}

    public function entry(): EditorialCalendarEntry
    {
        return $this->match->entry;
    }

    public function hasDiscrepancy(): bool
    {
        return $this->discrepancyType !== self::DISCREPANCY_NONE;
    }
}
