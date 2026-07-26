<?php

namespace App\Services\Concerns;

/**
 * Logica condivisa tra MediaReferenceService (preflight di spostamento) e
 * MediaUsageService (rilevamento utilizzi): entrambi devono camminare i
 * contenuti JSON di SpecialPage.content e riconoscere le stesse chiavi
 * censite come "immagine" per evitare che le due funzionalità divergano
 * silenziosamente su cosa considerano un riferimento valido.
 */
trait ScansJsonContentLeaves
{
    /**
     * Chiavi JSON di SpecialPage.content note per contenere un disk_name di
     * Media. L'indice numerico delle liste (cards, editorial_blocks, ...) e
     * normalizzato a "*" prima del confronto.
     *
     * @var list<string>
     */
    private const CONTENT_PATH_ALLOWLIST = [
        'hero.background_image',
        'hero.portrait_image',
        'home_teaser.background_image',
        'intro.background_image',
        'why.background_image',
        'final.background_image',
        'cards.*.image',
        'editorial_blocks.*.image',
        'editorial_blocks.*.background_image',
        'internal_links.*.image',
        'decorative_images.*.image',
        'why.items.*.image',
        'timeline.*.image',
    ];

    /**
     * @return list<array{path: string, value: string}>
     */
    private function collectStringLeaves(mixed $node, string $path = ''): array
    {
        $leaves = [];

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $childPath = $path === '' ? (string) $key : $path.'.'.$key;
                array_push($leaves, ...$this->collectStringLeaves($value, $childPath));
            }
        } elseif (is_string($node) && $node !== '') {
            $leaves[] = ['path' => $path, 'value' => $node];
        }

        return $leaves;
    }

    private function isSupportedContentPath(string $path): bool
    {
        $normalized = preg_replace('/\.\d+\./', '.*.', '.'.$path.'.');
        $normalized = trim($normalized, '.');

        return in_array($normalized, self::CONTENT_PATH_ALLOWLIST, true);
    }
}
