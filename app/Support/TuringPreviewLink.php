<?php

namespace App\Support;

/**
 * Cantiere 63 (programma "100 cantieri Kairus", fix Codex P2 su PR #629):
 * in modalità anteprima (Admin\TuringController::previewHub()/
 * previewChapter()) l'intera rete di navigazione fra hub e capitoli deve
 * restare dentro le rotte di anteprima — mai un vero link verso
 * /turing/*, che con turing.chapters_public=false reindirizzerebbe
 * l'editor fuori dall'anteprima, verso la landing "In arrivo"
 * (TuringPageController::index()/TuringPublicController redirect a
 * chapters_public=false, invariati da questo cantiere).
 *
 * Unica fonte di verità per risolvere un link verso l'hub o un capitolo
 * Turing: usata da ogni punto della rete che prima chiamava
 * route('turing')/route('turing.{capitolo}') direttamente (breadcrumb,
 * CTA "Continua il percorso", partial dell'hub, ai.blade.php).
 */
class TuringPreviewLink
{
    public static function hub(bool $previewMode): string
    {
        return $previewMode ? route('admin.turing.preview') : route('turing');
    }

    public static function chapter(string $chapter, bool $previewMode): string
    {
        return $previewMode
            ? route('admin.turing.preview-chapter', $chapter)
            : route('turing.'.$chapter);
    }
}
