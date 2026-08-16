<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivityLog extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_SYSTEM = 'system';

    /** Collegamento automatico origine sincronizzazione calendario editoriale (EditorialCalendarLinkingService). */
    public const SOURCE_EDITORIAL_SYNC = 'editorial_sync';

    /**
     * Origine GitHub (ProjectTaskGithubSyncService — mergiata una pull
     * request). Distinta da SOURCE_SYSTEM (FASE 8, missione Dashboard
     * Automation V2): "il sistema ha dedotto qualcosa dallo stato di un
     * articolo" e "GitHub ha segnalato che una PR è stata mergiata" sono
     * fatti di natura diversa, anche se entrambi non richiedono un click
     * umano — mai la stessa etichetta generica per provenienze diverse.
     */
    public const SOURCE_GITHUB = 'github';

    /**
     * Valori di new_value per gli eventi subject_type 'project_article'
     * (collegamento/scollegamento articolo↔progetto) — un marcatore
     * strutturato, non il testo libero di 'action', così
     * EditorialCalendarLinkingService può riconoscere in modo affidabile lo
     * stato più recente (es. "questo articolo è stato scollegato a mano,
     * non ricollegarlo automaticamente") senza dipendere da una stringa
     * che potrebbe cambiare formulazione in futuro.
     */
    public const PROJECT_ARTICLE_LINKED = 'linked';

    public const PROJECT_ARTICLE_UNLINKED = 'unlinked';

    public $timestamps = false;

    protected $fillable = [
        'project_id', 'subject_type', 'subject_id', 'subject_title',
        'action', 'old_value', 'new_value', 'reason', 'source',
        'user_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * $userId è sempre esplicito, senza default "furbo": un default che
     * provasse a indovinare tra origine manuale e automatica nasconderebbe
     * proprio la distinzione che questa colonna esiste per registrare.
     */
    public static function record(
        Project $project,
        string $subjectType,
        int $subjectId,
        ?string $subjectTitle,
        string $action,
        ?int $userId,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $reason = null,
        string $source = self::SOURCE_MANUAL,
    ): self {
        return static::create([
            'project_id' => $project->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_title' => $subjectTitle,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'reason' => $reason,
            'source' => $source,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
