<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MediaFolder extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'path',
        'parent_id',
        'created_by',
        'is_protected',
        'sort_order',
        'description',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->ordered();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function depth(): int
    {
        return substr_count($this->path, '/') + 1;
    }

    /**
     * @return Collection<int, self>
     */
    public function parentChain(?Collection $folders = null): Collection
    {
        $folders ??= self::query()->get()->keyBy('id');
        $chain = collect();
        $parentId = $this->parent_id;

        while ($parentId !== null && ($parent = $folders->get($parentId))) {
            $chain->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $chain;
    }

    public function containsMediaDirectly(): bool
    {
        $prefix = self::escapeLike($this->path.'/');

        return Media::query()
            ->whereRaw("disk_name LIKE ? ESCAPE '!'", [$prefix.'%'])
            ->whereRaw("disk_name NOT LIKE ? ESCAPE '!'", [$prefix.'%/%'])
            ->exists();
    }

    public function hasSubfolders(): bool
    {
        return $this->children()->exists();
    }

    public function hierarchicalLabel(?Collection $folders = null): string
    {
        return $this->parentChain($folders)
            ->push($this)
            ->pluck('name')
            ->implode(' / ');
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
