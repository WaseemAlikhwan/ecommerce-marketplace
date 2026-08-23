# Checkout Decision & Implementation Readiness Audit

**Status:** READY — CHK-0 **approved** 2026-08-23 (documentation only; no Checkout application code in CHK-0)  
**Authority:** ADR-001…007, ADR-011, ADR-012, ADR-019, ADR-029, ADR-032, ADR-033, ADR-041, **ADR-042**; BR-CHK/VO/SHP/COM/PAY/CUR/INV/GEO; UC-C04; Phase 7 plan  
**Baseline:** Cart C1 accepted (focused **42 / 437**; full Docker PHPUnit **369 / 2791**); cart CTA intentionally disabled until CHK-D  
**Execution:** [`checkout-chk.md`](./checkout-chk.md) is **READY**; **CHK-A DONE** (schema/domain). Next: CHK-B when requested.

---

## Approved V1 contract (CHK-0)

| ID | Decision | Status |
|----|----------|--------|
| **R1** | Mixed-currency **place without FX**; Parent shows **per-currency COD dues**; each VO single-currency | **APPROVED** → ADR-042 |
| **R2** | **Decrement** stock in checkout transaction (`lockForUpdate`) | **APPROVED** → ADR-042 |
| **R3** | **Configurable flat** shipping fee per Vendor Order (not hard-coded) | **APPROVED** → ADR-042 |
| **R4** | One COD **Payment per Vendor Order** | **APPROVED** → ADR-042 |
| **R5** | One **Parent** shipping address snapshot (copied to VOs) | **APPROVED** → ADR-042 |
| **R6** | Public codes **`PO-…` / `VO-…`** | **APPROVED** → ADR-042 |
| **R7** | COD statuses **`pending` \| `collected` \| `cancelled`** | **APPROVED** → ADR-042 |
| **R8** | Commission base = **item subtotal excluding shipping** | **APPROVED** → ADR-042 |
| **R9** | Commission **snapshot at placement**; **recognize at VO delivered** | **APPROVED** → ADR-042 |
| **R10** | Seed **governorates+cities**; **Syria-only**; **no area** level in V1 | **APPROVED** → ADR-042 |
| **R11** | Notifications minimum: **mail + database** | **APPROVED** → ADR-042 (OPEN-013 remainder for SMS/etc.) |

**First-slice exclusions (APPROVED):** no Wishlist, Coupons, Reviews, card gateways, Redis, settlement ledger.

---

## Closed vs remaining OPEN (post CHK-0)

### Closed by ADR-042
OPEN-005, OPEN-006, OPEN-011, OPEN-012, OPEN-021; BR-CHK-06/07, BR-INV-03, BR-SHP-04, BR-PAY-03/04, BR-COM-05/06, BR-CUR-04, BR-GEO-05; OPEN-013 narrowed to mail+DB for Checkout V1.

### Still OPEN (do not invent; not needed to start CHK-A)
OPEN-007 coupons · OPEN-008/009 reviews · OPEN-010 cancellation · OPEN-013 SMS/full channels · OPEN-017 suspend in-flight · OPEN-018 Wishlist · OPEN-020 admin KPIs · BR-PAY-05 / BR-SHP-06 operational · BR-CUR-08 display preference · BR-GEO-03 area · BR-VO-04/05 full status matrices.

---

## Code prerequisites (unchanged — for CHK-A+)

Missing until implementation: orders/payments/addresses/geo/commission tables & services; Checkout/Shipping/Payment/Inventory application services; checkout routes; cart CTA still disabled.

Reusable: Cart C1 services, variant stock, storefront visibility, auth/roles, money helpers, locale/RTL, queued mail.

---

## Sync completed in CHK-0

| Doc | Update |
|-----|--------|
| `docs/decisions.md` | **ADR-042** Accepted; OPEN-005/006/011/012/021 closed; OPEN-013 partially closed |
| `docs/business-rules.md` | Matching BRs → RULE / FUTURE as approved |
| `docs/architecture.md` | Payment per VO; flat shipping calculator; decrement; mixed place without FX; commission recognition |
| `docs/requirements.md` | FR + ambiguity log synced to ADR-042 |
| `docs/use-cases.md` | UC-C04 first-slice flow (no coupons; decrement; per-VO COD; mail+DB) |
| `docs/development-plan.md` | Phase 7 dependencies = ADR-042; CHK task READY |
| `docs/tasks/checkout-chk.md` | Status READY; decisions table frozen |
| `docs/tasks/README.md` | Task index updated |

---

**STOP for this file.** Implementation begins only when a named CHK-A…E slice is requested.
