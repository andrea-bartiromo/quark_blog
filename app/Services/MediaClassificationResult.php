<?php

namespace App\Services;

final class MediaClassificationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $referencesFound
     * @param  array<int, array<string, mixed>>  $blockingDetails
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly string $currentDiskName,
        public readonly ?string $currentFolder,
        public readonly ?string $proposedFolder,
        public readonly ?string $proposedDiskName,
        public readonly ?string $domain,
        public readonly string $status,
        public readonly string $reason,
        public readonly array $referencesFound,
        public readonly int $updatableCount,
        public readonly int $blockingCount,
        public readonly array $blockingDetails,
        public readonly string $confidence,
        public readonly ?string $outcome = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function isMovable(): bool
    {
        return $this->status === 'movable';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'media_id' => $this->mediaId,
            'current_disk_name' => $this->currentDiskName,
            'current_folder' => $this->currentFolder,
            'proposed_folder' => $this->proposedFolder,
            'proposed_disk_name' => $this->proposedDiskName,
            'domain' => $this->domain,
            'status' => $this->status,
            'reason' => $this->reason,
            'references_found' => count($this->referencesFound),
            'updatable_count' => $this->updatableCount,
            'blocking_count' => $this->blockingCount,
            'blocking_details' => $this->blockingDetails,
            'confidence' => $this->confidence,
            'outcome' => $this->outcome,
            'error_message' => $this->errorMessage,
        ];
    }

    public function withOutcome(string $outcome, ?string $errorMessage = null): self
    {
        return new self(
            $this->mediaId,
            $this->currentDiskName,
            $this->currentFolder,
            $this->proposedFolder,
            $this->proposedDiskName,
            $this->domain,
            $this->status,
            $this->reason,
            $this->referencesFound,
            $this->updatableCount,
            $this->blockingCount,
            $this->blockingDetails,
            $this->confidence,
            $outcome,
            $errorMessage,
        );
    }
}
