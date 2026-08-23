<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConceptAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'concept_id',
        'alias',
    ];

    public function concept()
    {
        return $this->belongsTo(Concept::class);
    }
}
