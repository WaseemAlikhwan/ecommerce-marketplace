# Coupons CPN — OPEN-007 V1 Coupons

**Status:** DONE (CPN-A…C accepted 2026-08-25)  
**Authority:** ADR-001, ADR-002, ADR-012, ADR-040, ADR-042; BR-CPN-01…09, BR-COM-05, BR-CAN-05; FR-CPN-01…05; FR-ADM-02; Phase 8 coupons  
**Baseline:** Checkout CHK DONE; Order Cancellation CAN DONE; Wishlist WSH DONE; Reviews REV DONE; no coupon persistence / checkout discount yet  
**Related OPEN:** **OPEN-007** coupon stacking (this task **freezes and closes V1**)

Implement only the named slice when asked. Do **not** start Reviews/Wishlist changes, Redis, card charge, settlement ledger, complex stacking matrices, vendor self-serve coupon UI, catalog promo engines, FULLTEXT, or Parent derivation unless a later approved slice says so.

## Planning freeze (APPROVED for CPN V1)

| Topic | Decision for CPN V1 | Notes |
|-------|---------------------|--------|
| Stacking (OPEN-007) | **Exactly one coupon code per checkout** (Parent place attempt) | No platform + vendor simultaneous apply. Closes **OPEN-007** / BR-CPN-05 / BR-CPN-06 for V1. |
| Scopes | **Platform** coupons and **vendor-scoped** coupons both exist | Vendor coupons discount **only that vendor’s eligible items** (BR-CPN-07). |
| Types | **Percent** and **fixed** amount (minor units) | Optional product and/or category allowlists; schedule window; min eligible subtotal; max discount cap; global + per-user redemption caps (BR-CPN-02…04). |
| Min-order basis | Platform → sum of **eligible** line minors on the Parent (same currency). Vendor → sum of **eligible** line minors for that vendor only. | Fail-closed if min not met. |
| Currency | Coupon carries `currency_code` | Apply fails closed if no positive eligible amount in that currency. |
| Commission (V1) | Commission base stays **pre-coupon** Vendor Order item subtotal | Coupon reduces customer due only; does not change commission base in this task (BR-COM-05 / current CheckoutService). |
| Admin | **Staff CRUD** for platform + vendor-scoped coupons; **seed/factory for tests** | Vendor self-serve coupon UI **out of V1**. |
| Checkout | Auth customer **apply** / **remove** on checkout; snapshot on place; redeem in place transaction | Guests → auth redirect on mutate. Fail-closed validation (BR-CPN-08). |
| Cancel release | On existing CAN cancel paths, **release** redemption toward limits | Compensating update only; no settlement ledger (BR-CAN-05 thin hook). |
| i18n / money | AR/EN key parity; public money as decimal strings | Arabic default RTL; English LTR complete. |
| Storage | MySQL authoritative | No Redis coupon cache. |

### Stacking examples (normative)

| ID | Cart | Applied code | Result |
|----|------|--------------|--------|
| **E1** | Multi-vendor; platform `SAVE10` (10% off eligible) | `SAVE10` | Discount on eligible lines only; amount allocated across Vendor Orders by eligible line share for VO snapshots. Second code rejected until first removed. |
| **E2** | Vendor A + Vendor B; vendor-A code `SHOPA5` | `SHOPA5` | Discount only Vendor A eligible items; Vendor B unaffected. |
| **E3** | Same cart with `SAVE10` already applied | try `SHOPA5` | **Fail-closed** localized reject: only one coupon per checkout. |
| **E4** | Vendor-A-only cart; platform fixed `FLAT50` below min eligible subtotal | `FLAT50` | Reject (min not met). |
| **E5** | Code expired / over global or per-user limit | any | Reject; no partial apply. |

### Source rules

> 1. Coupons may be platform-scoped or vendor-scoped (BR-CPN-01 / FR-CPN-01).  
> 2. Discount types: percentage and fixed amount; restrictions and limits as frozen above (BR-CPN-02…04 / FR-CPN-02…04).  
> 3. **V1 stacking:** exactly one coupon code per checkout (OPEN-007 closed by CPN / BR-CPN-05…06 / FR-CPN-05).  
> 4. Vendor coupons discount only that vendor’s eligible items (BR-CPN-07).  
> 5. Redemption is recorded and counted toward usage limits inside the checkout transaction (BR-CPN-08).  
> 6. Do not over-engineer V1 — no complex promotion engines (BR-CPN-09).

### Hard out of scope (every slice)

- Reviews / Wishlist mutations or feature changes  
- Redis; card/wallet charge; settlement / refund ledger  
- Complex stacking (platform + per-VO simultaneous coupons)  
- Vendor self-serve coupon admin UI  
- Catalog/search promo engines; auto-apply; BOGO; free shipping coupons  
- Changing commission recognition math beyond “base stays pre-coupon”  
- FULLTEXT; Parent derivation; post-ship returns  

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **CPN-A** | Domain: migrations (`coupons`, redemptions), model/enums, `CouponService` validate/quote, factory/seeder hooks, focused domain tests | This freeze | **DONE** |
| **CPN-B1** | Checkout path only: apply/remove HTTP; place-order snapshot + redeem; CAN cancel-release hook; checkout Blade totals; AR/EN; focused HTTP tests | CPN-A | **DONE** |
| **CPN-B2** | Staff coupon CRUD (admin) for platform + vendor-scoped rows; focused admin HTTP tests | CPN-B1 | **DONE** |
| **CPN-C** | Acceptance gate + mark DONE; sync OPEN-007 closed + BR-CPN-05/06 RULE wording + Phase 8 coupons note | CPN-A, CPN-B1, CPN-B2 | **DONE** |

---

## CPN-A — Domain coupons

**Status:** DONE (2026-08-25)

**Goal:** Authoritative, fail-closed coupon persistence and quote/validate logic with single-code stacking rules (no checkout HTTP/Blade required yet).

**In scope**

1. Migrations: `coupons` (code, scope platform|vendor, vendor_id nullable, type percent|fixed, value, currency_code, schedule, min eligible, max cap, product/category restrictions as cheap FKs/JSON pivot, global + per-user limits, active flag, timestamps); `coupon_redemptions` (coupon_id, user_id, parent_order_id and/or vendor_order_id as needed, amounts, timestamps); FKs fail-closed; unique code.  
2. Thin `CouponService` (name flexible): validate + quote discount for a cart/checkout candidate under the freeze (E1–E5); no place-order side effects required in A beyond pure quote helpers if useful.  
3. Factory (+ optional seeder hooks) for tests.  
4. Focused domain tests: platform vs vendor scope, min/max/window/limits, single-code rule helpers if modeled, currency fail-closed, no SKU/exact qty leakage in coupon DTOs.

**Out of scope:** Checkout HTTP/Blade; admin CRUD UI; place-order snapshot; cancel release; Reviews/Wishlist.

**Done when:** Focused coupon domain tests green; no storefront/admin UI required yet.

**Verification (CPN-A):** Focused `CouponsCpnATest` **8 / 29**. `coupons` + pivots + `coupon_redemptions`; `CouponService::validateAndQuote` / `assertSingleCodeAllowed`; platform vs vendor scope; min/window/limits/currency fail-closed; VO allocation on quote; no SKU/qty in `CouponQuote`. No Checkout HTTP/Blade / CPN-B.

**Stop after CPN-A.** (Completed.)

---

## CPN-B1 — Checkout path

**Status:** DONE (2026-08-25)

**Goal:** Auth customers apply/remove one coupon on checkout; place-order re-validates, snapshots, and redeems; cancel releases redemptions; checkout Blade shows discounted totals.

**In scope**

1. Checkout apply/remove (Form Requests + auth); guests → login redirect.  
2. Place-order: re-validate session code; snapshot on Parent/VO; redeem inside place transaction; commission base stays pre-coupon.  
3. Thin cancel-release hook on existing CAN cancel paths.  
4. Checkout Blade apply/remove + discount/COD totals; localized flash/errors; AR/EN key parity.  
5. Focused HTTP/service tests; no public SKU / exact qty regressions on touched surfaces.

**Out of scope:** Staff coupon CRUD (CPN-B2); vendor self-serve; Reviews/Wishlist; Redis; card charge; settlement ledger; CPN-C gate.

**Done when:** Focused checkout coupon tests green.

**Verification (CPN-B1):** Focused `CouponsCpnATest` + `CouponsCpnB1Test` **13 / 75**. Apply/remove session; place snapshot + redeem; commission base pre-coupon; CAN cancel release; checkout Blade totals; AR/EN coupon strings. No admin CRUD / CPN-B2 / CPN-C.

**Stop after CPN-B1.** (Completed.)

---

## CPN-B2 — Staff coupon CRUD

**Status:** DONE (2026-08-25)

**Goal:** Staff manage platform + vendor-scoped coupons in admin.

**In scope**

1. Staff coupon CRUD (admin) for platform + vendor-scoped rows.  
2. Focused admin HTTP tests; AR/EN for admin coupon strings.  
3. No public SKU / exact inventory quantity regressions on touched surfaces.

**Out of scope:** Gate slice; vendor self-serve coupon UI; Redis; card charge; settlement ledger; Reviews/Wishlist feature work.

**Done when:** Focused admin coupon HTTP tests green.

**Verification (CPN-B2):** Focused `CouponsCpnB2Test` **6 / 65**. Staff create/list/show/edit/status for platform + vendor-scoped coupons; guest redirect; customer/vendor 403; invalid payloads rejected; AR/EN admin coupon strings; no destroy route / no SKU or stock qty on product pickers. No CPN-C.

**Stop after CPN-B2.** (Completed.)

---

## CPN-C — Acceptance gate

**Status:** DONE (2026-08-25)

**Goal:** CPN passes a small gate; OPEN-007 V1 recorded as closed by this task.

**In scope**

1. Gate: focused CPN tests (A+B1+B2); Pint (CPN-scoped); `view:cache`; AR/EN parity; forbidden-ref (no Reviews/Wishlist mutations, Redis, card charge, settlement ledger, stacking matrices beyond single-code; no public SKU/exact qty regression); brief smoke (apply one code → place → totals reflect discount; leftovers 0).  
2. Mark this task DONE with exact counts; sync `docs/decisions.md` OPEN-007 **V1 closed by CPN**; Phase 8 coupons note in `docs/development-plan.md`; BR-CPN-05/06 RULE wording (single coupon per checkout).

**Out of scope:** Full Docker suite unless project gate requires it — prefer focused CPN + smoke (match REV-D / WSH-C lightly).

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (CPN-C / CPN final)

| Check | Result |
|-------|--------|
| Focused CPN (`CouponsCpnATest` + `CouponsCpnB1Test` + `CouponsCpnB2Test`) | **19 / 140** (A **8 / 29** + B1 **5 / 46** + B2 **6 / 65**) |
| Pint (CPN-scoped PHP) | **PASS** (34 files) |
| `view:cache` | **PASS** |
| AR/EN key parity | **1091 / 1091** (missing 0) |
| Forbidden-ref (Reviews/Wishlist mutations; Redis; card charge; settlement ledger; stacking beyond single-code; no public SKU / exact qty regression on coupon surfaces) | **PASS** |
| Smoke: apply one code → place → totals reflect discount | **PASS** — `SAVE10` 10% on 1000 → discount **100**; commission base pre-coupon; leftovers **0** |

**Verification (CPN-C):** Task **DONE**. OPEN-007 V1 closed by CPN; Phase 8 coupons note synced; BR-CPN-05/06 single-coupon-per-checkout RULE wording synced. No vendor self-serve coupon UI, settlement ledger, card charge, Reviews/Wishlist mutations, Redis.

**Stop after CPN-C.** (Completed.) Do not start settlement ledger, card charge, or vendor coupon self-admin.

---

## Hard boundaries (every slice)

- **One coupon code per checkout** — no platform+vendor stack.  
- Vendor coupons → that vendor’s eligible items only.  
- Redeem inside place transaction; fail-closed validation.  
- Commission base remains pre-coupon for V1.  
- No Reviews/Wishlist feature changes; no Redis; no card charge; no settlement ledger.  
- No public SKU; no exact inventory quantity.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only CPN-A from @docs/tasks/coupons-cpn.md. Stop after focused coupon domain tests.
```

```text
Implement only CPN-B1 from @docs/tasks/coupons-cpn.md. Stop after focused checkout coupon HTTP tests.
```

```text
Implement only CPN-B2 from @docs/tasks/coupons-cpn.md. Stop after focused admin coupon HTTP tests.
```

```text
Implement only CPN-C from @docs/tasks/coupons-cpn.md. Stop after the final acceptance report.
```
