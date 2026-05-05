<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'name', 'position', 'type', 'active', 'priority',
        'adsense_publisher_id', 'adsense_slot_id', 'adsense_format',
        'banner_image', 'banner_url', 'banner_alt',
        'html_code', 'notes',
    ];

    protected $casts = [
        'active'   => 'boolean',
        'priority' => 'integer',
    ];

    // Posizioni disponibili
    const POSITIONS = [
        'articolo-top'    => 'Sopra il testo articolo',
        'articolo-bottom' => 'Sotto l\'articolo (dopo autore)',
        'sidebar'         => 'Sidebar laterale',
        'lista'           => 'Tra gli articoli in lista',
        'footer'          => 'Sopra il footer',
    ];

    // Tipi disponibili
    const TYPES = [
        'adsense' => 'Google AdSense',
        'banner'  => 'Banner immagine',
        'html'    => 'Codice HTML personalizzato',
    ];

    // Recupera tutti gli annunci attivi per una posizione
    public static function forPosition(string $position)
    {
        return static::where('position', $position)
                     ->where('active', true)
                     ->orderBy('priority', 'desc')
                     ->get();
    }

    // Renderizza l'HTML dell'annuncio
    public function render(): string
    {
        if (!$this->active) return '';

        return match($this->type) {
            'adsense' => $this->renderAdSense(),
            'banner'  => $this->renderBanner(),
            'html'    => $this->html_code ?? '',
            default   => '',
        };
    }

    private function renderAdSense(): string
    {
        if (!$this->adsense_publisher_id || !$this->adsense_slot_id) return '';

        return sprintf(
            '<ins class="adsbygoogle" style="display:block;" data-ad-client="%s" data-ad-slot="%s" data-ad-format="%s" data-full-width-responsive="true"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script>',
            e($this->adsense_publisher_id),
            e($this->adsense_slot_id),
            e($this->adsense_format)
        );
    }

    private function renderBanner(): string
    {
        if (!$this->banner_image) return '';

        $img = sprintf(
            '<img src="%s" alt="%s" style="max-width:100%;height:auto;border-radius:8px;">',
            asset('assets/img/' . $this->banner_image),
            e($this->banner_alt ?? '')
        );

        if ($this->banner_url) {
            return sprintf('<a href="%s" target="_blank" rel="noopener sponsored">%s</a>', e($this->banner_url), $img);
        }

        return $img;
    }
}