<?php

namespace App\Services\EditorialQuality;

/**
 * Esito di UN controllo del Quality Gate — sempre una constatazione
 * ("manca una cover"), mai un giudizio editoriale ("questa cover è
 * brutta"). Nessun controllo scrive mai su un articolo: questo DTO è
 * sempre calcolato, mai persistito (vedi EditorialQualityChecker).
 */
final readonly class EditorialQualityCheckResult
{
    public const STATUS_PASS = 'pass';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAIL = 'fail';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const IMPORTANCE_ESSENTIAL = 'essential';

    public const IMPORTANCE_RECOMMENDED = 'recommended';

    public const CATEGORY_CONTENT = 'content';

    public const CATEGORY_MEDIA = 'media';

    public const CATEGORY_SEO = 'seo';

    public const CATEGORY_STRUCTURE = 'structure';

    public const CATEGORY_SOURCES = 'sources';

    public const CATEGORY_DISCOVERY = 'discovery';

    public const CATEGORY_PUBLISHING = 'publishing';

    public function __construct(
        /** Codice stabile, machine-readable (es. "title_present") — mai tradotto, usato da JSON/test. */
        public string $code,
        /** Etichetta leggibile in italiano (es. "Titolo"). */
        public string $label,
        public string $status,
        public string $importance,
        public string $category,
        /** Spiegazione neutra, mai accusatoria — descrive un fatto verificabile, non un giudizio. */
        public string $message,
        /** @var array<string, mixed>|null */
        public ?array $details = null,
    ) {}

    public function isApplicable(): bool
    {
        return $this->status !== self::STATUS_NOT_APPLICABLE;
    }
}
