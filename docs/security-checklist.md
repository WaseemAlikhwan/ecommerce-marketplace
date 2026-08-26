# V1 Security Checklist (evaluation)

Thin checklist for demo/defense. Not a penetration-test report. No Super Admin permission-matrix UI (BR-PERM-07 remains out of V1).

## Environment & secrets

- [ ] `.env` is not committed; use `.env.example` as the template
- [ ] `APP_KEY` is set; `APP_DEBUG` is off outside local debugging
- [ ] `APP_ENV=production` is never used with `demo:seed` (command refuses production)
- [ ] `SUPER_ADMIN_PASSWORD` (if set) is strong and not shared in public write-ups
- [ ] Demo passwords (`password` on `*@demo.test`) are for **local/staging only**

## Auth & authorization

- [ ] Guests hitting staff/vendor panels redirect to login
- [ ] Non-staff cannot open `/admin/*` (403)
- [ ] Non-vendors cannot open `/vendor/*` (403)
- [ ] Customer order / wishlist / review mutations fail-closed for strangers (404 where required)
- [ ] Login is rate-limited (Laravel login throttle: 5 attempts)
- [ ] Password reset / register / locale POSTs use thin `throttle` middleware (no Redis rate-limit productization)
- [ ] CSRF protection on web forms (Laravel defaults)

## Money & catalog privacy

- [ ] Money stored as integer **minor units**; public/admin UI shows decimal **strings**
- [ ] Storefront and admin order summaries do **not** expose public **SKU**
- [ ] Storefront / wishlist / admin ops do **not** expose **exact inventory quantity**
- [ ] Commission historical figures use snapshotted VO amounts when recognized (BR-RPT-03)

## Uploads

- [ ] Vendor product images: jpg/jpeg/png/webp only
- [ ] Max upload size enforced at **5120 KB (5 MB)** per image (`StoreProductImageRequest`)
- [ ] Gallery capped (max 8 images per product)

## Indexes

- [ ] No speculative index migrations in Phase 11 — see [`indexes-query-note.md`](indexes-query-note.md)

## Operations boundaries (V1)

- [ ] Admin orders/payments are **read-only** (no admin cancel, no COD “mark collected”)
- [ ] No card/wallet charge; no settlement/refund ledger UI
- [ ] No Redis application productization (cache/session as a product feature)
- [ ] Coupons are staff-managed; no vendor self-serve coupon admin in V1

## Health

- [ ] `GET /up` returns 200 when the app is up
- [ ] `GET /health` returns JSON `status: ok`
