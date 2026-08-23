# Architecture

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Status:** Draft for stakeholder review  
**Style:** Pragmatic Laravel layered architecture (not a full DDD microservices system)

---

## 1. Goals

- Keep controllers thin.
- Keep business rules testable and centralized.
- Enforce strict authorization boundaries.
- Preserve order/inventory integrity with transactions.
- Remain simple enough for a university project while reflecting production practices.
- Avoid unnecessary packages and patterns.

---

## 2. High-Level Context

```text
[Browser]
    |  HTTPS
[Nginx]
    |  FastCGI / PHP-FPM
[Laravel App]
    |-- MySQL (primary persistence)
    |-- Redis (cache, queues, optional sessions)
    |-- File storage disk (local now; S3-compatible later)
```

Docker Compose orchestrates app, Nginx, MySQL, Redis, and queue worker processes for development/demo environments.

---

## 3. Recommended Application Shape

Use a **modular monolith** inside one Laravel application:

| Area | Responsibility |
|------|----------------|
| Storefront (Blade) | Customer browsing, cart, checkout, account |
| Vendor panel (Blade) | Application status, store, catalog, vendor orders |
| Admin panel (Blade) | Platform management |
| Shared domain services | Checkout, inventory, commissions, coupons, payments |

Do **not** split into multiple deployable services for V1.

### 3.1 Suggested directory emphasis (conceptual)

```text
app/
  Http/
    Controllers/        # thin
    Requests/           # Form Requests
    Middleware/
    Resources/          # only if API added later; V1 is Blade-first
  Models/               # Eloquent entities
  Policies/
  Services/             # application/business use-case services
  Actions/              # optional single-purpose write actions (if helpful)
  Domain/               # optional small value objects/enums (OrderStatus, Money)
  Payments/             # payment contracts + COD driver
  Shipping/             # shipping calculator contract + V1 rule implementation
  Listeners/
  Notifications/
  Support/
```

**Guidance:** Prefer `Services/` + Form Requests + Policies. Introduce `Actions/` or `Domain/` types only when they reduce complexity.

---

## 4. Layering

### 4.1 Presentation layer

- Blade views + Tailwind CSS and small Alpine components.
- Controllers orchestrate HTTP only: authorize → validate → call service → return view/redirect.
- Locale middleware per ADR-021 (`ar` / `en`; RTL for Arabic).
- No business writes, Eloquent queries, View Composers, Models, or framework Collections embedded in public Storefront Blade payloads.

### 4.2 Application / business logic

Examples of services (names illustrative):

| Service | Responsibility |
|---------|----------------|
| `VendorApplicationService` | submit/review/approve/reject |
| `StoreService` | store profile lifecycle |
| `ProductService` | product/variant draft writes + published integrity guards |
| `ProductVariantMatrixService` | variable matrix sync + published integrity guards |
| `ProductImageService` | product gallery writes; last-image remove guarded when Published |
| `ProductReadinessService` | read-only publication readiness evaluation (ADR-039) |
| `ProductPublicationService` | vendor publish/unpublish transitions (ADR-039) |
| `CartService` | cart mutations |
| `CheckoutService` | transactional order placement |
| `InventoryService` | stock checks/decrements/restores |
| `CommissionService` | resolve effective rate + snapshot |
| `CouponService` | validate/apply/redeem |
| `OrderStatusService` | legal transitions + events |
| `ReviewService` | eligibility + uniqueness |
| `StorefrontProductQuery` | MySQL storefront catalog browse/detail (ADR-040; S8A). Legacy plan name: SearchService |
| `StorefrontBrowseService` | sanitized HTTP browse orchestration + presented pagination (ADR-040; S8B) |
| `StorefrontFilterOptionsService` | represented browse-only filter dictionaries; batched Attribute labels (ADR-040; S8C) |
| `StorefrontNavigationService` | represented navigable root Categories only; no full filter dictionary (ADR-040; S8C) |
| `StorefrontHomeService` | bounded newest cards, eligible Store ordering, and root navigation without a paginator (ADR-040; S8C) |

Services coordinate Eloquent models and DB transactions. Controllers do not contain checkout algorithms.

### 4.3 Domain concepts (lightweight)

Useful explicit concepts without heavy DDD ceremony:

- Parent Order / Vendor Order / Order Item
- Money (amount_minor + currency_code; exponents per ADR-033)
- ExchangeRateSnapshot
- CommissionSnapshot
- Order/VendorOrder status enums
- PaymentMethod / PaymentStatus
- VendorApplicationStatus (`pending` | `approved` | `rejected`)
- Vendor/Store suspension status (separate from application)
- ProductStatus (`draft` | `published` | `unpublished` | `suspended` | `archived`)

### 4.4 Persistence / data access

- Eloquent models + relationships.
- Migrations with foreign keys, unique constraints, and indexes.
- Soft deletes for products and variants (ADR-036). Soft-delete for other entities may remain separately scoped.
- Query scopes for storefront constraints: `Product::published()` and `Product::storefrontVisible()` (**S7B implemented**, ADR-028/039). S8B wires all public Product reads through these scopes and routes Category/Store reads through `storefrontNavigable()` / `publiclyEligible()`.
- Avoid raw repository interfaces unless a clear testing/seam need appears.

---

## 5. Core Domain Model (Logical)

```text
User
 ├── roles/permissions (Customer, Vendor, Admin, Super Admin as distinct roles; Customer+Vendor allowed on same user — ADR-017/018)
 ├── preferred_locale (synced with cookie — ADR-021)
 ├── Customer profile/addresses/wishlist/reviews
 ├── VendorApplication (0..n over time; statuses pending|approved|rejected — ADR-014)
 └── VendorProfile (may be suspended post-approval — ADR-014)
      └── Store (exactly one per vendor — ADR-015)
           ├── default_currency_code (ADR-033)
           └── Products (store_id only — ADR-022)
                ├── currency_code (ADR-033)
                ├── default_variant_id → product_variants (same product — ADR-037)
                ├── status: draft|published|unpublished|suspended|archived (ADR-027)
                ├── type: simple|variable (immutable after create — ADR-037)
                ├── canonical slug (ADR-026)
                ├── category_id → leaf category (ADR-023)
                ├── brand_id? (ADR-024)
                ├── Translations (name, short/full description; no slug — ADR-025)
                ├── primary_image_id → product_images (same product — ADR-038)
                ├── Images (ordered gallery; normalized JPEG/PNG/WebP masters; AR/EN alt translations — ADR-034/038)
                ├── product_attributes / product_attribute_values (SoftDeletes — ADR-037)
                └── Variants (always ≥1 — ADR-029)
                     ├── store_id (DB-consistent with product — ADR-031)
                     ├── sku (unique per store)
                     ├── combination_key (default \| a{id}:v{id}|… — ADR-037)
                     ├── price_amount_minor / compare_at_amount_minor?
                     ├── quantity (authoritative stock — ADR-032)
                     └── product_variant_attribute_values (immutable historical links — ADR-030/037)

ParentOrder
 ├── currency / exchange rate snapshots
 ├── customer address snapshot
 └── VendorOrders[]
      ├── vendor_id
      ├── shipping amount/status
      ├── commission snapshot
      ├── payment linkage (OPEN DECISION granularity)
      └── OrderItems[]
           ├── variant_id (only sellable unit — ADR-029)
           ├── snapshots (name, sku, price, currency, store/seller — ADR-036)
           └── quantity
```

### 5.1 Translations

```text
products
product_translations (product_id, locale, name, short_description?, description?)
categories / category_translations (name, description?)
brands / brand_translations (name, description?)
attributes / attribute_translations (name)
attribute_values / attribute_value_translations (name)
product_images / product_image_translations (optional alt_text per ar/en; cascade on image delete)
product_attributes / product_attribute_values (product assignment + selected values; SoftDeletes)
product_variant_attribute_values (variant combination links; no SoftDeletes)
```

Canonical slugs live on `products`, `categories`, `brands` — **not** on translation tables (ADR-026).

Do **not** use `name_ar` / `name_en` columns.

### 5.2 Inventory

- Authoritative stock: `product_variants.quantity` (unsigned; no negative stock; no backorders — ADR-032).
- No separate `inventories` table or stock-movement ledger in Catalog.
- Checkout **decrements** variant quantity inside the successful place-order transaction with `lockForUpdate` (ADR-042 / OPEN-021 closed).

### 5.3 Orders

- Parent Order = customer checkout aggregate.
- Vendor Order = seller fulfillment unit + shipping + vendor authorization boundary.
- Order Item = purchased **variant** line with **price/name/SKU/currency/store snapshots**.

### 5.4 Catalog integrity constraints (planned)

- `products (id, store_id)` uniquely identifiable for composite FK.
- `product_variants (product_id, store_id)` → `products (id, store_id)`.
- Unique `(store_id, sku)` on variants (including soft-deleted reservation policy — ADR-031/036).
- Unique canonical slugs per entity table.
- Categories: adjacency `parent_id`; max depth 3; product FK to leaf only (enforced in validation).
- `php artisan storage:link` is required (idempotent) so `/storage/...` is served from `storage/app/public`. Do not commit the symlink or uploaded files.

---

## 6. Authentication

- Laravel session authentication for Blade panels.
- Password hashing via Laravel framework defaults.
- Password policy: minimum 8 characters, confirmed (ADR-020).
- Password reset via email.
- Registration requires unique email + unique phone; login with email (ADR-016).
- Email verification supported; required before vendor application; not required for customer checkout (ADR-020).
- Guests may browse and use a guest cart; checkout requires authentication (ADR-019).
- Separate route prefixes/middleware groups:
  - `/` storefront
  - `/vendor` vendor panel (requires Vendor capability + non-suspended vendor)
  - `/admin` admin panel

### 6.1 Locale resolution (ADR-021)

- Locale middleware resolves `ar` / `en` (RTL for Arabic).
- First visit: `Accept-Language` if `ar`/`en`, else default `ar`.
- Explicit choice: cookie; authenticated users also persist `preferred_locale` on the user, synced with cookie.
- Session may cache resolved locale for the request; cookie/profile are the durable sources of truth.

---

## 7. Authorization

### 7.1 Approach (V1)

Native Laravel:

- Roles on users (or role tables owned by the app), including distinct `super_admin` and `admin` (ADR-018).
- Admin granular permissions table (`permission`, `admin_user_permission`) if needed; Super Admin bypasses granular checks.
- Same user may have Customer and Vendor capabilities (ADR-017).
- Policies for resources: `ProductPolicy`, `StorePolicy`, `VendorOrderPolicy`, `OrderPolicy`, etc.
- Gates for admin capability checks (`approve-vendors`, `manage-commissions`, …).

**Do not select Spatie/other authz package in this phase.** Re-evaluate later only if permission UX complexity justifies it. Exact Admin permission catalog remains OPEN (BR-PERM-07).

### 7.2 Isolation rules

| Actor | Boundary |
|-------|----------|
| Guest | Browse/search; guest cart; no checkout; no private resources |
| Customer | own orders, wishlist, addresses, reviews |
| Vendor | own store (exactly one), products, vendor orders, vendor coupons; may also be Customer on same account |
| Admin | global, filtered by permissions |
| Super Admin | all (`super_admin` role) |

Ownership checks must be in policies/services, not hidden in UI links alone.

---

## 8. Validation

- Form Request classes for all write endpoints.
- Prefer explicit validation rules aligned with business rules.
- Re-validate critical invariants in services (stock, coupon limits, status transitions) because HTTP validation is not enough under concurrency.

---

## 9. Events & Notifications

### 9.1 Domain/application events (examples)

- `VendorApplicationSubmitted`
- `VendorApplicationApproved` / `Rejected`
- `OrderPlaced`
- `VendorOrderConfirmed`
- `VendorOrderShipped`
- `VendorOrderDelivered`
- `VendorOrderCancelled`

### 9.2 Notifications

- Laravel `Notification` classes.
- V1 channel set is **OPEN DECISION** (database and/or mail) — OPEN-013 / BR-NTF-04.
- Onboarding currently sends queued **mail** for: new application (staff), approved, rejected. This does not close the channel decision.
- Design constructors around Notifiable users + payload IDs.
- Queue notifications.

---

## 10. Queues

- Redis queue driver.
- Worker container/process in Docker Compose.
- Use queues for: notifications, image post-processing (future), report generation (future).
- Keep checkout synchronous and transactional; do not put stock decrement solely in async jobs.

---

## 11. Caching

Redis cache candidates:

- category tree
- active FX rates
- global commission config
- storefront fragments (carefully invalidated)

Do not cache authorization decisions in a way that risks stale privilege grants without invalidation.

---

## 12. Storage

- Laravel Filesystem disks.
- V1: `local` or `public` disk inside Docker volume.
- Store paths in DB; never hard-code absolute host paths.
- Future: switch disk to S3-compatible without model redesign.
- Future optimization: thumbnail derivations via queued jobs.

---

## 13. Payments Architecture

```text
PaymentGateway (interface)
  ├── charge(orderContext): PaymentResult
  ├── markCollected(...)   # useful for COD
  └── refund(...)          # future-safe no-op/not implemented in V1 COD

CodPaymentGateway implements PaymentGateway
```

- Checkout depends on interface, not COD concretions.
- Payment records store method, status, amounts, and FK to **Vendor Order** (ADR-042 / OPEN-011 closed). COD statuses: `pending`, `collected`, `cancelled`.
- Adding Stripe/local gateway later = new implementation + config, not order rewrite.

---

## 14. Shipping Architecture

```text
ShippingCalculator (interface)
  └── calculate(VendorOrderDraft, Address): Money

FlatPerVendorShippingCalculator implements ShippingCalculator
  # configurable store flat fee + platform default (ADR-042)
```

- Persist shipping amount on Vendor Order.
- Keep Parent Order able to sum vendor shipping totals for customer receipt (per currency when mixed).
- V1 rule is **configurable flat fee per Vendor Order** — not hard-coded constants; geo tariff tables are a later calculator implementation.

---

## 15. Commissions

- Config entities: `commission_settings` (global) + `vendor_commission_overrides`.
- `CommissionService::resolve(vendor)` → rate.
- Snapshot rate + amount onto Vendor Order at placement; base = item subtotal excluding shipping (ADR-042).
- Recognition for reporting when Vendor Order is `delivered` (no wallet ledger in V1).
- Future subscription plans can add another resolver branch without changing order schema dramatically.

---

## 16. Coupons

- Coupon entity with scope (`platform`|`vendor`), type, constraints, limits.
- Redemptions table for usage accounting.
- Apply in `CheckoutService` after grouping items by vendor.
- Keep V1 stacking rules minimal once OPEN DECISIONS are approved.

---

## 17. Search / Storefront Catalog Query

- **S8A foundation (ADR-040):** `CatalogCriteria` / `CatalogCriteriaResult` + `StorefrontProductQuery` compose browse/detail SQL from `Product::storefrontVisible()` with escaped `LIKE` keyword search, category/brand/store/currency/price/availability/attribute filters, and newest/name/price sorts. Listing cards use SQL aggregates (min/max price, in-stock, Simple compare-at) rather than loading every Variant. Presenters are query-free.
- **S8B public cutover:** Thin Storefront controllers use `StorefrontBrowseService`, `StorefrontFilterOptionsService`, and query-free page presenters. All five public routes now render presentation arrays from persisted visible data; path-implied Category/Store criteria are removed from links. Product detail uses a server-payload-only Alpine selector with a bounded no-JavaScript fallback.
- **S8C orchestration:** Home and PDP use focused `StorefrontHomeService` / `StorefrontNavigationService`; only browse routes load represented filter dictionaries. Attribute criteria resolution and option labels are batched. Criteria presentation uses semantic effective state, and page input is capped at 50.
- **S8C presentation:** Sparse Variable selection remains server-payload-only; gallery hero identity is `primary_image_id`; responsive dialogs and pagination are accessible in RTL/LTR; SEO metadata is bounded and canonicalized according to ADR-040.
- Public layout/navigation and commerce components contain no fixture catalog, Rating, Cart, Wishlist, public SKU, or quantity behavior.
- A 120-Product disposable MySQL plan dataset measured Home/Search/Category/Store/Product at 7/13/18/14/20 queries. Home orchestration dropped from a 13-query full-browse/full-filter equivalent to 7; PDP dropped from 26 with full filters to 20 with root navigation.
- EXPLAIN showed indexed PK/FK access through Store/Vendor/taxonomy/Attribute/PDP paths. No S8C index migration was justified: small-table scans were cost-based, existing Product/Variant indexes cover bounded fan-out, and no production-cardinality or slow-query evidence demonstrated a net benefit.
- No external search engine, FULLTEXT, Redis catalog cache, or eager Variant listing payload is used in V1.
- Legacy name `SearchService` in earlier plans maps to this Storefront query foundation.

---

## 18. Multi-Currency

- Catalog: store default currency + product `currency_code`; variant amounts in minor units of that currency (ADR-033).
- **Cart (ADR-041):** mixed-currency carts allowed; per-currency subtotals; no FX.
- **Checkout (ADR-042):** mixed-currency placement allowed **without** conversion; Parent shows per-currency COD dues; each Vendor Order + its COD Payment is single-currency.
- Reference rate table remains available for a **later** phase if conversion is ever introduced; not required for V1 placement.
- Display layer may later convert for browsing using current rates; orders always show historical snapshots when conversion exists.

---

## 19. Database

- MySQL 8.x recommended.
- InnoDB, FK constraints enabled.
- Charset/collation supporting Arabic (`utf8mb4`).
- Important unique constraints examples:
  - review uniqueness (per approved rule)
  - wishlist uniqueness
  - coupon code uniqueness (scoped as designed)
  - variant SKU uniqueness **per store** with composite product/store FK (ADR-031)
  - canonical entity slugs unique per table (ADR-026)

---

## 20. Docker Infrastructure

Suggested Compose services:

| Service | Role |
|---------|------|
| `app` | PHP-FPM Laravel |
| `nginx` | web server |
| `mysql` | database |
| `redis` | cache/queue |
| `queue` | `php artisan queue:work` |
| `mailpit` (optional) | local email testing |

Secrets via `.env` (not committed). Volumes for MySQL data and uploaded media.

---

## 21. Testing Architecture

| Layer | What to test |
|-------|----------------|
| Unit | commission resolution, coupon math, shipping calculator, money/FX snapshot helpers |
| Feature | checkout transaction, inventory concurrency (where feasible), authz policies, vendor isolation, catalog ownership/SKU/visibility |
| Browser (optional later) | critical Blade flows |

Prefer testing services and policies over bloated controller tests.

---

## 22. Security Architecture Notes

- CSRF on web forms.
- Mass-assignment protection.
- Policy checks before updates.
- Rate limiting on login/application submit.
- Upload validation (MIME/size).
- Avoid leaking other vendors’ IDs through insecure direct object references.

---

## 23. What We Explicitly Avoid in V1

- Microservices
- CQRS/Event Sourcing
- Elasticsearch
- Multi-tenancy DB-per-vendor
- Premature package lock-in for permissions/payments
- Repository boilerplate around every Eloquent model
- Fixed bilingual columns
- Locale-specific slugs without locale-prefixed routes
- Nested-set/closure-table category packages
- Hard-delete of products/variants via normal UI

---

## 24. Mapping Requirements → Architecture

| Requirement theme | Architecture element |
|-------------------|----------------------|
| Multi-vendor checkout | CheckoutService + Parent/Vendor/Item model |
| Vendor isolation | Policies + scoped queries |
| Configurable commission | settings + snapshots |
| COD + future gateways | PaymentGateway interface |
| Shipping per vendor | VendorOrder + ShippingCalculator |
| Variant inventory | `product_variants.quantity` + locks/transactions at checkout |
| Always-variant sellable unit | cart/order_items → `variant_id` |
| i18n scalable | translation tables + lang files; canonical slugs |
| MySQL storefront catalog query | `StorefrontProductQuery` + `CatalogCriteria` (ADR-040; S8A) |
| Notifications extensible | Notification + queued channels |
| Admin granular perms | permissions + gates (catalog TBD BR-PERM-07) |

---

## 25. Open Architecture Points (Need Product Decisions)

P0 auth/identity/locale items are closed (ADR-014 … ADR-021). Catalog schema-shaping items are closed (ADR-022 … ADR-036). Cart C1 closed (ADR-041). Checkout V1 contract closed (ADR-042). Remaining:

1. Whether to introduce Spatie Permission later (not now)  
2. Exact admin permission catalog (BR-PERM-07)  
3. Soft-delete scope for non-catalog entities (users, etc.)  
4. Coupon stacking (OPEN-007); review gates (OPEN-008/009); cancellation matrix (OPEN-010)  
5. Notification channels beyond mail + database (OPEN-013 remainder)  
6. Wishlist target (OPEN-018); in-flight suspend policy (OPEN-017); admin KPI set (OPEN-020)  
7. Operational COD collector / shipper narratives (BR-PAY-05 / BR-SHP-06)  
