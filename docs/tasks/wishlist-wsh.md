# Wishlist WSH — OPEN-018 V1 Product Wishlist

**Status:** DONE (2026-08-24 — WSH-A…C accepted; OPEN-018 V1 closed by WSH)  
**Authority:** ADR-001, ADR-002, ADR-012, ADR-029, ADR-040, ADR-041; BR-CUS-06, BR-WSH-01…03; FR-WSH-01…03; FR-RBAC-04; Phase 6 remainder  
**Baseline:** Storefront Catalog S8C DONE; Cart C1 DONE; Checkout CHK DONE; Wishlist WSH-A…C DONE (OPEN-018 V1 closed)  
**Related OPEN:** **OPEN-018** wishlist target — **V1 closed by WSH** as Product, not Variant

Implement only the named slice when asked. Do **not** start Coupons, Reviews/ratings, Checkout changes, guest wishlist, Redis, convert-to-cart, card charge, FULLTEXT, or settlement ledgers unless a later approved slice says so.

## Planning freeze (APPROVED for WSH V1)

| Topic | Decision for WSH V1 | Notes |
|-------|---------------------|--------|
| Target grain | **Product** (`product_id`), **not** Variant | Closes **OPEN-018** / BR-WSH-03 for V1. Cart remains `variant_id` (ADR-029); wishlist does not pick a variant. |
| Audience | **Authenticated customers only** | Guests: redirect to login (or 403 on API-style posts). No guest/session wishlist in V1. |
| Uniqueness | Unique **`(user_id, product_id)`** | BR-WSH-02. Idempotent add (second add is no-op or same row). |
| Mutations | **Add** + **remove** only | No quantity, no notes, no sharing. |
| Surfaces | Storefront affordance (at least PDP; card optional if cheap) + **account wishlist list** replacing placeholder | Reuse existing account route name `account.wishlist` where practical. |
| Authorization | Fail-closed ownership (BR-CUS-06 / FR-RBAC-04) | Stranger → **404** on mutate/show of another user’s rows; list is owner-only. |
| Visibility | Wishlist **list** and add eligibility use **catalog storefront visibility** scopes (e.g. `Product::storefrontVisible()` / equivalent) | Hidden/unpublished/ineligible products must not appear as public cards; add of non-visible product → fail-closed (404 or localized reject). Persisted rows for later-invisible products: omit from list or show as unavailable without leaking private store data — prefer **omit** + silent skip for V1. |
| Presentation | Query-free presenters / existing product card contracts | **No public SKU**; **no exact inventory quantity** on wishlist UI. |
| i18n | AR/EN key parity for all new strings | Arabic default RTL; English LTR complete. |
| Storage | MySQL authoritative | No Redis wishlist. |
| Convert-to-cart | **Out of V1** | Not required by BR-WSH; not cheap enough vs Cart variant grain. User adds to cart from PDP as today. |

### Source rules

> 1. Authenticated customers can add/remove products (BR-WSH-01 / FR-WSH-01…02).  
> 2. Unique per customer + product (BR-WSH-02) — **product**, not variant (OPEN-018 V1).  
> 3. Wishlist is private to the owning customer (FR-WSH-03 / BR-CUS-06).  
> 4. Catalog visibility and no public SKU / exact qty remain in force (ADR-040 / project core).

### Hard out of scope (every slice)

- Coupons, Reviews, ratings  
- Checkout / order / payment changes  
- Guest or session wishlist  
- Redis / FULLTEXT / settlement ledger  
- Convert-to-cart / move-all-to-cart  
- Variant-targeted wishlist; sharing; price alerts  
- Admin wishlist UI  

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **WSH-A** | Domain: migration + model + service/policy + uniqueness + visibility-gated add + focused domain tests | This freeze | **DONE** |
| **WSH-B** | Storefront/account UI: add/remove + account list + Form Requests + HTTP tests; AR/EN strings | WSH-A | **DONE** |
| **WSH-C** | Acceptance gate + mark DONE; sync OPEN-018 closed + Phase 6 wishlist note | WSH-A, WSH-B | **DONE** |

---

## WSH-A — Domain wishlist

**Status:** DONE (2026-08-24)

**Goal:** Authoritative, fail-closed wishlist persistence keyed by customer + product.

**In scope**

1. Migration: wishlist table (name flexible) with `user_id`, `product_id`, timestamps; **unique `(user_id, product_id)`**; FKs fail-closed.  
2. Model + thin service (add/remove/list-for-user) enforcing ownership and storefront-visible product on **add**.  
3. Policy: customer may mutate/view only own wishlist.  
4. Focused tests: add, idempotent re-add, remove, uniqueness, stranger isolation, non-visible product rejected, no SKU/qty leakage in any domain DTO if present.

**Out of scope:** Blade/HTTP UI, guest wishlist, convert-to-cart, Coupons/Reviews.

**Done when:** Focused wishlist domain tests green; no storefront UI required yet.

**Verification (WSH-A):** Focused `WishlistWshATest` **9 / 56**. `wishlist_items` unique `(user_id, product_id)`; `WishlistService` add/remove/listFor; add gated by `Product::query()->storefrontVisible()`; list omits non-visible; policy owner-only; no SKU/qty on item attributes. No Blade/HTTP / WSH-B.

**Stop after WSH-A.** (Completed.)

---

## WSH-B — Storefront / account UI

**Status:** DONE (2026-08-24)

**Goal:** Customers can add/remove from the storefront and browse their account wishlist list.

**In scope**

1. Replace placeholder `account.wishlist` with real list using catalog-safe presenters/cards (visibility-scoped).  
2. Add/remove controls (PDP required; product card optional if low-risk). Guests hitting mutate → auth redirect.  
3. Form Requests + policy reuse; localized flash/errors; fail-closed HTTP (owner OK, stranger 404).  
4. Focused HTTP tests: owner add/remove/list, guest blocked, stranger 404, invisible product rejected, AR/EN keys added with parity.  
5. No public SKU or exact inventory quantity on wishlist surfaces.

**Out of scope:** Convert-to-cart, Coupons, Reviews, Checkout changes, guest wishlist, WSH-C gate.

**Done when:** Focused wishlist UI HTTP tests green.

**Verification (WSH-B):** Focused `WishlistWshBTest` **8 / 80**. Real `account.wishlist` list via `ProductCardPresenter` + storefront visibility; PDP add/remove; Form Requests abort 404; guest mutate → login; stranger destroy → 404; invisible add → 404; no public SKU / exact qty on list. Product-card wishlist CTA skipped (optional). No convert-to-cart / WSH-C.

**Stop after WSH-B.** (Completed.)

---

## WSH-C — Acceptance gate

**Status:** DONE (2026-08-24)

**Goal:** WSH passes a small gate; OPEN-018 V1 recorded as closed by this task.

**In scope**

1. Gate: focused WSH tests (A+B); Pint (WSH-scoped); `view:cache`; AR/EN parity; forbidden-ref (no Coupons/Reviews/guest wishlist persistence/convert-to-cart/Redis/public SKU/exact qty regression); brief smoke (login → add → account list → remove; leftovers 0).  
2. Mark this task DONE with exact counts; sync `docs/decisions.md` OPEN-018 **V1 closed by WSH**; Phase 6 wishlist note in `docs/development-plan.md` (and BR-WSH-02/03 wording if needed).

**Out of scope:** Full Docker suite unless project gate requires it — prefer focused WSH + smoke (match CAN-D / VOL-C lightly).

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (WSH-C / WSH final)

| Check | Result |
|-------|--------|
| Focused WSH (`WishlistWshATest` + `WishlistWshBTest`) | **17 / 136** (A **9 / 56** + B **8 / 80**) |
| Pint (WSH-scoped PHP) | **PASS** (11 files) |
| `view:cache` | **PASS** |
| AR/EN key parity | **1003 / 1003** (missing 0) |
| Forbidden-ref (Coupons / Reviews / guest wishlist persistence / convert-to-cart / Redis; no public SKU / exact qty regression on wishlist surfaces) | **PASS** |
| Smoke: login → PDP add → account list → remove | **PASS** — leftovers **0** |

**Verification (WSH-C):** Task **DONE**. OPEN-018 V1 closed by WSH; Phase 6 wishlist note synced; BR-WSH-02/03 product-level V1 wording synced. No Coupons, Reviews, convert-to-cart, guest wishlist, Checkout changes, Redis.

**Stop after WSH-C.** (Completed.) Do not start Coupons or Reviews.

---

## Hard boundaries (every slice)

- Wishlist targets **Product** only (not Variant).  
- Authenticated customers only; no guest wishlist.  
- Fail-closed ownership; stranger → 404.  
- Reuse catalog visibility; no public SKU; no exact inventory quantity.  
- No Coupons, Reviews, ratings, Checkout changes, convert-to-cart, Redis.  
- Money display via existing catalog presenters if shown; no new FX.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only WSH-A from @docs/tasks/wishlist-wsh.md. Stop after focused wishlist domain tests.
```

```text
Implement only WSH-B from @docs/tasks/wishlist-wsh.md. Stop after focused wishlist UI HTTP tests.
```

```text
Implement only WSH-C from @docs/tasks/wishlist-wsh.md. Stop after the final acceptance report.
```
