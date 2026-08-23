# Checkout CHK — Multi-Vendor COD Checkout

**Status:** DONE (CHK-E accepted 2026-08-24)  
**Authority:** ADR-001, ADR-002, ADR-003, ADR-005, ADR-006, ADR-012, ADR-019, ADR-029, ADR-032, ADR-033, ADR-041, **ADR-042**; BR-CHK / BR-VO / BR-SHP / BR-COM / BR-PAY / BR-CUR / BR-INV / BR-GEO; UC-C04  
**Readiness audit:** [`checkout-readiness.md`](./checkout-readiness.md)  
**Baseline:** Cart C1 complete; storefront cart CTA enabled in CHK-D  

Implement only the named slice when asked. Do **not** start Wishlist, Coupons, Reviews, card gateways, Redis, or settlement ledgers unless a later approved slice says so.

## Approved decisions (CHK-0 freeze)

| Topic | Decision | Approval |
|-------|----------|----------|
| Mixed-currency checkout | Place without FX; per-currency COD dues; each VO single-currency | **APPROVED** ADR-042 |
| Inventory at checkout | Decrement in checkout txn with `lockForUpdate` | **APPROVED** ADR-042 |
| Shipping fee V1 | Configurable flat fee per Vendor Order (store + platform default) | **APPROVED** ADR-042 |
| COD payment grain | One Payment per Vendor Order | **APPROVED** ADR-042 |
| Shipping address | One Parent address snapshot, copied to VOs | **APPROVED** ADR-042 |
| Order public codes | `PO-…` / `VO-…` | **APPROVED** ADR-042 |
| COD status set | `pending` \| `collected` \| `cancelled` | **APPROVED** ADR-042 |
| Commission base | Item subtotal excluding shipping | **APPROVED** ADR-042 |
| Commission recognition | Snapshot at placement; recognize at VO `delivered` | **APPROVED** ADR-042 |
| Geo / Syria validation | Seed governorates+cities; Syria-only; no area level | **APPROVED** ADR-042 |
| V1 notification channels | Mail + database minimum | **APPROVED** ADR-042 |

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **CHK-0** | Decisions approved; ADRs/BRs/plan synced; this file READY | Readiness audit | **DONE** |
| **CHK-A** | Schema + domain for geo, addresses, orders, payments, commission snapshots | CHK-0 | **DONE** |
| **CHK-B** | `CheckoutService` atomic place-order | CHK-A | **DONE** |
| **CHK-C** | `ShippingCalculator` V1 + `PaymentGateway` COD | CHK-B | **DONE** |
| **CHK-D** | Checkout + customer/vendor order UIs; enable cart CTA | CHK-B, CHK-C | **DONE** |
| **CHK-E** | Final acceptance gate | CHK-D | **DONE** |

---

## CHK-0 — Decision freeze & doc sync

**Status:** DONE (2026-08-23)

**Outcome:** ADR-042 accepted; authoritative docs synced; this file READY. No application code.

**Stop after CHK-0.** (Completed.)

---

## CHK-A — Schema & domain foundation

**Status:** DONE (2026-08-23)

**Goal:** Persist geo, customer addresses, Parent/Vendor/Item orders, payments, and commission snapshot fields without placing orders yet.

**In scope**

1. Migrations: `governorates`, `cities` (Syria seed); `customer_addresses`; `parent_orders`; `vendor_orders`; `order_items`; `payments`; commission snapshot columns; store flat-shipping setting + platform default config as decided.
2. Eloquent models + minimal enums (`pending|collected|cancelled` for payments; minimal order statuses needed for placement scaffolding).
3. Factories/seeders sufficient for later feature tests.
4. Policies with deny-by-default for order/payment resources (even if no HTTP yet).

**Out of scope:** Checkout HTTP, stock mutation, mail sending, Blade checkout, coupons, wishlist.

**Done when:** Focused schema/model tests green; migrate+seed clean on Docker MySQL.

**Verification (CHK-A):** Focused `CheckoutChkASchemaTest` **7 / 51**; Docker `migrate:fresh --seed` clean (Syria geo + commission settings). No CheckoutService / HTTP / Blade / stock mutation.

**Stop after CHK-A.** (Completed.)

---

## CHK-B — CheckoutService transaction

**Status:** DONE (2026-08-23)

**Goal:** Authenticated actor can place a multi-vendor order from the cart in one DB transaction.

**In scope**

1. Cart revalidation (visibility, stock, prices).
2. Create Parent + Vendor Orders + Items with snapshots (money, currency, names, address, commission).
3. Inventory decrement with row locks.
4. Cart consumption on success; full rollback on failure.
5. Feature tests: happy path, concurrent stock, empty cart, unavailable variant, vendor isolation of created rows, mixed-currency dues.

**Out of scope:** Full shipping calculator beyond stub hook, payment gateway beyond stub hook, Blade UI.

**Done when:** Focused CheckoutService tests green.

**Verification (CHK-B):** Focused `CheckoutChkBServiceTest` **6 / 95**. Stub shipping fee = 0; stub COD payment recorder creates `pending` payments per VO. No HTTP/Blade/mail/coupons/Wishlist.

**Stop after CHK-B.** (Completed.)

---

## CHK-C — Shipping V1 + COD payment

**Status:** DONE (2026-08-23)

**Goal:** Place-order computes configurable flat vendor shipping and records COD payments per Vendor Order.

**In scope**

1. `ShippingCalculator` interface + flat-per-vendor implementation (configurable).
2. `PaymentGateway` interface + COD driver creating `pending` payments per VO.
3. Wire into `CheckoutService`.
4. Tests for fee attachment and payment rows; no card driver.

**Out of scope:** Courier APIs, mark-collected UI (may be minimal later), FX conversion.

**Done when:** Focused shipping/COD tests green.

**Verification (CHK-C):** Focused `CheckoutChkCShippingPaymentTest` **5 / 51**. Store override vs platform default; multi-vendor fees; pending COD amounts = items+shipping; `PaymentMethod` COD-only; stubs removed. No HTTP/Blade/mail.

**Stop after CHK-C.** (Completed.)

---

## CHK-D — Checkout & order UIs

**Status:** DONE (2026-08-23)

**Goal:** Customer completes checkout in the browser; customer and vendor can read their orders.

**In scope**

1. Checkout routes/form (address + confirm); auth gate.
2. Enable cart “Continue to checkout” CTA for authenticated non-empty carts.
3. Customer Parent order show/index.
4. Vendor Vendor-order show/index (own only).
5. AR/EN string parity.
6. Mail + database notifications on placement.

**Out of scope:** Wishlist, coupons, reviews, admin finance reports, cancel/refund matrix UI.

**Done when:** Focused HTTP/UI tests green.

**Verification (CHK-D):** Focused `CheckoutChkDUiTest` **6 / 55**. Cart CTA enabled (`CartC1D2Test` still green). Auth checkout → Parent Order show; guest → login; vendor isolation 404; mail+database notifications; AR/EN parity OK (959/959). No Wishlist/coupons/reviews/CHK-E.

**Stop after CHK-D.** (Completed.)

---

## CHK-E — Final acceptance gate

**Status:** DONE (2026-08-24)

**Goal:** Phase 7 V1 vertical slice passes project gate.

**In scope**

1. Focused Checkout tests  
2. Full Docker PHPUnit  
3. Pint (Checkout-scoped)  
4. `view:cache`  
5. `npm run build`  
6. AR/EN parity  
7. Forbidden-ref checks (no Wishlist persistence, no coupon engine, no card charge, no public SKU/exact stock regression)  
8. Smoke: login → cart → checkout COD → customer + vendor order views; cleanup  
9. Mark this task DONE with exact counts  

**Out of scope:** Starting Phase 8/9 features.

**Done when:** Gate table recorded with exact counts; smoke leftovers 0.

### CHK-E gate results (2026-08-24)

| Check | Result |
|-------|--------|
| Focused Checkout (`CheckoutChkASchemaTest\|CheckoutChkBServiceTest\|CheckoutChkCShippingPaymentTest\|CheckoutChkDUiTest`) | **24 tests / 252 assertions** |
| Full Docker PHPUnit | **393 tests / 3044 assertions** |
| Pint (`--test`, Checkout-scoped paths) | PASS (39 files). Project-wide `--test` still reports pre-existing CRLF `line_ending` noise outside Checkout. |
| `php artisan view:cache` | PASS |
| `npm run build` | PASS |
| AR/EN parity | **EN=960 AR=960**, `PARITY_OK` |
| Forbidden refs | PASS — no Wishlist models/migrations; no coupon engine; no card charge (`stripe`/`paypal`/etc. absent); `PaymentMethod` COD-only; no public SKU / exact stock on storefront surfaces |
| HTTP smoke | PASS — login → add to cart → checkout COD → customer Parent Order + vendor Vendor Order views; leftovers **0** |

**Gate fixes included in CHK-E**

- Missing storefront JSON literal `__('Confirm your address and place a cash-on-delivery order.')` added to EN/AR so LocalizationTest S8C literal coverage passes.

**Stop after CHK-E.** Do not start Coupons, Wishlist, Reviews, or cancellations.

---

## Hard boundaries (every slice)

- No Wishlist, Coupons, Reviews, ratings, card/wallet charge, Redis, FULLTEXT, or settlement ledger.
- No public SKU or exact inventory quantity on storefront catalog surfaces.
- Vendor Order authorization fail-closed.
- Money remains integer minor units; public money serializes as decimal strings.
- No commit/push unless the user asks.

## Prompts

```text
Implement only CHK-A from @docs/tasks/checkout-chk.md. Stop after focused schema/domain tests.
```

```text
Implement only CHK-B from @docs/tasks/checkout-chk.md. Stop after focused CheckoutService tests.
```

```text
Implement only CHK-C from @docs/tasks/checkout-chk.md. Stop after focused shipping/COD tests.
```

```text
Implement only CHK-D from @docs/tasks/checkout-chk.md. Stop after focused checkout/order UI tests.
```

```text
Implement only CHK-E from @docs/tasks/checkout-chk.md. Stop after the final acceptance report.
```
