# Syrian Multi-Vendor E-Commerce Marketplace

University V1 marketplace (Laravel modular monolith). Authoritative planning lives in [`docs/`](docs/). This README is the **evaluator runbook** for local/Docker demo and handoff (Phase 11).

**V1 status:** Cart → Checkout (COD) → Vendor Order lifecycle → Cancellations → Wishlist → Reviews → Coupons → Admin ops KPIs are implemented. Future work (card charge, settlement ledger, SMS, etc.) stays out of V1.

## Requirements

- PHP 8.3+ (8.4 OK)
- Composer 2
- Node.js 20+ (Vite assets)
- Docker + Docker Compose (**recommended**), **or** Laragon MySQL for local PHP

## Quick start (Docker Compose)

```bash
cp .env.example .env
# Optional: set SUPER_ADMIN_EMAIL + SUPER_ADMIN_PASSWORD before seeding staff

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install && docker compose exec app npm run build
docker compose exec app php artisan demo:seed
```

| Service | URL / note |
|---------|------------|
| App | http://localhost:8081 |
| Health | http://localhost:8081/up and http://localhost:8081/health |
| Mailpit UI | http://localhost:8025 |
| Queue worker | Compose service `queue` runs `php artisan queue:work` |

Host port defaults avoid common collisions (`APP_PORT` 8081, MySQL publish 3308, Redis publish 6380). Inside Compose, MySQL is `3306` and Redis `6379`. Redis in Compose is infrastructure for the default queue connection in `.env.example` — **not** an application product feature (no Redis cache/session productization in V1).

Check containers: `docker compose ps` (app, nginx, mysql, redis, queue, mailpit).

## Quick start (Laragon / local PHP)

```bash
cp .env.example .env
# Point DB_* at local MySQL.
# For simple local demos you may set QUEUE_CONNECTION=database (or sync) and skip Redis.
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan demo:seed
php artisan serve
# If QUEUE_CONNECTION=database, also run: php artisan queue:work
```

App: http://127.0.0.1:8000 (or your Laragon vhost). Mail: configure `MAIL_*` (Mailpit SMTP `1025` if you run Mailpit locally).

## Platform seed vs demo seed

| Command | What it loads | Notes |
|---------|---------------|--------|
| `php artisan migrate --seed` | Lean platform: roles, currencies, Syria geo, commission settings, optional Super Admin from env | **No** demo catalog/orders |
| `php artisan demo:seed` | Full V1 walkthrough dataset (vendors, products, coupons, sample orders) | Local/staging **only** |

`demo:seed` **refuses** `APP_ENV=production`. Re-running is safe: upserts by stable demo emails/slugs/codes; skips already-seeded demo order markers.

Optional staff account: set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env`, then `php artisan db:seed` (or include them before first `migrate --seed`). Demo seed does **not** create an admin user.

### Demo accounts (verified against `demo:seed`)

| Role | Email | Password |
|------|-------|----------|
| Customer | `customer@demo.test` | `password` |
| Vendor (SYP — Demo Silk House) | `vendor.syp@demo.test` | `password` |
| Vendor (USD — Demo Olive Mart) | `vendor.usd@demo.test` | `password` |
| Vendor (SYP 2 — Demo Cedar Crafts) | `vendor.syp2@demo.test` | `password` |

**Coupons:** `SAVE10` (platform 10% SYP), `SHOPSYP10` (vendor SYP store 10%).

**Sample catalog:** Demo Linen Scarf (`demo-linen-scarf`, SYP), Demo Cotton Tee (`demo-cotton-tee`, USD), Demo Cedar Bowl (`demo-cedar-bowl`, SYP).

**Pre-seeded orders (idempotent markers):** delivered SYP scarf (review-ready); pending multi-vendor Parent; confirmed USD tee VO.

## End-to-end V1 walkthrough

1. Open the storefront (AR default RTL; switch to EN as needed).
2. Browse Home / Search / Category → open **Demo Linen Scarf** → add to cart.
3. Checkout as `customer@demo.test` → apply `SAVE10` → place **COD**.
4. As `vendor.syp@demo.test` → Vendor → Orders → confirm → ship → deliver.
5. As customer → submit a product review on the delivered purchase; staff (Super Admin) can moderate under Admin → Product reviews.
6. As customer, open account orders: cancel the **pending multi-vendor** Parent while all VOs are pending, **or** as `vendor.usd@demo.test` advance the **confirmed** cotton-tee VO.
7. As staff: Admin → Overview KPIs; Parent orders / Payments read-only; Settings shows global commission.

Manual UAT checklist for the final Phase 11 gate: [`docs/uat-phase-11.md`](docs/uat-phase-11.md).

## Auth & locale (V1)

- Register with **email + phone** (unique); login with **email**
- Password: minimum **8** characters, confirmed
- Email verification supported; **does not block** login/dashboard/checkout
- Roles: `customer`, `vendor`, `admin`, `super_admin` (Customer+Vendor dual-role supported)
- Default locale Arabic (`ar`) RTL; English LTR complete; cookie + `preferred_locale`

## Queue & mail

- Compose: `queue` worker is required for queued notifications (order placement, VO status, vendor application, etc.).
- Mailpit (Compose): UI http://localhost:8025 — password reset and notification mail appear here when `MAIL_*` points at Mailpit SMTP.
- Laragon: run `php artisan queue:work` when not using `sync`.

## Tests

Focused (host / Laragon, in-memory SQLite via `phpunit.xml`):

```bash
php artisan test
# or
vendor/bin/phpunit
```

Full regression as used at phase gates (inside Compose app container):

```bash
docker compose exec app php artisan test
# or
docker compose exec app vendor/bin/phpunit
```

HND-C (Phase 11 final) runs the full Docker suite once; do not treat a green focused filter as the full gate.

## Compose smoke (bootstrap)

1. `docker compose ps` — services up  
2. `GET /up` → 200  
3. `GET /health` → JSON `status: ok`  
4. Locale switch AR/EN → `dir` flips  
5. Password reset mail in Mailpit (optional)

## Security checklist

See [`docs/security-checklist.md`](docs/security-checklist.md) for the thin V1 evaluation checklist (authz, money, SKU/inventory, uploads, env secrets, `demo:seed` production refuse).

## Documentation map

| Doc | Use |
|-----|-----|
| [`docs/development-plan.md`](docs/development-plan.md) | Phase roadmap |
| [`docs/decisions.md`](docs/decisions.md) | ADRs / OPEN closures |
| [`docs/business-rules.md`](docs/business-rules.md) | Business rules |
| [`docs/tasks/`](docs/tasks/) | Slice execution tasks |
| [`docs/security-checklist.md`](docs/security-checklist.md) | V1 security checklist |
| [`docs/indexes-query-note.md`](docs/indexes-query-note.md) | S8C index/EXPLAIN posture (no speculative migrations) |
| [`docs/uat-phase-11.md`](docs/uat-phase-11.md) | Manual UAT for Phase 11 gate |

Indexes / query posture: see [`docs/indexes-query-note.md`](docs/indexes-query-note.md) (S8C EXPLAIN; no speculative Phase 11 index migrations).
