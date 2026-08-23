<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleConcept extends Model
{
    use HasFactory;

    public const RELATION_PRIMARY = 'primary';

    public const RELATION_SUPPORTING = 'supporting';

    protected $fillable = [
        'article_id',
        'concept_id',
        'relation_type',
        'weight',
    ];

    protected $casts = [
        'weight' => 'integer',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }
}
