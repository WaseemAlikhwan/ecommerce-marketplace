# Order Cancellation CAN — OPEN-010 V1 Cancel Matrix

**Status:** DONE (CAN-A…D accepted 2026-08-24)  
**Authority:** ADR-001, ADR-002, ADR-006, ADR-012, **ADR-042**; BR-VO / BR-CHK / BR-PAY / BR-INV / BR-CAN / BR-REF / BR-NTF; Phase 7 remainder + Phase 9 boundary  
**Baseline:** Checkout CHK DONE (ADR-042); Vendor Order Lifecycle VOL DONE (fulfillment advance + customer VO labels); stock decremented at placement  
**Related OPEN:** **OPEN-010** cancellation matrix (this task freezes and implements V1 — **V1 closed by CAN**); OPEN-017 vendor suspend in-flight (**out of this task**)

Implement only the named slice when asked. Do **not** start Wishlist, Coupons, Reviews, card charge, Redis, settlement ledgers, COD `collected`, Parent auto-derivation engines, or full Phase 9 refund productization unless a later approved slice says so.

## Planning freeze (APPROVED for CAN V1)

| Topic | Decision for CAN V1 | Notes |
|-------|---------------------|--------|
| Customer cancel | **Parent Order only**, all-or-nothing, only while **every** Vendor Order is still `pending` | No independent customer VO cancel in V1. Closes BR-CAN-01/02 for V1. |
| Vendor cancel | **Own VO only**, only while status is `pending` or `confirmed` (before `shipped`) | Sibling VOs unaffected. Closes BR-CAN-03 for V1. |
| Forbidden window | After `shipped` or `delivered`: **no cancel** | Returns/refunds FUTURE. |
| Stock restore | On cancel: restore inventory for cancelled VO line items (BR-CAN-04 / BR-INV-06) | Transactional + `lockForUpdate` on stock rows. |
| COD Payment | Set that VO’s Payment to `cancelled` / do-not-collect (ADR-042 / BR-REF-02 direction) | Never mutate `collected` in CAN (COD collected **out of scope**). |
| Notifications | Mail + database to customer + affected vendor(s) (BR-NTF-01) | Same channel floor as ADR-042 / VOL. |
| Parent coherence | Narrow cancel-side rules only — **not** a BR-VO-05 derivation engine | Customer Parent cancel → Parent `cancelled` + all VOs `cancelled`. Vendor VO cancel → that VO `cancelled`; Parent stays `placed` unless **no non-cancelled VOs remain**, then set Parent `cancelled`. No `partial` status; no totals/commission recomputation ledger. |
| Coupons | No coupon release | Coupons not in V1 checkout. |
| Admin / refunds | No admin cancel UI; no settlement/refund ledger | Phase 9 / FUTURE. |
| Authorization | Fail-closed: owner-only; stranger → **404**; illegal state → rejected with localized error | Matches VOL fail-closed pattern. |

### Source rule (from VOL planning freeze)

> 1. **Customer:** may cancel a **Parent Order** only while **every** Vendor Order is still `pending` (all-or-nothing).  
> 2. **Vendor:** may cancel **own** VO only while status is `pending` or `confirmed` (before `shipped`).  
> 3. On cancel: restore stock; set COD Payment to `cancelled` / do-not-collect; no coupon logic.  
> 4. After `shipped` or `delivered`: no cancel in V1.

### Hard out of scope (every slice)

- Wishlist, Coupons, Reviews/ratings  
- COD Payment `collected`  
- Parent auto-derivation engine / `partial` Parent status product  
- Settlement / refund ledger  
- Card/wallet charge, Redis, FULLTEXT  
- Admin cancel UI; returns after ship/deliver  

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **CAN-A** | Domain cancel service + policy guards + stock restore + Payment→cancelled + notifications + Parent coherence + focused domain tests | This freeze | **DONE** |
| **CAN-B** | Vendor VO show cancel affordance (`pending`/`confirmed` only) + Form Request + HTTP tests | CAN-A | **DONE** |
| **CAN-C** | Customer Parent show cancel (all-VO-pending only) + Form Request + HTTP tests | CAN-A | **DONE** |
| **CAN-D** | Acceptance gate + mark DONE; sync OPEN-010 / Phase note if needed | CAN-A, CAN-B, CAN-C | **DONE** |

---

## CAN-A — Domain cancellations

**Status:** DONE (2026-08-24)

**Goal:** Authoritative, fail-closed cancel operations after COD placement.

**In scope**

1. Transactional cancel service (name flexible) enforcing the freeze graph only.  
2. Policies: customer may cancel own Parent under all-VO-pending rule; vendor may cancel own VO under pending/confirmed rule.  
3. On cancel: restore stock for affected lines; set COD Payment(s) to `cancelled`; apply Parent coherence rules above.  
4. Notifications: customer + affected vendor(s) (mail + database).  
5. Focused feature/unit tests for happy paths, illegal windows, stranger isolation, and multi-VO Parent cancel.

**Out of scope:** Blade UI, admin UI, COD `collected`, Wishlist/Coupons/Reviews, settlement ledger.

**Done when:** Focused cancellation domain tests green; no HTTP UI required yet (prefer service-level tests).

**Verification (CAN-A):** Focused `OrderCancellationCanATest` **5 / 44**. `OrderCancellationService` locks Parent/VO/stock, restores variant quantities, sets COD Payment to `cancelled` (never `collected`), applies Parent coherence (all-VO customer cancel; last vendor VO → Parent cancelled), notifies customer + affected vendors. Policies `cancel` on Parent/VO. No Blade / CAN-B.

**Stop after CAN-A.** (Completed.)

---

## CAN-B — Vendor cancel UI

**Status:** DONE (2026-08-24)

**Goal:** Vendor can cancel eligible own Vendor Orders from the existing vendor order UI.

**In scope**

1. Cancel action on vendor VO show, enabled only for `pending`/`confirmed`.  
2. Form Request + policy reuse; localized flash/errors.  
3. Focused HTTP tests: owner success, non-owner 404, illegal status rejected, stock/payment side effects visible via domain assertions.

**Out of scope:** Customer UI, admin UI, index bulk cancel (unless trivially low-risk read-only hint).

**Done when:** Focused vendor cancel UI tests green.

**Verification (CAN-B):** Focused `OrderCancellationCanBTest` **5 / 51**. `POST vendor.orders.cancel` + Cancel CTA on VO show (`pending`/`confirmed` via `vendorCanCancelVendorOrder` + `cancel` policy). Form Request fail-closed 404; illegal/shipped/delivered/collected → redirect with localized error; owner success restores stock and cancels Payment. No customer/admin UI / CAN-C.

**Stop after CAN-B.** (Completed.)

---

## CAN-C — Customer cancel UI

**Status:** DONE (2026-08-24)

**Goal:** Customer can cancel an eligible Parent Order from the existing account order UI.

**In scope**

1. Cancel action on customer Parent show, enabled only when every VO is `pending`.  
2. Form Request + policy reuse; localized flash/errors.  
3. Focused HTTP tests: owner success (all VOs + Parent cancelled), ineligible Parent rejected, stranger 404, no vendor advance controls introduced.

**Out of scope:** Customer VO-level cancel, admin UI, Wishlist/Coupons/Reviews.

**Done when:** Focused customer cancel UI tests green.

**Verification (CAN-C):** Focused `OrderCancellationCanCTest` **4 / 49**. `POST account.orders.cancel` + Cancel CTA on Parent show (all VOs `pending` via `customerCanCancelParent` + `cancel` policy). Form Request fail-closed 404; ineligible (confirmed/shipped VO) → redirect with localized error; owner success cancels Parent + all VOs, restores stock, cancels Payments. No vendor advance controls / customer VO cancel / CAN-D.

**Stop after CAN-C.** (Completed.)

---

## CAN-D — Acceptance gate

**Status:** DONE (2026-08-24)

**Goal:** CAN passes a small gate; OPEN-010 V1 recorded as implemented via this task.

**In scope**

1. Gate: focused CAN tests (A+B+C); Pint (CAN-scoped); `view:cache`; AR/EN parity; forbidden-ref (no Wishlist/Coupons/Reviews/settlement ledger/COD collected mutation); brief smoke (vendor cancel VO and/or customer cancel Parent → statuses + stock coherent); leftovers 0.  
2. Mark this task DONE with exact counts; sync Phase 7/9 note and OPEN-010 “V1 closed by CAN” note in decisions if needed (no premature ADR close before this gate).

**Out of scope:** Full Docker suite unless project gate for this slice requires it — prefer focused CAN + smoke (match VOL-C / CHK-E lightly).

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (CAN-D / CAN final)

| Check | Result |
|-------|--------|
| Focused CAN (`OrderCancellationCanATest` + `OrderCancellationCanBTest` + `OrderCancellationCanCTest`) | **14 / 143** |
| Pint (CAN-scoped PHP) | **PASS** (13 files) |
| `view:cache` | **PASS** |
| AR/EN key parity | **998 / 998** (missing 0) |
| Forbidden-ref (Wishlist / Coupons / Reviews / settlement ledger; no COD `collected` mutation path) | **PASS** — collected only rejected; no Wishlist/Coupons/Reviews/settlement in CAN surfaces |
| Smoke: vendor cancel VO + customer cancel Parent → statuses + stock coherent | **PASS** — VO/Parent/`Payment`→`cancelled`; stock restored; leftovers **0** |

**Verification (CAN-D):** Task **DONE**. OPEN-010 V1 closed by CAN; Phase 7/9 notes synced. No Wishlist/Coupons/Reviews/post-ship returns/COD collected/Parent auto-derivation engine.

**Stop after CAN-D.** (Completed.) Do not start Wishlist, Coupons, Reviews, or post-ship returns.

---

## Hard boundaries (every slice)

- No cancel after `shipped`/`delivered`.  
- No COD `collected` handling.  
- No Wishlist, Coupons, Reviews, ratings, card charge, Redis, FULLTEXT, settlement ledger.  
- No public SKU or exact inventory quantity on storefront catalog surfaces.  
- Vendor/Parent authorization remains fail-closed.  
- Money remains integer minor units; public money serializes as decimal strings.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only CAN-A from @docs/tasks/order-cancellation-can.md. Stop after focused cancellation domain tests.
```

```text
Implement only CAN-B from @docs/tasks/order-cancellation-can.md. Stop after focused vendor cancel UI tests.
```

```text
Implement only CAN-C from @docs/tasks/order-cancellation-can.md. Stop after focused customer cancel UI tests.
```

```text
Implement only CAN-D from @docs/tasks/order-cancellation-can.md. Stop after the final acceptance report.
```
