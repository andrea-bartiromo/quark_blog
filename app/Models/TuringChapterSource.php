<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cantiere 61 (programma "100 cantieri Kairus"): registro gestibile
 * dall'editor delle fonti/citazioni per ciascun capitolo dello Speciale
 * Turing. Nessuna riga viene mai creata automaticamente da questo
 * programma — la tabella parte vuota, un editor la popola dall'admin
 * (`Admin\TuringChapterSourceController`) con le fonti reali che
 * decide di citare, ad esempio quelle già indicate come mancanti in
 * docs/00_Governance/Architettura_Editoriale_v1.0.docx §7 (es. il saggio
 * "On Computable Numbers" del 1936 per Computation, le scuse pubbliche
 * del 2009 per Legacy) — questo codice non inventa né presume alcuna
 * fonte specifica.
 */
class TuringChapterSource extends Model
{
    protected $fillable = [
        'chapter',
        'label',
        'url',
        'year',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
