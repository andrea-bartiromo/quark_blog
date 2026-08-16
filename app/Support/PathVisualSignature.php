<?php

namespace App\Support;

use App\Models\ContentCluster;

/**
 * Firma visiva deterministica di un Percorso (Missione PERCORSI WOW, Pass
 * 2 — "Automatic Path Visual Identity"). Nessuna colonna nuova: deriva un
 * indice di preset stabile dallo slug del Percorso (il suo identificatore
 * editoriale più significativo — fallback sull'id se lo slug fosse vuoto,
 * caso che l'invariante DB non permette ma che questa funzione gestisce
 * comunque senza eccezioni).
 *
 * Deterministica per costruzione: stesso Percorso, stesso preset, sempre
 * — nessuno stato, nessuna casualità, nessuna dipendenza da request/sessione.
 * Un Percorso futuro con uno slug mai visto ottiene automaticamente uno
 * dei PRESET_COUNT preset esistenti tramite lo stesso hash, senza alcuna
 * configurazione per singolo Percorso (mai un if slug === ...).
 *
 * Il significato di ogni preset (colori, posizione del motivo decorativo)
 * vive interamente in CSS (.path-signature-0 … .path-signature-N-1) —
 * questa classe conosce solo QUALE preset selezionare, mai COSA contiene:
 * separazione deliberata, così i preset restano un'unica fonte di verità
 * del linguaggio Kairus, modificabile senza toccare PHP.
 */
class PathVisualSignature
{
    public const PRESET_COUNT = 6;

    public static function presetIndex(ContentCluster $cluster): int
    {
        $seed = filled($cluster->slug) ? $cluster->slug : (string) $cluster->id;

        return abs(crc32($seed)) % self::PRESET_COUNT;
    }

    public static function cssClass(ContentCluster $cluster): string
    {
        return 'path-signature-'.self::presetIndex($cluster);
    }
}
