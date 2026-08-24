# Short Task Workflow

Large implementation prompts are stored here once and executed as small slices.

## Active tasks

| File | Status |
|------|--------|
| `cart-c1.md` | DONE — Cart C1 accepted (2026-08-23); focused 42/437; full Docker 369/2791 |
| `checkout-readiness.md` | READY — CHK-0 approved 2026-08-23 |
| `checkout-chk.md` | **DONE** — CHK-0…CHK-E accepted (2026-08-24); focused **24 / 252**; full Docker **393 / 3044** |
| `vendor-order-lifecycle-vol.md` | **DONE** — VOL-A…C accepted (2026-08-24); focused **10 / 104**; AR/EN **990 / 990**; smoke leftovers **0** |
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
9. Do not start Wishlist from Checkout or VOL tasks.
10. Do not implement Vendor Order lifecycle until `vendor-order-lifecycle-vol.md` is READY and a named VOL-* slice is requested.
11. Do not implement cancellations from VOL (OPEN-010 remains PENDING).

## Short Prompt Template

```text
Implement only <SLICE> from @docs/tasks/<task>.md.
Read its referenced ADR/rules and inspect the current code.
Run the focused checks listed for this slice.
Do not start the next slice, commit, or push.
Return changed files, test counts, blockers, and the next slice name.
```
