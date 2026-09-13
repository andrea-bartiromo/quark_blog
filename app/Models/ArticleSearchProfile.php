<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cantiere 2 (programma "Kairus Organic Discovery"). Il "profilo di
 * ricerca editoriale" di UN articolo (relazione 1:1, article_id unico) —
 * intento primario, query primaria/secondarie, domande dei lettori, tipo
 * di contenuto, livello del lettore, data e nota dell'ultima revisione
 * editoriale, ambito/limiti delle evidenze. Concetto distinto da
 * Concept/ConceptQuestion (tassonomia editoriale curata e condivisa tra
 * articoli): qui il testo è la formulazione letterale che un lettore
 * potrebbe digitare, propria di UN solo articolo, mai riusata come nodo
 * di tassonomia.
 */
class ArticleSearchProfile extends Model
{
    public const CONTENT_TYPE_EXPLANATION = 'explanation';

    public const CONTENT_TYPE_GUIDE = 'guide';

    public const CONTENT_TYPE_COMPARISON = 'comparison';

    public const CONTENT_TYPE_NEWS = 'news';

    public const CONTENT_TYPE_DEEP_DIVE = 'deep_dive';

    public const READER_LEVEL_BEGINNER = 'beginner';

    public const READER_LEVEL_INTERMEDIATE = 'intermediate';

    public const READER_LEVEL_ADVANCED = 'advanced';

    protected $fillable = [
        'article_id',
        'primary_intent',
        'primary_query',
        'secondary_queries',
        'reader_questions',
        'content_type',
        'reader_level',
        'last_editorial_review_at',
        'freshness_note',
        'evidence_scope',
    ];

    protected function casts(): array
    {
        return [
            'secondary_queries' => 'array',
            'reader_questions' => 'array',
            'last_editorial_review_at' => 'date',
        ];
    }

    /** @return array<string, string> */
    public static function contentTypeOptions(): array
    {
        return [
            self::CONTENT_TYPE_EXPLANATION => 'Spiegazione',
            self::CONTENT_TYPE_GUIDE => 'Guida',
            self::CONTENT_TYPE_COMPARISON => 'Confronto',
            self::CONTENT_TYPE_NEWS => 'Notizia',
            self::CONTENT_TYPE_DEEP_DIVE => 'Approfondimento',
        ];
    }

    /** @return array<string, string> */
    public static function readerLevelOptions(): array
    {
        return [
            self::READER_LEVEL_BEGINNER => 'Base',
            self::READER_LEVEL_INTERMEDIATE => 'Intermedio',
            self::READER_LEVEL_ADVANCED => 'Avanzato',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
