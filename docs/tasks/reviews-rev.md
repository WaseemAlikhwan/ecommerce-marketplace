# Reviews REV — OPEN-008 / OPEN-009 V1 Product Reviews

**Status:** DONE (REV-A…D accepted 2026-08-25)  
**Authority:** ADR-001, ADR-002, ADR-012, ADR-040, ADR-042; BR-CUS-06, BR-REV-01…06, BR-STR-06; FR-REV-01…05; FR-RBAC-04; Phase 9 reviews remainder  
**Baseline:** Checkout CHK DONE; Vendor Order Lifecycle VOL DONE (`VendorOrder` can reach `delivered`); Order Cancellation CAN DONE; Wishlist WSH DONE; no product-review persistence yet  
**Related OPEN:** **OPEN-008** eligibility + **OPEN-009** uniqueness (this task **freezes and closes V1** for both)

Implement only the named slice when asked. Do **not** start Coupons, Wishlist changes, Checkout/order/payment changes, Redis, catalog rating filters (unless already cheap), store-level rating (BR-STR-06), vendor review replies, card charge, FULLTEXT, or settlement ledgers unless a later approved slice says so.

## Planning freeze (APPROVED for REV V1)

| Topic | Decision for REV V1 | Notes |
|-------|---------------------|--------|
| Eligibility (OPEN-008) | Customer may review a **Product** only after purchasing it on a **Vendor Order that reached `delivered`** | Line item on that VO must reference the product (`order_items.product_id`). Purchased-but-not-delivered is **not** enough. Closes **OPEN-008** / BR-REV-02 for V1. |
| Uniqueness (OPEN-009) | **One review per customer per product** — unique `(user_id, product_id)` | Not per order. Idempotent re-submit of the same row is update-or-reject per create/edit rules below. Closes **OPEN-009** / BR-REV-04 for V1. |
| Grain | **Product** (`product_id`), not Variant | Matches purchase line `product_id`; no variant-specific reviews in V1. |
| Fields | **Rating** (required, integer 1–5) + **optional text** body | No photos, titles, or helpfulness votes in V1. |
| Moderation | Admin workflow **`pending` → `approved` \| `rejected`** before public display | New/edited customer submissions enter **`pending`**. Only **`approved`** reviews appear on the PDP. Rejected stay owner-visible as status only (no public leak of rejected body beyond owner). |
| Customer mutations | Authenticated eligible customer may **create** and **edit/resubmit** own review | Edit of approved/rejected/pending returns the row to **`pending`** (re-moderation). Stranger → **404**. Guests → auth redirect on mutate. |
| Surfaces | **PDP:** list approved reviews (+ thin product aggregate if cheap). **Customer:** create/edit-or-submit UI (PDP and/or account — prefer PDP-adjacent if cheaper). **Admin:** moderation queue approve/reject | Fail-closed ownership (BR-CUS-06 / FR-RBAC-04). |
| Product rating aggregate | **Display-only** average (and optional count) of **approved** reviews if thin | Recompute on approve/reject/edit in the same service transaction, or derive on read if still cheap. No Redis. No catalog/search rating filter unless already cheap (default: **out of V1**). |
| Store rating (BR-STR-06) | **Out of V1** | Do not invent store-level aggregation or write `stores.rating` from product reviews in this task. |
| Vendor replies (BR-REV-06) | **Out of V1** | No vendor response entity or UI. |
| i18n | AR/EN key parity for all new strings | Arabic default RTL; English LTR complete. |
| Storage | MySQL authoritative | No Redis review cache. |

### Source rules

> 1. Only purchasers may review; V1 requires the purchase to sit on a Vendor Order that reached **`delivered`** (OPEN-008 / BR-REV-01…02 / FR-REV-02…03).  
> 2. One review per customer + product (OPEN-009 / BR-REV-03…04 / FR-REV-04).  
> 3. Admins moderate before public display (BR-REV-05 / FR-REV-05).  
> 4. Reviews are private to the owner until approved; strangers get fail-closed **404** on mutate/show of non-public rows (BR-CUS-06).  
> 5. Catalog visibility / no public SKU / no exact inventory quantity remain in force on any product surfaces touched (ADR-040).

### Hard out of scope (every slice)

- Coupons; Wishlist changes; Checkout / Parent / VO / Payment changes  
- Store-level rating productization (BR-STR-06)  
- Vendor replies to reviews (BR-REV-06)  
- Guest reviews; photos; helpfulness; sharing  
- Catalog/search rating filters (unless already cheap — default **no**)  
- Redis / FULLTEXT / settlement ledger / card charge  
- Returns/refunds; admin cancel; Parent derivation  

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **REV-A** | Domain: migration + model + service/policy + eligibility + uniqueness + moderation states + optional thin product aggregate + focused domain tests | This freeze | **DONE** |
| **REV-B** | Customer + PDP UI: approved list on PDP; create/edit-or-submit; Form Requests + HTTP tests; AR/EN strings | REV-A | **DONE** |
| **REV-C** | Admin moderation UI: pending queue + approve/reject; fail-closed staff authz; focused HTTP tests; AR/EN | REV-A (REV-B preferred first for end-to-end, but C may follow A if B blocked) | **DONE** |
| **REV-D** | Acceptance gate + mark DONE; sync OPEN-008/009 closed + Phase 9 reviews note + BR-REV-02/04/06 and BR-STR-06 “out of REV V1” wording if needed | REV-A…C | **DONE** |

---

## REV-A — Domain reviews

**Status:** DONE (2026-08-25)

**Goal:** Authoritative, fail-closed review persistence with delivered-purchase eligibility and unique customer+product rows.

**In scope**

1. Migration: reviews table (name flexible) with `user_id`, `product_id`, rating, optional body, moderation status, timestamps; **unique `(user_id, product_id)`**; FKs fail-closed.  
2. Thin service: create/update (eligibility-gated), list approved for product, admin approve/reject; owner-only read of own non-public rows.  
3. Policy: customer mutate/view own; staff moderate; public may view approved only via service/query helpers (not stranger access to pending).  
4. Eligibility helper: exists delivered VO line for `(user, product)`.  
5. Optional thin product rating aggregate (approved only) if cheap.  
6. Focused domain tests: eligible create, ineligible reject, uniqueness, stranger isolation, moderation transitions, no SKU/qty leakage in review DTOs.

**Out of scope:** Blade/HTTP customer or admin UI; store rating; vendor replies; Coupons/Wishlist/Checkout.

**Done when:** Focused review domain tests green; no storefront/admin UI required yet.

**Verification (REV-A):** Focused `ReviewsRevATest` **7 / 44**. `product_reviews` unique `(user_id, product_id)`; `ReviewService` create/update/listApprovedForProduct/approve/reject; eligibility = delivered VO line; edit → `pending`; thin `products.approved_reviews_count` / `approved_rating_average`; policy owner/staff; no SKU/qty on review attributes. No Blade/HTTP / REV-B.

**Stop after REV-A.** (Completed.)

---

## REV-B — Customer / PDP UI

**Status:** DONE (2026-08-25)

**Goal:** Shoppers see approved reviews on the PDP; eligible customers can create/edit-or-submit with localized flash/errors.

**In scope**

1. PDP: approved reviews list (+ thin aggregate display if REV-A added it).  
2. Create/edit-or-submit controls for authenticated eligible customers; guests on mutate → auth redirect.  
3. Form Requests + policy reuse; fail-closed HTTP (owner OK, stranger 404, ineligible → 404 or localized reject).  
4. Focused HTTP tests; AR/EN key parity for new strings.  
5. No public SKU / exact inventory quantity regressions on PDP.

**Out of scope:** Admin moderation UI (REV-C); Coupons; Wishlist; Checkout; store rating; vendor replies.

**Done when:** Focused customer/PDP review HTTP tests green.

**Verification (REV-B):** Focused `ReviewsRevBTest` **7 / 121**. PDP approved list + thin aggregate; eligible create/edit (edit → pending); guest mutate → auth redirect; stranger/ineligible 404; AR/EN parity; no public SKU/qty on PDP. No admin moderation / REV-C.

**Stop after REV-B.** (Completed.)

---

## REV-C — Admin moderation UI

**Status:** DONE (2026-08-25)

**Goal:** Staff can approve/reject pending reviews so only approved content is public.

**In scope**

1. Admin pending queue + show + approve/reject actions (staff middleware / policy).  
2. Form Requests; localized flash; fail-closed non-staff.  
3. Focused HTTP tests; AR/EN strings.  
4. Approving/rejecting updates public visibility and thin product aggregate if present.

**Out of scope:** Coupons; Wishlist; Checkout; store rating; vendor replies; REV-D gate.

**Done when:** Focused admin moderation HTTP tests green.

**Verification (REV-C):** Focused `ReviewsRevCTest` **6 / 92**. Admin pending queue + show + approve/reject via `ReviewService`; Form Requests + staff middleware/policy; non-staff 403 / guest login redirect; approve/reject updates listApproved + thin aggregate; AR/EN admin strings. No Coupons/Wishlist/Checkout/store rating/vendor replies / REV-D.

**Stop after REV-C.** (Completed.)

---

## REV-D — Acceptance gate

**Status:** DONE (2026-08-25)

**Goal:** REV passes a small gate; OPEN-008 and OPEN-009 V1 recorded as closed by this task.

**In scope**

1. Gate: focused REV tests (A+B+C); Pint (REV-scoped); `view:cache`; AR/EN parity; forbidden-ref (no Coupons/Wishlist mutations/Checkout changes/Redis/guest reviews/vendor replies/store-rating writes/public SKU/exact qty regression); brief smoke (delivered purchase → submit review → admin approve → PDP shows; leftovers 0).  
2. Mark this task DONE with exact counts; sync `docs/decisions.md` OPEN-008/009 **V1 closed by REV**; Phase 9 reviews note in `docs/development-plan.md`; BR-REV-02/04 (and BR-REV-06 / BR-STR-06 “out of REV V1” if needed).

**Out of scope:** Full Docker suite unless project gate requires it — prefer focused REV + smoke (match WSH-C / CAN-D lightly).

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (REV-D / REV final)

| Check | Result |
|-------|--------|
| Focused REV (`ReviewsRevATest` + `ReviewsRevBTest` + `ReviewsRevCTest`) | **20 / 257** (A **7 / 44** + B **7 / 121** + C **6 / 92**) |
| Pint (REV-scoped PHP) | **PASS** (20 files) |
| `view:cache` | **PASS** |
| AR/EN key parity | **1035 / 1035** (missing 0) |
| Forbidden-ref (Coupons / Wishlist mutations / Checkout changes / Redis / guest reviews / vendor replies / store-rating writes; no public SKU / exact qty regression on PDP) | **PASS** |
| Smoke: delivered purchase → submit → admin approve → PDP shows | **PASS** — leftovers **0** |

**Verification (REV-D):** Task **DONE**. OPEN-008 / OPEN-009 V1 closed by REV; Phase 9 reviews note synced; BR-REV-02/04 delivered+uniqueness RULE wording synced; BR-REV-06 / BR-STR-06 out-of-REV-V1 wording synced. No Coupons, store-rating productization, vendor replies, Wishlist mutations, Checkout changes, Redis.

**Stop after REV-D.** (Completed.) Do not start Coupons or store-rating productization.

---

## Hard boundaries (every slice)

- Eligibility = purchased on a **delivered** Vendor Order for that product.  
- Uniqueness = one review per **customer + product**.  
- Public PDP shows **approved** only; moderation required.  
- Fail-closed ownership; stranger → 404 on private rows.  
- No Coupons, Wishlist changes, Checkout changes, Redis, store rating, vendor replies.  
- No public SKU; no exact inventory quantity.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only REV-A from @docs/tasks/reviews-rev.md. Stop after focused review domain tests.
```

```text
Implement only REV-B from @docs/tasks/reviews-rev.md. Stop after focused customer/PDP review HTTP tests.
```

```text
Implement only REV-C from @docs/tasks/reviews-rev.md. Stop after focused admin moderation HTTP tests.
```

```text
Implement only REV-D from @docs/tasks/reviews-rev.md. Stop after the final acceptance report.
```
