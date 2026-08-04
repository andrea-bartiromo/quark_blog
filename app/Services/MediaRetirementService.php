<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Ritira in sicurezza un file media non più necessario (tipicamente
 * l'immagine precedente dopo una sostituzione: copertina categoria, foto
 * profilo, ecc.), rimuovendolo da entrambe le directory (public/assets/img
 * e l'eventuale radice pubblica secondaria) e dal relativo record Media,
 * ma solo se non è ancora referenziato altrove nel sito.
 *
 * Operazione sempre best-effort: non lancia mai eccezioni. Un fallimento
 * nella sincronizzazione verso la radice pubblica secondaria viene
 * registrato con un warning strutturato e interrompe il ritiro (nessuna
 * modifica parziale: se non si può garantire la rimozione da entrambe le
 * directory, non si tocca né l'altra directory né il record Media — il
 * file precedente resta semplicemente presente e coerente ovunque, il
 * chiamante non deve mai bloccare la propria azione principale per questo).
 */
class MediaRetirementService
{
    public function __construct(
        private readonly PublicMediaSyncService $publicMediaSync,
        private readonly MediaUsageService $mediaUsageService,
    ) {}

    /**
     * @param  string  $diskName  Percorso relativo del file da ritirare (es.
     *                            "foto.jpg" o "categories/foto.jpg"), nella
     *                            stessa convenzione già usata altrove per i
     *                            record Media.
     * @param  string  $operation  Etichetta breve del contesto chiamante
     *                             (es. "profile_photo_replaced",
     *                             "category_image_replaced"), usata solo
     *                             per il log in caso di fallimento.
     * @return bool true se il file è stato effettivamente ritirato.
     */
    public function retireIfUnused(string $diskName, string $operation): bool
    {
        $media = Media::where('disk_name', $diskName)->first();

        if ($media?->isProtected()) {
            return false;
        }

        // Nessun record Media trovato: si usa comunque un'istanza
        // "sonda" (mai salvata) solo per interrogare MediaUsageService,
        // che legge esclusivamente disk_name dall'oggetto passato.
        $probe = $media ?? new Media(['disk_name' => $diskName]);

        if ($this->mediaUsageService->usageFor($probe) !== []) {
            return false;
        }

        try {
            $this->publicMediaSync->delete($diskName);
        } catch (RuntimeException $exception) {
            Log::warning('MediaRetirementService: pulizia file precedente non riuscita nella directory pubblica secondaria.', [
                'operation' => $operation,
                'disk_name' => $diskName,
                'exception' => $exception,
            ]);

            return false;
        }

        $path = public_path('assets/img/'.$diskName);
        if (is_file($path)) {
            @unlink($path);
        }

        $media?->delete();

        return true;
    }
}
