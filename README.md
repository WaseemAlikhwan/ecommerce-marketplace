# Syrian Multi-Vendor E-Commerce Marketplace

University project using production-oriented Laravel practices. Planning docs live in [`docs/`](docs/).

## Phase 1 status

Bootstrap complete: Laravel + Docker Compose + auth foundation (email + phone), password policy, email verification (non-blocking for customers), roles table including `super_admin`, and Arabic/English locale middleware (ADR-021).

## Requirements

- PHP 8.3+
- Composer 2
- Node.js 20+ (Vite assets)
- Docker + Docker Compose (recommended), **or** Laragon MySQL/Redis for local PHP

## Quick start (Docker)

```bash
cp .env.example .env
# Set APP_KEY after containers are up, or generate locally first:
# php artisan key:generate

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install && docker compose exec app npm run build
```

App: http://localhost:8081  
Health: http://localhost:8081/health and http://localhost:8081/up  
Mailpit UI: http://localhost:8025  

Host port defaults avoid a common local collision with other stacks (`8080`/`6379`). Inside Compose, MySQL remains `3306` and Redis remains `6379`.  

Optional Super Admin seed: set `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD` in `.env`, then re-run `php artisan db:seed`.

## Quick start (Laragon / local PHP)

```bash
cp .env.example .env
# Point DB_* at local MySQL; REDIS_* / QUEUE / CACHE / SESSION can stay file/database/array for simple local use
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

## Auth rules (P0)

- Register with **email + phone** (both unique); login with **email**
- Password: minimum **8** characters, confirmed
- Email verification supported; **does not block** login/dashboard/checkout later
- Vendor application (Phase 4) will require verified email
- Roles: `customer`, `vendor`, `admin`, `super_admin` (multi-role ready for Customer+Vendor)

## Locale (P0-8)

- Default: Arabic (`ar`) with RTL
- Guest: cookie + first-visit `Accept-Language`
- Authenticated: `users.preferred_locale` synced with cookie
- Switcher in guest and app layouts

## Tests

```bash
php artisan test
```

Uses in-memory SQLite (`phpunit.xml`).

## Compose smoke checklist

1. `docker compose ps` — app, nginx, mysql, redis, queue, mailpit healthy/up  
2. `GET /up` → 200  
3. `GET /health` → JSON `status: ok`  
4. Register user with phone → logged in → dashboard (unverified banner OK)  
5. Switch locale AR/EN → `dir` flips RTL/LTR  
6. Password reset email appears in Mailpit  

## Documentation

See `docs/development-plan.md` Phase 1. Do not implement Phase 2+ until planned.
