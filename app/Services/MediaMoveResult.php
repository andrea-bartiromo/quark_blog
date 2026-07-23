<?php

namespace App\Services;

use App\Models\Media;

final class MediaMoveResult
{
    private function __construct(
        public readonly string $status,
        public readonly Media $media,
        public readonly ?string $oldDiskName,
        public readonly ?string $newDiskName,
        public readonly string $message,
        public readonly array $preflight = [],
    ) {}

    public static function moved(Media $media, string $oldDiskName, string $newDiskName, array $preflight): self
    {
        return new self('moved', $media, $oldDiskName, $newDiskName, 'Immagine spostata con successo.', $preflight);
    }

    public static function noop(Media $media, string $message): self
    {
        return new self('noop', $media, $media->disk_name, $media->disk_name, $message);
    }

    public static function blocked(Media $media, array $preflight): self
    {
        return new self(
            'blocked',
            $media,
            $media->disk_name,
            null,
            'Spostamento bloccato: sono presenti riferimenti non aggiornabili in sicurezza.',
            $preflight
        );
    }

    public function isMoved(): bool
    {
        return $this->status === 'moved';
    }

    public function isNoop(): bool
    {
        return $this->status === 'noop';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }
}
