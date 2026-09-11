<?php

namespace App\Services\Deploy;

use RuntimeException;

/**
 * Prompt 7 (programma 100-prompt Kairus, Fase P0 — affidabilità del
 * rilascio). REVISION e DEPLOY_INFO (scritti da deploy.sh) vivono dentro
 * la directory di release stessa e vengono scartati al rilascio
 * successivo (schema a directory separate + switch di symlink): nessuna
 * storia sopravvive tra un deploy e l'altro, e il "Principio di stato"
 * della roadmap operativa (costruito ≠ CI green ≠ merged ≠ deployed ≠
 * verified ≠ measured) viene oggi tracciato a mano in una tabella
 * Markdown, senza alcuna fonte automatica.
 *
 * Log append-only (JSON Lines, un evento per riga) a un percorso
 * configurato FUORI dalla directory di release
 * (config('deploy.release_registry_path') / DEPLOY_RELEASE_REGISTRY_PATH).
 * Disattivato di default (isEnabled() false) quando il percorso non è
 * configurato — stesso contratto già in uso da PublicAssetDriftDetector
 * per served_public_root: nessun comportamento esistente cambia finché
 * non viene impostato esplicitamente.
 *
 * Mai un gate di rilascio: un fallimento di scrittura qui non deve mai
 * bloccare un deploy altrimenti verificato — vedi
 * App\Console\Commands\ReleaseRegistryRecord, che cattura ogni eccezione
 * di questo servizio e non fallisce mai chiuso.
 */
class ReleaseRegistry
{
    public const STAGE_DEPLOYED = 'deployed';

    public const STAGE_VERIFIED = 'verified';

    public const STAGE_MEASURED = 'measured';

    public function isEnabled(): bool
    {
        return filled($this->path());
    }

    /**
     * Aggiunge un evento in coda al registro. No-op silenzioso (ritorna
     * false) quando il registro è disattivato — mai un errore, per non
     * costringere ogni chiamante a distinguere "disattivato" da
     * "fallito". Quando invece è configurato ma la scrittura fallisce
     * davvero (percorso non scrivibile, directory mancante), lancia
     * un'eccezione esplicita: è compito del chiamante decidere se quel
     * fallimento debba bloccare qualcosa — questo servizio non lo
     * nasconde né lo silenzia da solo.
     */
    public function record(string $revision, string $stage, ?string $note = null): bool
    {
        $path = $this->path();

        if ($path === null) {
            return false;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            throw new RuntimeException("Release registry directory does not exist: {$directory}");
        }

        $line = json_encode([
            'revision' => $revision,
            'stage' => $stage,
            'recorded_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'note' => $note,
        ], JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            throw new RuntimeException('Failed to encode release registry entry as JSON.');
        }

        $written = @file_put_contents($path, $line.PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            throw new RuntimeException("Failed to append to release registry at: {$path}");
        }

        return true;
    }

    /**
     * Ogni riga del log, nell'ordine in cui è stata scritta (più vecchia
     * per prima). Righe malformate (un file toccato a mano, un evento
     * scritto a metà per un crash a disco pieno) vengono scartate in
     * silenzio invece di far fallire l'intera lettura — un registro
     * best-effort non deve mai impedire di leggere il resto della sua
     * stessa storia per colpa di una singola riga corrotta.
     *
     * @return list<array{revision:string,stage:string,recorded_at_utc:string,note:?string}>
     */
    public function entries(): array
    {
        $path = $this->path();

        if ($path === null || ! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $entries = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (
                ! is_array($decoded)
                || ! isset($decoded['revision'], $decoded['stage'], $decoded['recorded_at_utc'])
                || ! is_string($decoded['revision'])
                || ! is_string($decoded['stage'])
                || ! is_string($decoded['recorded_at_utc'])
            ) {
                continue;
            }

            $entries[] = [
                'revision' => $decoded['revision'],
                'stage' => $decoded['stage'],
                'recorded_at_utc' => $decoded['recorded_at_utc'],
                'note' => is_string($decoded['note'] ?? null) ? $decoded['note'] : null,
            ];
        }

        return $entries;
    }

    /**
     * Le stesse voci di entries(), raggruppate per revisione — l'unico
     * timestamp conservato per ciascuna coppia revisione/stage è il
     * PRIMO registrato (un secondo evento "deployed" per la stessa
     * revisione, es. un rollback e ridistribuzione, non sovrascrive
     * quando è avvenuto per la prima volta). Ordine di prima apparizione
     * della revisione, non alfabetico né temporale globale.
     *
     * @return list<array{revision:string,stages:array<string,string>}>
     */
    public function history(): array
    {
        $byRevision = [];
        $order = [];

        foreach ($this->entries() as $entry) {
            $revision = $entry['revision'];

            if (! isset($byRevision[$revision])) {
                $byRevision[$revision] = [];
                $order[] = $revision;
            }

            if (! isset($byRevision[$revision][$entry['stage']])) {
                $byRevision[$revision][$entry['stage']] = $entry['recorded_at_utc'];
            }
        }

        return array_map(
            fn (string $revision) => ['revision' => $revision, 'stages' => $byRevision[$revision]],
            $order
        );
    }

    private function path(): ?string
    {
        $path = config('deploy.release_registry_path');

        return filled($path) ? $path : null;
    }
}
