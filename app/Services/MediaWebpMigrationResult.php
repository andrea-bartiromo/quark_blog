<?php

namespace App\Services;

use App\Models\Media;

/**
 * Una riga del manifest prodotto da media:convert-webp (dry-run o
 * --execute): esattamente cosa e' successo (o accadrebbe) per un Media,
 * mai un aggregato — l'aggregazione e' compito del comando/report.
 */
final class MediaWebpMigrationResult
{
    private function __construct(
        public readonly string $status,
        public readonly int $mediaId,
        public readonly ?string $originalDiskName,
        public readonly ?string $newDiskName,
        public readonly ?int $originalBytes,
        public readonly ?int $webpBytes,
        public readonly ?int $savingBytes,
        public readonly ?float $savingPercent,
        public readonly ?array $dimensions,
        public readonly ?string $reason,
        public readonly ?int $updatedReferenceCount = null,
    ) {}

    public static function planned(
        Media $media,
        string $webpDiskName,
        int $originalBytes,
        ?int $webpBytes,
        ?array $dimensions,
        int $updatedReferenceCount,
    ): self {
        [$savingBytes, $savingPercent] = self::savings($originalBytes, $webpBytes);

        return new self(
            'planned',
            $media->id,
            $media->disk_name,
            $webpDiskName,
            $originalBytes,
            $webpBytes,
            $savingBytes,
            $savingPercent,
            $dimensions,
            null,
            $updatedReferenceCount,
        );
    }

    public static function converted(
        Media $media,
        string $originalDiskName,
        string $webpDiskName,
        int $originalBytes,
        int $webpBytes,
        ?array $dimensions,
        int $updatedReferenceCount,
    ): self {
        [$savingBytes, $savingPercent] = self::savings($originalBytes, $webpBytes);

        return new self(
            'converted',
            $media->id,
            $originalDiskName,
            $webpDiskName,
            $originalBytes,
            $webpBytes,
            $savingBytes,
            $savingPercent,
            $dimensions,
            null,
            $updatedReferenceCount,
        );
    }

    public static function skipped(int $mediaId, ?string $diskName, string $bucket, string $reason): self
    {
        return new self('skipped_'.$bucket, $mediaId, $diskName, null, null, null, null, null, null, $reason);
    }

    public static function missingSource(int $mediaId, ?string $diskName, string $reason): self
    {
        return new self('missing_source', $mediaId, $diskName, null, null, null, null, null, null, $reason);
    }

    public static function failed(int $mediaId, ?string $diskName, string $reason): self
    {
        return new self('failed', $mediaId, $diskName, null, null, null, null, null, null, $reason);
    }

    public function isConvertedOrPlanned(): bool
    {
        return in_array($this->status, ['converted', 'planned'], true);
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * @return array{0: ?int, 1: ?float}
     */
    private static function savings(int $originalBytes, ?int $webpBytes): array
    {
        if ($webpBytes === null) {
            return [null, null];
        }

        $savingBytes = $originalBytes - $webpBytes;
        $savingPercent = $originalBytes > 0 ? round(($savingBytes / $originalBytes) * 100, 1) : 0.0;

        return [$savingBytes, $savingPercent];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'media_id' => $this->mediaId,
            'original_path' => $this->originalDiskName,
            'new_path' => $this->newDiskName,
            'original_bytes' => $this->originalBytes,
            'webp_bytes' => $this->webpBytes,
            'saving_bytes' => $this->savingBytes,
            'saving_percent' => $this->savingPercent,
            'dimensions' => $this->dimensions,
            'updated_reference_count' => $this->updatedReferenceCount,
            'reason' => $this->reason,
        ];
    }
}
