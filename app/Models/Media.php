<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    /**
     * Tipi MIME riconosciuti come documenti ai fini del filtro/statistiche
     * "images / documents / others". Nessun file di questo tipo transita
     * ancora dai flussi di upload esistenti (limitati alle immagini), ma la
     * colonna mime_type e generica: la categorizzazione resta corretta se in
     * futuro verranno registrati anche documenti (es. import legacy).
     */
    private const DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/rtf',
        'text/plain',
        'text/csv',
    ];

    protected $fillable = [
        'user_id', 'filename', 'disk_name', 'mime_type', 'size', 'alt_text',
    ];

    protected $appends = ['url', 'human_size'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeImages(Builder $q): Builder
    {
        return $q->where('mime_type', 'like', 'image/%');
    }

    public function scopeDocuments(Builder $q): Builder
    {
        return $q->whereIn('mime_type', self::DOCUMENT_MIME_TYPES);
    }

    public function scopeOthers(Builder $q): Builder
    {
        return $q->where('mime_type', 'not like', 'image/%')
            ->whereNotIn('mime_type', self::DOCUMENT_MIME_TYPES);
    }

    /** URL pubblica dell'immagine */
    public function getUrlAttribute(): string
    {
        return asset('assets/img/'.$this->disk_name);
    }

    /** Dimensione leggibile (es. "2.4 MB") */
    public function getHumanSizeAttribute(): string
    {
        return static::humanFileSize($this->size);
    }

    public static function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 1).' '.$units[$unit];
    }

    /**
     * Riferimento statico protetto (hardcoded in controller/viste/seeder
     * versionati, elencato in config/media.php): non eliminabile ne'
     * spostabile, indipendentemente dal fatto che risulti "usato" nei
     * contenuti dinamici del database.
     */
    public function isProtected(): bool
    {
        return in_array($this->disk_name, config('media.protected_disk_names', []), true);
    }
}
