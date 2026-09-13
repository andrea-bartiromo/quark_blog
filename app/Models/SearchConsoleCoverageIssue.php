<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchConsoleCoverageIssue extends Model
{
    use HasFactory;

    public const CLASS_EXPECTED = 'expected';
    public const CLASS_REVIEW = 'review';
    public const CLASS_FIX = 'fix';
    public const CLASS_INTENTIONAL = 'intentional_exclusion';
    public const CLASS_EDITORIAL = 'editorial_review';

    protected $fillable = ['coverage_import_id', 'reason', 'source', 'validation', 'page_count', 'page_url', 'audit', 'classification', 'recommendation'];

    protected $casts = ['page_count' => 'integer', 'audit' => 'array'];

    public function coverageImport(): BelongsTo
    {
        return $this->belongsTo(SearchConsoleCoverageImport::class, 'coverage_import_id');
    }

    public static function classificationLabels(): array
    {
        return [
            self::CLASS_EXPECTED => 'Atteso',
            self::CLASS_REVIEW => 'Da verificare',
            self::CLASS_FIX => 'Da correggere',
            self::CLASS_INTENTIONAL => 'Escluso intenzionalmente',
            self::CLASS_EDITORIAL => 'Pronto per revisione editoriale',
        ];
    }
}
