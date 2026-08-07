<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationTemplateVersion extends Model
{
    use HasFactory;

    protected $table = 'comm_template_versions';

    public $timestamps = false;

    protected $fillable = [
        'template_id', 'version_number', 'subject', 'preheader', 'content', 'metadata',
        'created_by', 'created_at',
    ];

    protected $casts = [
        'content' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
