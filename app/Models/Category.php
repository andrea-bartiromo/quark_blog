<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Category extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const EDITORIAL_TIMEZONE = 'Europe/Rome';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'color',
        'sort_order',
        'is_active',
        'status',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Invariante di stato (stesso principio di Article::booted()): una
     * bozza non ha mai una data di pubblicazione residua da un
     * passaggio di stato precedente; una categoria pubblicata senza
     * data esplicita (il caso più comune, pubblicazione immediata da
     * admin) prende `now()` invece di restare null — altrimenti
     * status=published con published_at=null supererebbe comunque
     * scopePubliclyVisible() (che per lo stato "published" non
     * controlla affatto published_at, vedi il docblock dello scope),
     * ma lascerebbe un dato editoriale mancante e fuorviante nell'admin.
     */
    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if ($category->status === self::STATUS_DRAFT) {
                $category->published_at = null;
            } elseif ($category->status === self::STATUS_PUBLISHED && $category->published_at === null) {
                $category->published_at = now();
            }
        });
    }

    /** Categoria principale: relazione storica basata sullo slug. */
    public function articles()
    {
        return $this->hasMany(Article::class, 'category', 'slug');
    }

    /** Articoli che usano questa categoria come associazione secondaria. */
    public function secondaryArticles()
    {
        return $this->belongsToMany(Article::class, 'article_category')
            ->withTimestamps();
    }

    public function publishedArticles()
    {
        return $this->articles()->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Unica source of truth per "questa categoria è raggiungibile
     * pubblicamente ADESSO" — ogni superficie pubblica (route categoria,
     * header, footer, sidebar, home, ricerca, sitemap, JSON-LD,
     * breadcrumb) deve riusare questo scope o isPubliclyVisible(), mai
     * replicare la condizione. Stessa policy a due livelli già in uso da
     * ContentCluster::scopePubliclyVisible():
     *
     *   is_active=false                                    → mai pubblica;
     *   is_active=true, status=draft                        → mai pubblica;
     *   is_active=true, status=published                    → pubblica
     *     (published_at non condiziona questo ramo: una categoria
     *     pubblicata è pubblica subito, published_at è solo il dato
     *     editoriale "da quando", mai un secondo gate);
     *   is_active=true, status=scheduled, published_at<=now() → pubblica;
     *   is_active=true, status=scheduled, published_at>now()  → non ancora
     *     pubblica (programmata).
     *
     * Nessun job schedulato promuove mai scheduled→published: la
     * visibilità è calcolata dinamicamente ad ogni query, esattamente
     * come ContentCluster::publish_at — evita una dipendenza da cron per
     * l'esatto istante di apertura (vedi i test time-travel).
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) {
                $query->where('status', self::STATUS_PUBLISHED)
                    ->orWhere(function (Builder $query) {
                        $query->where('status', self::STATUS_SCHEDULED)
                            ->where('published_at', '<=', now());
                    });
            });
    }

    /**
     * Equivalente di scopePubliclyVisible() per un'istanza già caricata
     * in memoria — stessa policy, mai duplicata altrove. Vedi il
     * docblock dello scope per il contratto completo.
     */
    public function isPubliclyVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->status === self::STATUS_PUBLISHED) {
            return true;
        }

        if ($this->status === self::STATUS_SCHEDULED) {
            return $this->published_at !== null && ! $this->published_at->isFuture();
        }

        return false;
    }

    /**
     * Etichetta sintetica per l'anteprima di visibilità nell'admin —
     * riflette SOLO isPubliclyVisible()/status, mai altro segnale.
     */
    public function effectiveVisibilityLabel(): string
    {
        if (! $this->is_active) {
            return 'Disattivata';
        }

        return match (true) {
            $this->status === self::STATUS_DRAFT => 'Bozza',
            $this->status === self::STATUS_SCHEDULED && ! $this->isPubliclyVisible() => 'Programmata',
            default => 'Pubblica',
        };
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Bozza',
            self::STATUS_SCHEDULED => 'Programmato',
            self::STATUS_PUBLISHED => 'Pubblicato',
        ];
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('assets/img/categories/'.$this->image) : null;
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;

        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    /**
     * Normalizza sempre a UTC prima della persistenza, indipendentemente
     * dal fuso orario del valore assegnato — stesso motivo e stesso
     * pattern di ContentCluster::setPublishAtAttribute(): senza questo
     * mutator il cast 'datetime' salverebbe il wall-clock del fuso
     * corrente così com'è, non convertito, producendo un timestamp
     * silenziosamente sbagliato di alcune ore.
     */
    public function setPublishedAtAttribute(mixed $value): void
    {
        $this->attributes['published_at'] = $value === null
            ? null
            : $this->fromDateTime(Carbon::parse($value)->utc());
    }

    /**
     * published_at (memorizzato in UTC) convertito nel fuso orario della
     * redazione, per la visualizzazione nel form admin — stesso pattern
     * di Article::publishedAtForEditors() / ContentCluster::publishAtForEditors().
     */
    public function publishedAtForEditors(): ?Carbon
    {
        return $this->published_at?->clone()->timezone(self::EDITORIAL_TIMEZONE);
    }

    /**
     * Converte una coppia data+ora inserita dall'editor nel fuso orario
     * Europe/Rome in un Carbon UTC pronto per essere assegnato a
     * published_at — stesso pattern di Article::scheduledAtFromEditorialInput().
     */
    public static function scheduledAtFromEditorialInput(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', "{$date} {$time}", self::EDITORIAL_TIMEZONE)->utc();
    }

    /**
     * Variante di options() per le superfici GENUINAMENTE pubbliche
     * (navigazione, ricerca, sitemap) — usa publiclyVisible(), non solo
     * active(): una categoria bozza o programmata nel futuro resta
     * selezionabile nei form editoriali tramite options() (il
     * requisito esplicito è che resti assegnabile in anticipo a un
     * articolo), ma non deve mai comparire qui.
     *
     * Il fallback a config('laboratorio.categories') copre SOLO gli slug
     * senza alcuna riga DB corrispondente (tabella non ancora esistente
     * durante deploy/migrazioni, o uno slug legacy mai migrato) — mai uno
     * slug la cui riga DB esiste proprio perché è stata resa bozza,
     * programmata nel futuro o disattivata. Senza questa distinzione, una
     * categoria come "energia" (presente anche in config come default
     * legacy) sarebbe tornata pubblica tramite il fallback nello stesso
     * istante in cui viene nascosta in DB — riaprendo esattamente
     * l'incoerenza che publiclyVisible() esiste per chiudere.
     */
    public static function publicOptions(): array
    {
        $categories = static::allOrderedWithVisibilityColumns();

        if ($categories->isEmpty()) {
            return config('laboratorio.categories', []);
        }

        $visible = $categories
            ->filter(fn (Category $category) => $category->isPubliclyVisible())
            ->pluck('name', 'slug')
            ->toArray();

        $configOnlySlugs = array_diff_key(
            config('laboratorio.categories', []),
            array_flip($categories->pluck('slug')->all())
        );

        return $visible + $configOnlySlugs;
    }

    public static function options(bool $activeOnly = true): array
    {
        try {
            $query = static::query()->ordered();

            if ($activeOnly) {
                $query->active();
            }

            $categories = $query->pluck('name', 'slug')->toArray();

            if ($categories !== []) {
                return $categories;
            }
        } catch (\Throwable $e) {
            // Durante deploy/migrazioni la tabella potrebbe non esistere ancora.
        }

        return config('laboratorio.categories', []);
    }

    /**
     * Come options(false), ma restituisce i model completi (con le
     * colonne necessarie a isPubliclyVisible()) invece della sola mappa
     * slug=>name — per un consumer che deve derivare SIA l'etichetta SIA
     * la visibilità di una categoria da UNA query sola. Nato per
     * ArticleController::show(): prima di questo metodo, calcolare
     * 'categoryPubliclyVisible' lì richiedeva una seconda query dedicata
     * oltre a options(false), regredendo il budget query della pagina
     * articolo (vedi PublicPageQueryBudgetTest). Riusato anche da
     * publicOptions() e ArticleDiscoveryAuditService per lo stesso motivo:
     * distinguere "nessuna riga DB" (fallback a config legittimo) da
     * "riga DB nascosta di proposito" (fallback vietato, vedi i rispettivi
     * docblock). Stesso fallback a config() di options()/publicOptions()
     * quando la tabella non esiste ancora.
     *
     * @return Collection<int, Category>
     */
    public static function allOrderedWithVisibilityColumns(): Collection
    {
        try {
            return static::query()->ordered()->get(['id', 'name', 'slug', 'is_active', 'status', 'published_at']);
        } catch (\Throwable $e) {
            // Durante deploy/migrazioni la tabella potrebbe non esistere ancora.
            return collect();
        }
    }
}
