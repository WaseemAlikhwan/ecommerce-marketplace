# Phase 11 Manual UAT Script (HND-C)

Execute at the **HND-C** gate after Docker/Laragon is up, `migrate --seed`, and `demo:seed`. Check each box; leave **leftovers 0** (no temp scripts, no abandoned carts required beyond the walkthrough).

**Prereq:** Demo accounts from README (`customer@demo.test` / vendors / password `password`). Staff: Super Admin from `.env` seed, or create an admin for KPI glance.

## A. Health

- [ ] `GET /up` → 200
- [ ] `GET /health` → JSON includes `"status":"ok"`
- [ ] (Docker) `docker compose ps` shows app, nginx, mysql, queue, mailpit up

## B. Storefront & checkout

- [ ] Home loads; locale switch AR ↔ EN flips `dir`
- [ ] Open **Demo Linen Scarf** (`/p/demo-linen-scarf` or via browse)
- [ ] Add to cart as `customer@demo.test`
- [ ] Checkout: apply `SAVE10` → discount visible → place **COD** successfully
- [ ] No public SKU / exact stock quantity shown on cart/checkout/PDP summary

## C. Vendor fulfillment & review

- [ ] Login `vendor.syp@demo.test` → confirm → ship → deliver the new VO (or the seeded delivered path already allows review)
- [ ] As customer, submit a product review on an eligible delivered purchase
- [ ] (Optional) Staff approve review; PDP shows approved review only

## D. Cancel / advance (seeded paths)

- [ ] Customer can cancel the seeded **pending multi-vendor** Parent while all VOs are pending, **or**
- [ ] `vendor.usd@demo.test` can advance the seeded **confirmed** cotton-tee VO toward shipped/delivered

## E. Staff admin KPI glance

- [ ] Admin Overview shows frozen KPIs (pending apps/reviews, placed parents, VO/payment status counts, published products, approved vendors, recognized commission)
- [ ] Open one Parent order show (nested VOs + payments); no SKU leak
- [ ] Open one Payment show; COD status visible; **no** “mark collected” action
- [ ] Settings shows read-only global commission

## F. Coupons (spot-check)

- [ ] Staff Admin → Coupons lists `SAVE10` / `SHOPSYP10` (or create is staff-only)
- [ ] Second distinct code on checkout fails closed until first removed

## G. Sign-off

- [ ] No leftover temp files from this UAT
- [ ] Notes / blockers (if any):

---

_HND-A draft — execute and mark results during HND-C._
