# Turing missing sections investigation

## Scope

This investigation documents why the public `/turing` page can render without the editorial blocks and timeline sections.

No application behavior is changed in this branch. The analysis covers controller data flow, Blade conditions, database population, seed behavior, and CSS visibility rules.

## Stato controller

File: `app/Http/Controllers/TuringPageController.php`

The public page reads a `SpecialPage` record directly:

```php
$page = SpecialPage::where('slug', 'turing')->first();
$content = ($page && $page->is_active) ? ($page->content ?? []) : [];
```

The relevant public view variables are then built only from keys inside `content`:

```php
'editorialBlocks' => collect($content['editorial_blocks'] ?? []),
'timeline' => collect($content['timeline'] ?? []),
```

The controller has asset fallbacks for images and backgrounds:

```php
sectionImageFallbacks()
sectionBackgroundFallbacks()
```

However, these fallbacks only help once an editorial block exists and has a matching `key`. They do not create missing editorial blocks.

There is no controller fallback that creates default `editorial_blocks` or `timeline` entries when the database content is empty or incomplete.

## Stato Blade

Main view: `resources/views/turing/index.blade.php`

The page always includes the partials:

```blade
@include('turing.partials.editorial-blocks')
@include('turing.partials.timeline')
```

### Editorial blocks

File: `resources/views/turing/partials/editorial-blocks.blade.php`

The partial renders only enabled blocks:

```blade
@foreach($editorialBlocks->where('enabled', true) as $block)
```

If `editorialBlocks` is empty, nothing is rendered.

If the array exists but each block lacks `enabled: true`, nothing is rendered.

There is no `@forelse`, `@empty`, or Blade fallback for missing blocks.

### Timeline

File: `resources/views/turing/partials/timeline.blade.php`

The whole timeline section is conditional:

```blade
@if($timeline->isNotEmpty())
```

If `timeline` is empty or missing from `content`, the entire section is skipped.

There is no Blade fallback for missing timeline events.

## Stato database

Model: `app/Models/SpecialPage.php`

Relevant behavior:

```php
protected $casts = [
    'content' => 'array',
    'is_active' => 'boolean',
];
```

The public controller does not call `SpecialPage::bySlug()`. It reads the model directly and uses an empty array whenever the record is missing, inactive, or has empty content.

For an actual database, the fields to inspect are:

```bash
php artisan tinker --execute='dump(App\Models\SpecialPage::where("slug", "turing")->first()?->only(["slug", "title", "description", "is_active", "content"]));'
```

Expected interpretation:

- No record with `slug = turing`: public `/turing` uses default hero/intro/legacy/final text, but `editorialBlocks` and `timeline` are empty.
- Record exists with `content = []`: same behavior.
- Record exists with `content.hero`, `content.intro`, `content.why`, or `content.final` only: those areas render from DB/defaults, but editorial blocks and timeline remain absent.
- Record exists with non-empty `content.editorial_blocks` and at least one block where `enabled` is true: editorial blocks render.
- Record exists with non-empty `content.timeline`: timeline renders.

## Stato seeder

File checked: `database/seeders/DatabaseSeeder.php`

The main seeder creates users, categories, articles, and comments. It does not import `SpecialPage`, does not call `SpecialPage::create()`, and does not create a `slug = turing` record.

Therefore, after:

```bash
php artisan migrate:fresh --seed
```

the seeded database should not contain a populated Turing special page from `DatabaseSeeder`.

The public `/turing` page can still respond because the public controller has default values for hero, intro, legacy, final, terminal lines, and image fallbacks. The missing areas are the ones that depend on arrays without content fallbacks: `editorial_blocks` and `timeline`.

## Stato admin

File: `app/Http/Controllers/Admin/TuringController.php`

The admin editor creates the Turing page lazily:

```php
SpecialPage::firstOrCreate(
    ['slug' => self::SLUG],
    [
        'title' => 'Alan Turing',
        'description' => 'Speciale editoriale dedicato ad Alan Turing, Enigma e intelligenza artificiale.',
        'is_active' => true,
        'content' => [],
    ]
);
```

This means opening the admin Turing page can create an active `SpecialPage` record with empty content.

The admin Blade view (`resources/views/admin/turing-lite.blade.php`) does define local fallback arrays for empty `cards` and `editorialBlocks`, but those fallback arrays exist only inside the admin form rendering. They are not automatically persisted to the database unless submitted and saved.

No equivalent public fallback exists for `timeline` or `editorial_blocks`.

## Stato CSS

File: `public/css/turing.css`

The CSS defines layout and styling for:

```css
.turing-editorial-link
.turing-timeline
.turing-timeline__item
```

No rule was found that intentionally hides editorial blocks or the timeline via:

- `display: none`
- `visibility: hidden`
- `opacity: 0`
- `height: 0`
- problematic `z-index`

The missing sections are therefore data/rendering conditions, not CSS visibility issues.

## Causa reale delle sezioni mancanti

The missing sections are caused by missing structured content in `SpecialPage.content`, combined with public Blade conditions that silently render nothing for empty arrays.

Specifically:

1. `DatabaseSeeder` does not create a populated `SpecialPage` record for `slug = turing`.
2. `Admin\TuringController::firstOrCreateTuringPage()` can create the record with `content = []`.
3. `TuringPageController` passes `editorialBlocks` and `timeline` as empty collections when the content keys are absent.
4. `editorial-blocks.blade.php` renders only `$editorialBlocks->where('enabled', true)`.
5. `timeline.blade.php` renders only when `$timeline->isNotEmpty()`.
6. Asset fallbacks introduced for Turing images do not create missing content records or missing array items.

So the page is not broken by CSS or missing image assets. It is rendering exactly what the current data contract tells it to render: no editorial blocks and no timeline when those arrays are absent.

## Miglior soluzione tecnica

The safest next implementation PR should choose one authoritative source for baseline Turing content.

Recommended option: add a focused public fallback/default content layer in `TuringPageController` or a dedicated private method/service, then merge database content over it.

Why this is likely the best fit:

- It keeps clean installations visually complete.
- It avoids relying on admin form fallbacks that are not persisted.
- It does not require a database migration or schema change.
- It keeps existing CMS overrides possible: saved `SpecialPage.content` can still override defaults.
- It matches the existing approach already used for hero, intro, terminal lines, and image fallbacks.

Alternative: seed a full `SpecialPage` record. This makes `migrate:fresh --seed` complete, but does not protect installations where the admin-created record already exists with partial or empty content.

Alternative: add Blade fallbacks. This is less ideal because content defaults would be split across views and controller, making future maintenance harder.

## PR consigliata successiva

Suggested PR: `fix: add Turing public content fallbacks`

Scope:

- Add public fallback data for `editorial_blocks` and `timeline` in one authoritative PHP location.
- Preserve CMS overrides from `SpecialPage.content`.
- Add a feature test proving that `/turing` renders editorial blocks and timeline after `migrate:fresh --seed` or with no `SpecialPage` record.
- Add a second test proving an existing CMS-provided block/timeline still renders.
- Do not change routes, database schema, admin UI, or editorial assets.
