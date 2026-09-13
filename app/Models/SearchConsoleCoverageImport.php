<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchConsoleCoverageImport extends Model
{
    use HasFactory;

    protected $fillable = ['property', 'observed_at', 'source', 'source_filename', 'import_batch', 'imported_at'];

    protected $casts = ['observed_at' => 'date', 'imported_at' => 'datetime'];

    public function issues(): HasMany
    {
        return $this->hasMany(SearchConsoleCoverageIssue::class, 'coverage_import_id');
    }
}
