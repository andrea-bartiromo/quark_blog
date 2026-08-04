<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Replica in una document root pubblica secondaria (tipicamente
 * public_html su cPanel, separata da public/assets/img dell'applicazione)
 * ogni creazione, spostamento ed eliminazione di file della Libreria
 * media. Vedi config/media.php per il significato di 'public_root'.
 *
 * Disattivata di default (nessuna operazione) quando 'public_root' non e'
 * configurato: nessun comportamento esistente cambia finche' non viene
 * impostato esplicitamente MEDIA_PUBLIC_ROOT. Si disattiva anche da sola
 * quando 'public_root' risolve, tramite realpath(), alla stessa directory
 * fisica di public/assets/img (es. un symlink impostato a livello di
 * sistema operativo tra le due directory): in quel caso copiare sarebbe
 * scrivere un file su se stesso.
 */
class PublicMediaSyncService
{
    /**
     * Copia un file gia' scritto in public/assets/img anche nella radice
     * pubblica secondaria, preservando le eventuali sottocartelle presenti
     * in $diskName.
     *
     * @throws RuntimeException se la sincronizzazione e' configurata ma la
     *                          copia non puo' essere completata e verificata.
     */
    public function create(string $absoluteSourcePath, string $diskName): void
    {
        $target = $this->resolveTarget($diskName);

        if ($target === null) {
            return;
        }

        if (! is_file($absoluteSourcePath)) {
            throw new RuntimeException('File sorgente non trovato per la sincronizzazione pubblica: '.$absoluteSourcePath);
        }

        clearstatcache(true, $target);

        if (is_file($target) && ! $this->sameContent($target, $absoluteSourcePath)) {
            throw new RuntimeException('Un file diverso esiste gia\' nella directory pubblica per: '.$diskName);
        }

        $this->ensureDirectory(dirname($target));

        if (! @copy($absoluteSourcePath, $target)) {
            throw new RuntimeException('Copia verso la directory pubblica fallita per: '.$diskName);
        }

        clearstatcache(true, $target);

        if (! is_file($target) || ! $this->sameContent($target, $absoluteSourcePath)) {
            @unlink($target);

            throw new RuntimeException('Verifica della copia pubblica fallita per: '.$diskName);
        }
    }

    /**
     * Ripulisce, con un tentativo best-effort, il file caricato nella
     * document root primaria dopo che create() e' fallita: create() viene
     * sempre chiamata prima di registrare il Media corrispondente, quindi
     * nessun record puntera' mai a questo file. Lasciarlo sul disco lo
     * renderebbe un orfano non tracciato da nulla. Un fallimento qui viene
     * solo loggato: non deve mai mascherare l'errore originale gia'
     * riportato al chiamante.
     */
    public function cleanupAfterFailedCreate(string $fullPath): void
    {
        if (! file_exists($fullPath)) {
            return;
        }

        if (! @unlink($fullPath)) {
            Log::warning('PublicMediaSyncService: pulizia del file locale non riuscita dopo un fallimento di sincronizzazione.', [
                'operation' => 'cleanup_after_failed_create',
                'path' => $fullPath,
            ]);
        }
    }

    /**
     * Rimuove, se presente, la copia nella radice pubblica secondaria.
     * Nessun errore se il file non esiste li' (idempotente): puo' non
     * essere mai stato sincronizzato, ad esempio perche' caricato prima
     * dell'attivazione di questa funzionalita'. Un fallimento reale della
     * rimozione (es. permessi) solleva invece un'eccezione: il chiamante
     * deve poterlo distinguere dal caso "non c'era nulla da fare".
     *
     * @throws RuntimeException se il file esiste ma non puo' essere rimosso.
     */
    public function delete(string $diskName): void
    {
        $target = $this->resolveTarget($diskName);

        if ($target === null || ! is_file($target)) {
            return;
        }

        if (! @unlink($target)) {
            throw new RuntimeException('Impossibile eliminare il file dalla directory pubblica per: '.$diskName);
        }
    }

    /**
     * Sposta un file nella radice pubblica secondaria dal vecchio al nuovo
     * disk_name. Implementato come create-poi-delete (non rename diretto):
     * il file sorgente autorevole per il nuovo percorso e' sempre quello
     * gia' scritto in public/assets/img dal chiamante (dopo il proprio
     * rename), quindi questa chiamata e' anche auto-risanante per un file
     * che non era mai stato sincronizzato in precedenza.
     *
     * @throws RuntimeException se la sincronizzazione e' configurata ma fallisce.
     */
    public function move(string $newAbsoluteSourcePath, string $oldDiskName, string $newDiskName): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->create($newAbsoluteSourcePath, $newDiskName);
        $this->delete($oldDiskName);
    }

    public function isEnabled(): bool
    {
        return $this->publicRoot() !== null;
    }

    /**
     * Percorso assoluto di destinazione nella radice pubblica secondaria,
     * oppure null se la sincronizzazione e' disattivata (nessuna radice
     * configurata) o superflua (radice pubblica e radice applicativa sono
     * fisicamente la stessa directory).
     */
    private function resolveTarget(string $diskName): ?string
    {
        $root = $this->publicRoot();

        if ($root === null) {
            return null;
        }

        $appRoot = realpath(public_path('assets/img'));
        $publicRootReal = realpath($root);

        if ($appRoot !== false && $publicRootReal !== false && $appRoot === $publicRootReal) {
            return null;
        }

        $this->assertSafeDiskName($diskName);

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $diskName);
    }

    private function publicRoot(): ?string
    {
        $root = config('media.public_root');

        return filled($root) ? $root : null;
    }

    private function assertSafeDiskName(string $diskName): void
    {
        if (
            $diskName === ''
            || str_contains($diskName, "\0")
            || str_contains($diskName, '..')
            || str_contains($diskName, '\\')
            || str_starts_with($diskName, '/')
        ) {
            throw new RuntimeException('Nome file non valido per la sincronizzazione pubblica: '.$diskName);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Impossibile creare la directory pubblica: '.$directory);
        }
    }

    /**
     * Confronta due file per contenuto reale (digest SHA-256), non solo per
     * dimensione: due file diversi possono coincidere per byte totali, il
     * che renderebbe filesize() da solo inadatto sia a rilevare una vera
     * collisione sia a verificare che la copia sia stata effettivamente
     * replicata byte per byte.
     */
    private function sameContent(string $pathA, string $pathB): bool
    {
        $hashA = @hash_file('sha256', $pathA);
        $hashB = @hash_file('sha256', $pathB);

        return $hashA !== false && $hashB !== false && $hashA === $hashB;
    }
}
