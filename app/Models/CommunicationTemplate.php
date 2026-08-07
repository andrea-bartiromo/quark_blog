<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunicationTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comm_templates';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'uuid', 'name', 'description', 'type', 'status', 'active_version_id',
        'created_by', 'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommunicationTemplate $template) {
            if (blank($template->uuid)) {
                $template->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function versions(): HasMany
    {
        return $this->hasMany(CommunicationTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(CommunicationTemplateVersion::class, 'active_version_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scope ─────────────────────────────────────────────────

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    // ── Etichette ─────────────────────────────────────────────

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Attivo',
            self::STATUS_ARCHIVED => 'Archiviato',
        ];
    }
}
