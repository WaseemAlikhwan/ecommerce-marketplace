# Storefront S8C Recovery

**Status:** DONE (2026-08-22) — S8C-R1/R2/R3 accepted; S8C complete  
**Source of truth:** ADR-040 and the S8C rules already recorded in project documentation  
**Pre-change baseline:** Docker PHPUnit 313 tests / 2225 assertions  
**Post-S8C gate:** Docker PHPUnit **327 tests / 2351 assertions**; focused S8C **49 / 516**; browser smoke **6 / 0**

S8C is complete. This file now records the interrupted starting point, recovery actions, and accepted final gate.

## Historical interrupted state (resolved)

At recovery start, `CatalogCriteriaTest` passed 11 tests / 81 assertions and `view:cache` passed, but the Storefront HTTP run had one error plus three failures, no post-S8C full Docker pass was recorded, browser smoke had two failures, and temporary smoke data remained. R1–R3 below resolved every item.

## S8C-R1 — Restore the automated test contract

**Status:** DONE (2026-08-22) — final focused gate green: **49 tests / 516 assertions**.

Inspect and resolve only the current focused failures:

1. The corrupt-topology test inserts the removed `product_variants.is_default` column.
2. The effective-Currency chip test uses a brittle HTML regex; verify that its URL removes Currency, min/max, and price sort.
3. The represented Category-label expectation assumes slugs although localized translations are rendered.
4. The accessibility test expects `mobile-navigation`, while production markup consistently uses `storefront-mobile-navigation`.

Requirements:

- Fix the real contract; do not weaken production behavior merely to make assertions green.
- Check for any additional focused failures after those corrections.
- Run only Catalog criteria, issue presenter, Storefront query, and Storefront HTTP tests.
- Do not run browser smoke or the full suite in R1.
- Stop and report exact focused test/assertion counts.

### R1 resolution notes

1. Corrupt-topology insert no longer writes `product_variants.is_default` (already corrected in the suite).
2. Currency chip assertion now matches the localized label (e.g. `US Dollar (USD)`) and still requires the remove-URL query to keep only `q`, dropping `currency`, `min_price`, `max_price`, and price `sort`.
3. Represented Category expectations already assert localized labels, not slugs.
4. Accessibility assertions already target `storefront-mobile-navigation`.

## S8C-R2 — Browser smoke and cleanup

**Status:** DONE (2026-08-22) — browser smoke **6 passed / 0 failed**; smoke DB rows and temp artifacts removed after the run.

Start only after R1 is green.

1. Make smoke selectors match the production IDs and accessible names.
2. Diagnose the pagination timeout:
   - first verify the Page 2 link and response are correct;
   - split an oversized scenario or use a justified timeout;
   - do not hide an actual slow/broken request by blindly increasing timeouts.
3. Run the six AR/EN, desktop/mobile, gallery, sparse Variant, no-JS, SEO, and dialog scenarios.
4. Make smoke setup/cleanup idempotent and ensure cleanup runs after success or failure.
5. Remove temporary scripts, manifest, generated images, test results, and `s8c-smoke-*` database rows after recording results.

Stop after an accepted browser result and report pass/fail counts.

### R2 resolution notes

1. Smoke selectors aligned to production (`Open menu` / `storefront-mobile-navigation`, `Filters` / `#catalog-filter-dialog`, `Sign up`, `Image unavailable`, pagination via Page 2 `href` then navigation).
2. Pagination verified by asserting Page 2 href contains `page=2` and loading that URL; no blind timeout increase.
3. Six scenarios all passed (desktop SEO/routes/pagination, gallery/metadata, sparse Variable selector, mobile dialogs, no-JS fallbacks, Arabic RTL).
4. Idempotent setup/cleanup ran; post-run cleanup deleted 27 products and smoke identity rows.
5. Temp smoke scripts/manifest/results removed; leftover check: products/users/categories/brands/attrs = 0.

## S8C-R3 — Final acceptance gate

**Status:** DONE (2026-08-22) — all required gates green; ADR-040 and `docs/development-plan.md` reconciled; smoke leftovers 0.

Start only after R1 and R2 are green.

Run once:

- focused S8C tests
- full Docker PHPUnit
- Pint
- Blade `view:cache`
- `npm run build`
- AR/EN translation parity
- fresh MySQL migrate + seed
- forbidden public-feature/reference searches

Then:

- reconcile ADR-040 and `docs/development-plan.md` with verified results;
- keep S8C `IN PROGRESS` if any required gate fails;
- mark S8C complete only with exact final counts and a clean smoke-data check.

### R3 gate results

| Gate | Result |
|------|--------|
| Focused S8C (`CatalogCriteriaTest\|CatalogIssuePresenterTest\|StorefrontProductQueryTest\|StorefrontCatalogHttpTest`) | **49 tests / 516 assertions** |
| Full Docker PHPUnit | **327 tests / 2351 assertions** |
| Pint `--test` | PASS (235 files) |
| Blade `view:cache` | PASS |
| `npm run build` | PASS |
| AR/EN key parity | **874 / 874** |
| Fresh migrate + seed | PASS (disposable `marketplace_s8c_gate`, then dropped) |
| Forbidden references (DemoCatalog, unsplash, JSON-LD, storefront Cart/Wishlist/Quick add/preview) | PASS — no active storefront hits; pre-existing account Wishlist placeholder chrome only |
| Smoke leftovers | products/users/categories/brands/attrs = **0** |

## Hard boundaries

- No Cart, Wishlist, Checkout, Reviews, ratings, conversion, Redis, FULLTEXT, JSON-LD, or moderation.
- No new dependency or migration unless a newly discovered blocker requires an explicit decision.
- No unrelated refactor, commit, or push.

## Prompts

Use only one at a time:

```text
Implement only S8C-R1 from @docs/tasks/storefront-s8c-recovery.md. Stop after its focused tests.
```

```text
Implement only S8C-R2 from @docs/tasks/storefront-s8c-recovery.md. Stop after browser cleanup and the smoke report.
```

```text
Implement only S8C-R3 from @docs/tasks/storefront-s8c-recovery.md. Stop after the final acceptance report.
```
