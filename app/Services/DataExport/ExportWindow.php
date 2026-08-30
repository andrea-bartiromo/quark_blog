<?php

namespace App\Services\DataExport;

use Carbon\CarbonImmutable;

final readonly class ExportWindow
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $timezone,
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromValidated(array $validated): self
    {
        $timezone = $validated['timezone'];

        return new self(
            CarbonImmutable::parse($validated['from'], $timezone)->startOfDay(),
            CarbonImmutable::parse($validated['to'], $timezone)->endOfDay(),
            $timezone,
        );
    }

    /** @return array<string, string> */
    public function metadata(): array
    {
        return [
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
            'timezone' => $this->timezone,
        ];
    }
}
