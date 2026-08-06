<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use League\CommonMark\CommonMarkConverter;

class ProjectDocument extends Model
{
    use HasFactory;

    public const TYPE_BRIEF = 'brief';

    public const TYPE_NOTE = 'note';

    public const TYPE_GUIDELINE = 'guideline';

    public const TYPE_ASSET = 'asset';

    public const TYPE_OTHER = 'other';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'project_id', 'title', 'content', 'media_id', 'type',
        'version', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Markdown -> HTML per la sola anteprima: input HTML grezzo eventuale
     * viene neutralizzato (strip) e nessun link "javascript:"/protocollo
     * non sicuro viene reso cliccabile.
     */
    public function renderedContent(): string
    {
        if (blank($this->content)) {
            return '';
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($this->content)->getContent();
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_BRIEF => 'Brief',
            self::TYPE_NOTE => 'Nota',
            self::TYPE_GUIDELINE => 'Linea guida',
            self::TYPE_ASSET => 'Asset',
            self::TYPE_OTHER => 'Altro',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Bozza',
            self::STATUS_APPROVED => 'Approvato',
            self::STATUS_ARCHIVED => 'Archiviato',
        ];
    }
}
