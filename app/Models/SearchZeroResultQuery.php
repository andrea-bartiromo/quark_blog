<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchZeroResultQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'normalized_query',
        'hit_count',
    ];

    protected $casts = [
        'hit_count' => 'integer',
    ];
}
