# Short Task Workflow

Large implementation prompts are stored here once and executed as small slices.

## Active tasks

| File | Status |
|------|--------|
| `cart-c1.md` | DONE — Cart C1 accepted (2026-08-23); focused 42/437; full Docker 369/2791 |
| `checkout-readiness.md` | READY — CHK-0 approved 2026-08-23 |
| `checkout-chk.md` | **DONE** — CHK-0…CHK-E accepted (2026-08-24); focused **24 / 252**; full Docker **393 / 3044** |
| `vendor-order-lifecycle-vol.md` | **DONE** — VOL-A…C accepted (2026-08-24); focused **10 / 104**; AR/EN **990 / 990**; smoke leftovers **0** |
| `order-cancellation-can.md` | **DONE** — CAN-A…D accepted (2026-08-24); focused **14 / 143**; AR/EN **998 / 998**; smoke leftovers **0** |
| `wishlist-wsh.md` | **DONE** — WSH-A…C accepted (2026-08-24); focused **17 / 136**; AR/EN **1003 / 1003**; smoke leftovers **0** |
| `reviews-rev.md` | **DONE** — REV-A…D accepted (2026-08-25); focused **20 / 257**; AR/EN **1035 / 1035**; smoke leftovers **0** |
| `coupons-cpn.md` | **DONE** — CPN-A…C accepted (2026-08-25); focused **19 / 140**; AR/EN **1091 / 1091**; smoke leftovers **0** |
| `admin-ops-adm.md` | **DONE** — ADM-A…D accepted (2026-08-26); focused **12 / 146**; AR/EN **1125 / 1125**; smoke leftovers **0** |
| `phase-11-hardening-hnd.md` | **DONE** — HND-A…C accepted (2026-08-26); focused HND **8 / 31**; full Docker **493 / 3999**; AR/EN **1141 / 1141**; UAT **25 / 0**; leftovers **0** |
| `customer-address-book-addr.md` | **DONE** — ADDR-A…B accepted (2026-08-29); focused **8 / 48**; checkout inline **1 / 9**; AR/EN **1152 / 1152**; stale placeholders **0** |
| `cod-collected-ops-col.md` | **DONE** — COL-A…B accepted (2026-08-29); focused **8 / 44**; AR/EN **1155 / 1155**; gate leftovers **0** |
| `professional-polish-pro.md` | READY — PRO-A/B/C done (2026-08-30); next **PRO-D** |
| `storefront-s8c-recovery.md` | DONE — S8C accepted |

## Rules

1. Keep permanent decisions in the existing ADR, architecture, business-rule, and development-plan documents.
2. Keep only temporary execution details in `docs/tasks/`.
3. Split work so one slice has one primary outcome and its focused tests.
4. Do not defer tests for a slice to a later slice.
5. Run the full Docker suite and browser matrix only in the final gate slice.
6. Mark interrupted work `IN PROGRESS`; never document it as complete.
7. Archive or delete a task file after its final gate is accepted.
8. Do not implement Checkout until `checkout-chk.md` is READY and a named CHK-* slice is requested.
9. Do not start Wishlist from Checkout, VOL, or CAN tasks.
10. Do not implement Vendor Order lifecycle until `vendor-order-lifecycle-vol.md` is READY and a named VOL-* slice is requested.
11. Do not implement cancellations from VOL. Implement cancellations only from `order-cancellation-can.md` when READY and a named CAN-* slice is requested.
12. Do not start Wishlist from Cart, Checkout, VOL, or CAN. Implement Wishlist only from `wishlist-wsh.md` when READY and a named WSH-* slice is requested.
13. Do not start Reviews from Cart, Checkout, VOL, CAN, or Wishlist. Implement Reviews only from `reviews-rev.md` when READY and a named REV-* slice is requested.
14. Do not start Coupons from Cart, Checkout, VOL, CAN, Wishlist, or Reviews. Implement Coupons only from `coupons-cpn.md` when READY and a named CPN-* slice is requested.
15. Do not start Admin Ops / Phase 10 dashboard from Coupons, Reviews, or earlier commerce tasks. Implement Admin Ops only from `admin-ops-adm.md` when READY and a named ADM-* slice is requested.
16. Do not start Phase 11 Hardening / Handoff from Admin Ops or earlier commerce tasks. Implement Phase 11 only from `phase-11-hardening-hnd.md` when READY and a named HND-* slice is requested. Do not rebuild `DemoMarketplaceSeeder` — document/verify only.
17. Do not start Customer Address Book from unrelated tasks. Implement only from `customer-address-book-addr.md` when READY and a named ADDR-* slice is requested. No checkout/order changes beyond shared address validation extraction.
18. Do not start COD Collected Ops from unrelated tasks. Implement only from `cod-collected-ops-col.md` when READY and a named COL-* slice is requested. No card charge, settlement ledger, SMS, admin cancel, or auto-collect on deliver.
19. Do not start Professional Polish from unrelated tasks. Implement only from `professional-polish-pro.md` when READY and a named PRO-* slice is requested. No F1/F2/F7, checkout/coupon/shipping changes, or new notification types beyond PRO-C scope.

## Short Prompt Template

```text
Implement only <SLICE> from @docs/tasks/<task>.md.
Read its referenced ADR/rules and inspect the current code.
Run the focused checks listed for this slice.
Do not start the next slice, commit, or push.
Return changed files, test counts, blockers, and the next slice name.
```
