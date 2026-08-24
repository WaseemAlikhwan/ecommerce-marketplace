# Vendor Order Lifecycle VOL — Post-COD Status Transitions

**Status:** DONE (2026-08-24 — VOL-A…C accepted; planning freeze retained for reference)  
**Authority:** ADR-001, ADR-002, ADR-006, ADR-012, **ADR-042**; BR-VO / BR-CHK / BR-COM / BR-PAY / BR-INV / BR-CAN / BR-NTF; Phase 7 remainder + Phase 9 boundary  
**Baseline:** Checkout CHK DONE (ADR-042); VO rows exist at `pending` after COD placement; customer Parent Order + vendor Vendor Order **read** UIs exist  
**Related OPEN:** OPEN-010 cancellation matrix (**PENDING** — recommended rule below, not implemented here); OPEN-017 vendor suspend in-flight (**out of this task**)

Implement only the named slice when asked. Do **not** start Wishlist, Coupons, Reviews, card charge, Redis, settlement ledgers, or full Phase 9 cancellation/refund productization unless a later approved slice says so.

## Planning freeze (approved for VOL implementation)

| Topic | Decision for VOL V1 | Notes |
|-------|---------------------|--------|
| Minimal VO status set | `pending` → `confirmed` → `shipped` → `delivered` (+ terminal `cancelled` reserved) | Drop **`processing`** from V1 allowed transitions (enum value may remain unused until a later slice). Matches BR-VO-03/NTF needs without extra hops. |
| Who advances fulfillment | **Vendor only** (own VO, fail-closed policy) | Customer and guest cannot mutate VO status in VOL. Admin panel transitions are **out of VOL**. |
| Allowed vendor transitions | `pending→confirmed`, `confirmed→shipped`, `shipped→delivered` | Forward-only; no skip; no reopen from `delivered`. |
| Placement starting status | Unchanged: checkout leaves VO at **`pending`** (ADR-042) | VOL starts after placement. |
| COD Payment status | **Out of VOL** except read-only display | `pending\|collected\|cancelled` remains ADR-042; marking COD `collected` is a later ops slice (may coincide with `delivered` later — not required here). |
| Commission recognition | On transition **into `delivered`**, set recognition timestamp if not already set (ADR-042 / BR-COM-06) | No wallet/settlement ledger. |
| Parent Order status | Parent stays **`placed`** for VOL | BR-VO-05 Parent derivation **deferred** (no auto `delivered`/`partial` on Parent in VOL). |
| Customer visibility | Customer sees VO status labels on existing Parent Order show/index | No customer transition buttons in VOL. |
| Notifications | Mail + database on `confirmed`, `shipped`, `delivered` (BR-NTF-01) | Same channel floor as ADR-042. |
| Cancellations | **PENDING** — OPEN-010 not closed; no cancel/stock-restore code in VOL | Recommended rule recorded below for a future Phase 9 / CAN slice. |

### Recommended simple cancellation rule (OPEN-010 — **PENDING**, not implemented in VOL)

> **Until a later slice closes OPEN-010:**  
> 1. **Customer:** may cancel a **Parent Order** only while **every** Vendor Order is still `pending` (all-or-nothing). No independent VO cancel by customer in V1.  
> 2. **Vendor:** may cancel **own** VO only while status is `pending` or `confirmed` (before `shipped`). Sibling VOs unaffected.  
> 3. On cancel: restore stock (BR-CAN-04 / BR-INV-06); set COD Payment to `cancelled` / do-not-collect (BR-REF-02 direction); no coupon logic (coupons not in V1 checkout).  
> 4. After `shipped` or `delivered`: no cancel in V1 (returns/refunds FUTURE).

Do **not** implement cancellations, stock restore, or Payment→cancelled in VOL-A…C.

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **VOL-A** | Domain transition service + policy guards + commission recognition on `delivered` + notifications | This freeze | **DONE** |
| **VOL-B** | Vendor panel actions on own VO show/index (confirm / ship / deliver) | VOL-A | **DONE** |
| **VOL-C** | Customer Parent Order visibility of VO statuses + AR/EN + focused gate | VOL-A, VOL-B | **DONE** |

---

## VOL-A — Domain transitions

**Status:** DONE (2026-08-24)

**Goal:** Authoritative, fail-closed Vendor Order status transitions after COD placement.

**In scope**

1. Transactional `VendorOrderLifecycleService` (name flexible) enforcing the allowed graph only.  
2. Policy: vendor can update only own store’s VO; customer/admin paths do not advance fulfillment here.  
3. On `delivered`: commission recognition timestamp per ADR-042.  
4. Notifications: customer + vendor (as appropriate) on confirmed / shipped / delivered (mail + database).  
5. Focused feature/unit tests for happy path, illegal skips/regressions, and vendor isolation.

**Out of scope:** Blade UI, cancellations, COD `collected`, Parent status derivation, Wishlist/Coupons/Reviews.

**Done when:** Focused lifecycle tests green; no HTTP UI required yet (optional thin route only if tests need it — prefer service-level tests).

**Verification (VOL-A):** Focused `VendorOrderLifecycleVolATest` **4 / 32**. Service locks VO, enforces vendor-only forward graph, sets `commission_recognized_at` once on `delivered`, leaves Parent `placed` and COD Payment untouched, sends mail+database notifications. No Blade / cancel / VOL-B.

**Stop after VOL-A.** (Completed.)

---

## VOL-B — Vendor UI

**Status:** DONE (2026-08-24)

**Goal:** Vendor can advance their Vendor Orders from the existing vendor order UI.

**In scope**

1. Actions on vendor VO show (and compact affordances on index if low-risk): Confirm / Mark shipped / Mark delivered, enabled only for legal transitions.  
2. Form Requests + policy reuse; flash/errors localized.  
3. Focused HTTP tests: owner success, non-owner 404 fail-closed, illegal transition rejected.

**Out of scope:** Customer mutations, admin UI, cancellations, payment collection UI.

**Done when:** Focused vendor UI tests green.

**Verification (VOL-B):** Focused `VendorOrderLifecycleVolBTest` **4 / 37**. Show-only primary CTA for legal next status via `VendorOrderLifecycleService::nextStatus` + `advance` policy; `POST vendor.orders.advance` Form Request; owner success with localized flash; stranger vendor 404; illegal/cancelled targets rejected; customer blocked by vendor middleware (403). No index POST forms, no customer mutations, no VOL-C.

**Stop after VOL-B.** (Completed.)

---

## VOL-C — Customer visibility + acceptance gate

**Status:** DONE (2026-08-24)

**Goal:** Customer Parent Order views show live VO statuses; VOL passes a small gate.

**In scope**

1. Customer Parent Order show/index reflect confirmed/shipped/delivered labels (query-free presenters).  
2. AR/EN key parity for new copy.  
3. Gate: focused VOL tests; Pint (VOL-scoped); `view:cache`; AR/EN parity; forbidden-ref (no Wishlist/Coupons/Reviews/cancel engine); brief smoke vendor advances VO → customer sees status; leftovers 0.  
4. Mark this task DONE with exact counts; sync Phase 7 note / ADR-042 “lifecycle” implemented note if needed.

**Out of scope:** Full Docker suite only if project gate for this slice requires it — prefer focused + smoke unless repo convention demands full suite (match Checkout CHK-E lightly: focused + parity + smoke; full suite optional if time-boxed). Prefer **focused VOL + smoke**; do not expand into Phase 9.

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (VOL-C / VOL final)

| Check | Result |
|-------|--------|
| Focused VOL tests (A+B+C) | **PASS** — **10 / 104** (`VolA` 4/32, `VolB` 4/37, `VolC` 2/35) |
| Pint (VOL-scoped, `--test`) | **PASS** — 12 files |
| `php artisan view:cache` | **PASS** |
| AR/EN key parity | **PASS** — **990 / 990** |
| Forbidden-ref (Wishlist/Coupons/Reviews/cancel engine) | **PASS** — clean on VOL-touched paths |
| Smoke: vendor advances VO → customer Parent show/index | **PASS** — live Confirmed/Shipped/Delivered labels; no customer transition controls; leftovers **0** |

**Verification (VOL-C):** `OrderViewService::parentIndexRows` exposes query-free `vendor_statuses`; account Parent show/index render shipment labels only (no advance forms). Focused `VendorOrderLifecycleVolCTest` **2 / 35**. Task **DONE**.

**Stop after VOL-C.** (Completed.) Do not start OPEN-010 cancellations, Wishlist, Coupons, or Reviews.

---

## Hard boundaries (every slice)

- No cancellation/stock-restore/Payment→cancelled implementation (OPEN-010 PENDING).  
- No Wishlist, Coupons, Reviews, ratings, card charge, Redis, FULLTEXT, settlement ledger.  
- No public SKU or exact inventory quantity on storefront catalog surfaces.  
- Vendor Order authorization remains fail-closed.  
- Money remains integer minor units; public money serializes as decimal strings.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only VOL-A from @docs/tasks/vendor-order-lifecycle-vol.md. Stop after focused lifecycle domain tests.
```

```text
Implement only VOL-B from @docs/tasks/vendor-order-lifecycle-vol.md. Stop after focused vendor VO UI tests.
```

```text
Implement only VOL-C from @docs/tasks/vendor-order-lifecycle-vol.md. Stop after the final acceptance report.
```
