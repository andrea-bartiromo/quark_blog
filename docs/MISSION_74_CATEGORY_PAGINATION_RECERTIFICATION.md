# Mission 74 — Category Pagination V1 recertification

## Outcome

`VERIFIED_ALREADY_PRESENT`, with one missing regression added for draft/review visibility.

The category archive already uses server-side pagination (`paginate(12)`) on
`Article::published()` in `ArticleController::category()`. This mission does
not replace or refactor that implementation.

## Evidence matrix

| Gate | Evidence on `main` | Result |
| --- | --- | --- |
| Zero articles | `CategoryPageEmptyStateTest::test_a_category_with_no_published_articles_shows_an_honest_empty_state` and `CategoryPaginationV1RegressionTest::test_a_category_with_zero_published_articles_shows_no_pagination_controls` | Covered |
| One page | `CategoryPaginationV1RegressionTest::test_page_one_shows_the_first_twelve_articles` | Covered |
| Multiple pages | `test_page_two_shows_the_remaining_article` | Covered |
| Out-of-range page | `test_requesting_a_page_beyond_the_last_returns_an_empty_but_valid_page` | Covered |
| Non-numeric page | `test_a_non_numeric_page_parameter_falls_back_to_page_one_without_erroring` | Covered |
| Scheduled invisible | `test_scheduled_articles_never_count_toward_category_pagination_totals` | Covered |
| Draft/review invisible | `test_draft_and_review_articles_never_count_or_appear_in_category_pages` (added by this mission) | Covered |
| Canonical page 1/page N | `ArchivePaginationCanonicalTest::test_category_page_one_canonical_has_no_page_query_string` and `test_category_page_two_canonicalizes_to_itself_not_to_page_one` | Covered |
| Structured data | `CollectionPageStructuredDataTest` plus the shared page-aware canonical passed by `categoria.blade.php` to `collections.partials.structured-data` | Covered statically and by feature regressions |
| Query budget | `PublicPageQueryBudgetTest::test_category_page_query_count_is_within_the_post_fix_budget` and the page-one/page-two parity regression | Covered |
| Keyboard/focus contract | `components/pagination.blade.php`: semantic `nav`, labelled links, `aria-current`, `aria-disabled`, and `:focus-visible` outline | Verified statically |
| Mobile 390 px contract | Pagination wraps and retains 44 px controls under the `max-width: 640px` breakpoint; the public layout collapses below 900 px | Verified statically; runtime browser certification pending |

## Static data-safety proof

`Article::scopePublished()` requires both `status = published` and
`published_at <= now()`. Therefore scheduled future content, draft content,
and review content cannot enter the paginator query. Primary and secondary
category matches are contained in one query and eager-load only the author,
so pagination does not introduce an article-count-dependent N+1.

## Validation boundary

The repository checkout used for this mission has no PHP runtime and no
installed Composer dependencies, so PHPUnit, Pint, and a served browser run
could not be executed locally. The 390 px visual check and real keyboard tab
order remain a runtime release check; this report does not claim they passed.
CI is also an acknowledged external blocker until 2026-09-01, so this mission
does not claim a green CI result.

Recommended runtime commands when the environment is available:

```bash
php artisan test tests/Feature/CategoryPaginationV1RegressionTest.php \
  tests/Feature/CategoryPageEmptyStateTest.php \
  tests/Feature/ArchivePaginationCanonicalTest.php \
  tests/Feature/CollectionPageStructuredDataTest.php \
  tests/Feature/PublicPageQueryBudgetTest.php
vendor/bin/pint --test
```

Then open a category with at least 13 published articles at 390 px, tab through
the pagination controls in both directions, and verify that focus never becomes
hidden or clipped and that page navigation preserves a usable layout.
