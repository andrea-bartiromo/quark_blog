<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    protected $fillable = [
        'user_id', 'filename', 'disk_name', 'mime_type', 'size', 'alt_text',
    ];

    protected $appends = ['url', 'human_size'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** URL pubblica dell'immagine */
    public function getUrlAttribute(): string
    {
        return asset('assets/img/'.$this->disk_name);
    }

    /** Dimensione leggibile (es. "2.4 MB") */
    public function getHumanSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
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
