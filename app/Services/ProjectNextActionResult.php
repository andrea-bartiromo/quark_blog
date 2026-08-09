<?php

namespace App\Services;

use App\Models\ProjectTask;

/**
 * Esito immutabile di ProjectNextActionResolver::resolve() — dati
 * strutturati, mai una stringa già formattata per l'utente: la
 * formulazione del messaggio resta una scelta successiva (view/controller),
 * non di questo servizio.
 */
final class ProjectNextActionResult
{
    private function __construct(
        public readonly ?ProjectTask $task,
        public readonly string $kind,
        public readonly int $pendingDependencyCount = 0,
    ) {}

    public static function forTask(ProjectTask $task, string $kind): self
    {
        return new self($task, $kind);
    }

    public static function pendingDependencies(int $count): self
    {
        return new self(null, ProjectNextActionResolver::KIND_PENDING_DEPENDENCIES, $count);
    }

    public static function none(): self
    {
        return new self(null, ProjectNextActionResolver::KIND_NONE);
    }
}
