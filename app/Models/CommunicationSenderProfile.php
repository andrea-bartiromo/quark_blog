<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CommunicationSenderProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comm_sender_profiles';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    // Unico provider disponibile in questo blocco: il transport SMTP già
    // configurato in .env (Comunicazione B4A — nessun ESP/SDK introdotto).
    public const PROVIDER_SMTP = 'smtp';

    protected $fillable = [
        'uuid', 'name', 'from_name', 'from_email', 'reply_to', 'provider', 'status', 'is_default',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommunicationSenderProfile $profile) {
            if (blank($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (CommunicationSenderProfile $profile) {
            // Rete di sicurezza a livello di modello, stesso principio di
            // Project::is_default_editorial: un profilo archiviato non deve
            // mai poter restare marcato come predefinito, anche per un uso
            // diretto di Eloquent che bypassi il FormRequest.
            if ($profile->is_default && $profile->status === self::STATUS_ARCHIVED) {
                $profile->is_default = false;
            }

            // Al più un profilo alla volta è il predefinito. Spegnimento
            // degli altri QUI, in saving() — prima che la riga corrente
            // venga scritta — non in saved(): altrimenti esisterebbe una
            // finestra, per quanto breve, in cui due righe risultano
            // entrambe predefinite. Query di massa (non Eloquent::save())
            // per non far scattare di nuovo questi stessi eventi sugli
            // altri profili. $profile->id è null per una riga non ancora
            // creata: sentinella 0 (mai un id reale) così la query spegne
            // comunque tutti gli eventuali predefiniti esistenti.
            if ($profile->is_default && $profile->isDirty('is_default')) {
                static::query()
                    ->where('id', '!=', $profile->id ?? 0)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    // ── Relazioni ─────────────────────────────────────────────

    public function campaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class, 'sender_profile_id');
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

    public static function providerOptions(): array
    {
        return [
            self::PROVIDER_SMTP => 'SMTP (configurazione applicativa esistente)',
        ];
    }
}
