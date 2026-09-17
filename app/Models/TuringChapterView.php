<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 68 (programma "100 cantieri Kairus").
 *
 * Evento append-only: una vista reale dell'hub o di un capitolo dello
 * Speciale Turing, registrata SOLO quando `config('turing.chapters_public')`
 * è vero (mai per la landing "In arrivo" — non è la stessa esperienza) —
 * mai modificato dopo la creazione, mai un identificativo di
 * visitatore/sessione/utente/IP persistito qui. Stesso principio
 * privacy-first già in uso per TrustKnowledgeStatementPreviewView
 * (Cantiere 43) e ArticleContinuationEvent.
 */
class TuringChapterView extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'turing_chapter_views';

    protected $fillable = [
        'chapter',
    ];
}
