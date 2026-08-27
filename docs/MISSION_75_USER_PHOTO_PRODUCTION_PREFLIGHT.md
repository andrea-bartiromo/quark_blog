# Mission 75 — User photo production-fact preflight (#249)

## Outcome

`REQUIRES_PRODUCTION_FACT`

Current application code proves that both upload paths store a bare filename in
`users.photo` and write the file below `public/assets/img`. It does not prove
that every production row was created by the current code. Three existing views
still render `storage/{photo}`, while the author and admin profile surfaces use
the media-library root. Changing those call sites without inspecting production
could break a legacy value such as `photos/avatar.jpg`.

This preflight is read-only. It does not update the database, copy files, create
directories, or perform a backfill.

## 1. Database classification (read-only)

Run the following SQL against the production database and retain the aggregate
result plus the bounded exception sample. `TRIM()` makes whitespace-only values
equivalent to null/empty; either slash direction is classified as a path.

```sql
SELECT
    CASE
        WHEN photo IS NULL OR TRIM(photo) = '' THEN 'NULL_OR_EMPTY'
        WHEN LOCATE('/', photo) > 0 OR LOCATE(CHAR(92), photo) > 0 THEN 'PATH_WITH_SLASH'
        ELSE 'BARE_FILENAME'
    END AS photo_shape,
    COUNT(*) AS row_count
FROM users
GROUP BY photo_shape
ORDER BY photo_shape;

SELECT
    id,
    photo,
    CASE
        WHEN photo IS NULL OR TRIM(photo) = '' THEN 'NULL_OR_EMPTY'
        WHEN LOCATE('/', photo) > 0 OR LOCATE(CHAR(92), photo) > 0 THEN 'PATH_WITH_SLASH'
        ELSE 'BARE_FILENAME'
    END AS photo_shape
FROM users
WHERE photo IS NOT NULL
  AND TRIM(photo) <> ''
  AND (LOCATE('/', photo) > 0 OR LOCATE(CHAR(92), photo) > 0)
ORDER BY id
LIMIT 100;
```

Record the exact database timestamp/replica used. Do not paste personal user
data into a public PR; the aggregate counts and redacted path shapes are enough.

## 2. Suggested filesystem checks (read-only)

Run from the deployed application root. These commands only list metadata and
do not follow with a copy, move, delete, link, or permission change.

```bash
find public/assets/img -maxdepth 3 -type f -printf '%P\n' | sort
find storage/app/public -maxdepth 4 -type f -printf '%P\n' | sort
find public/storage -maxdepth 1 -printf '%y %p -> %l\n'
```

For every redacted `PATH_WITH_SLASH` shape, determine which of these candidate
roots contains the file:

- `public/assets/img/{photo}`
- `storage/app/public/{photo}` (normally exposed as `public/storage/{photo}`)
- both roots
- neither root

Do not interpolate an unreviewed database value into a shell command. Reject
absolute paths, `..` segments, NUL/control characters, or URL-like values and
record them as unsafe anomalies requiring manual review.

## 3. Decision tree

### A — all non-empty values are bare filenames

Choose A only when `PATH_WITH_SLASH = 0` and sampled files resolve under
`public/assets/img`. Normalize every real rendering call site to the
media-library/responsive-image contract. No data migration is needed.

### B — legacy paths exist and resolve safely

Choose B when at least one slash-containing value is legitimate and files exist
under more than one root, or when a rendering-only compatibility period is
required. Introduce one path-safe resolver and use it at every call site; do not
duplicate fallback rules in Blade templates. Add traversal, missing-file, and
broken-image fallback tests. Do not mutate production data.

### C — a backfill is justified

Choose C only with explicit separate authorization when legacy files genuinely
exist under `storage/app/public` and converging the data is preferable to a
permanent compatibility resolver. The plan must define backup, collision
handling, file-copy verification, transactional or idempotent database updates,
rollback, and post-backfill counts. Mission 75 does not authorize or execute C.

### Stop conditions

If any value is absolute, traversal-like, URL-like, missing from both roots, or
present in both roots with different contents, stop and request a product/data
decision. Do not guess which file is canonical.

## 4. Evidence needed to unblock Mission 76

- aggregate counts for all three shapes;
- redacted classification of every slash-containing value (bounded query may
  need pagination if it returns 100 rows);
- root-resolution counts: assets only / storage only / both / neither;
- confirmation whether `public/storage` is the expected symlink;
- selected decision A, B, or separately authorized C.

Until those production facts are supplied, Mission 76 must not change user
photo rendering.
