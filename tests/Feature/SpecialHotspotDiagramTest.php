<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SpecialHotspotDiagramTest extends TestCase
{
    use RefreshDatabase;

    private function renderHotspot(string $image): string
    {
        return Blade::render(
            '<x-special.hotspot-diagram :image="$image" alt="Diagramma di prova" :hotspots="$hotspots" />',
            [
                'image' => $image,
                'hotspots' => [
                    ['x' => 10, 'y' => 10, 'title' => 'Punto uno', 'text' => 'Descrizione uno.'],
                ],
            ]
        );
    }

    // Senza width/height sull'<img>, .sp-hotspot__image (width:100%;
    // height:auto) non riserva spazio prima del caricamento: il browser
    // calcola l'altezza reale solo a immagine caricata, causando un layout
    // shift (e, a cascata, l'atterraggio sbagliato di qualunque ancora verso
    // una sezione successiva calcolata prima dello shift). Per un'immagine
    // risolvibile localmente, il componente deve quindi emettere entrambi
    // gli attributi con le dimensioni reali del file.
    public function test_local_image_gets_intrinsic_width_and_height_attributes(): void
    {
        $html = $this->renderHotspot(asset('images/turing/enigma/cutaway-enigma.png'));

        [$width, $height] = getimagesize(public_path('images/turing/enigma/cutaway-enigma.png'));

        $this->assertStringContainsString('width="' . $width . '"', $html);
        $this->assertStringContainsString('height="' . $height . '"', $html);
    }

    // Un'immagine non risolvibile sul filesystem locale (URL realmente
    // esterno, o file inesistente) non deve rompere il componente: niente
    // width/height, ma nessun errore — il comportamento torna quello
    // preesistente all'introduzione del fix.
    public function test_unresolvable_image_degrades_without_width_or_height_attributes(): void
    {
        $html = $this->renderHotspot('https://example.org/immagine-esterna-inesistente.png');

        $this->assertStringNotContainsString('width="', $html);
        $this->assertStringNotContainsString('height="', $html);
        $this->assertStringContainsString('sp-hotspot__image', $html);
    }

    // .sp-hotspot__image resta width:100%;height:auto (nessuna regressione
    // sul comportamento responsive): il fix aggiunge solo gli attributi
    // HTML, da cui il browser deriva l'aspect-ratio prima del caricamento,
    // senza fissare una dimensione assoluta che romperebbe il ridimensionamento.
    public function test_component_css_still_scales_the_image_to_its_container(): void
    {
        $css = file_get_contents(public_path('css/turing-enigma.css'));

        $this->assertMatchesRegularExpression(
            '/\.sp-hotspot__image\s*\{[^}]*width:\s*100%;[^}]*height:\s*auto;/s',
            $css
        );
    }
}
