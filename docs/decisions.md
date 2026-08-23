# Architecture & Product Decisions

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Status:** Living decision log (documentation phase)

This document records important decisions, rationale, rejected alternatives, and items awaiting human approval.

Format keys:
- **ADR** — Architecture Decision Record (accepted for planning)
- **OPEN** — Requires human approval before implementation
- **REJECTED** — Explicitly not chosen for V1

---

## ADR-001 — Multi-vendor checkout uses Parent Order → Vendor Orders → Items

**Status:** Accepted  

**Context:** Customers may buy from multiple vendors in one checkout. Vendors must fulfill independently and must not see other vendors’ data. Shipping fees differ per vendor.

**Decision:** Model a Parent Order as the customer-facing checkout aggregate, containing one Vendor Order per vendor, each with its own items, shipping, and status.

**Rationale:**
- Matches real fulfillment boundaries.
- Natural authorization boundary (`vendor_id` on Vendor Order).
- Allows per-vendor shipping and commission snapshots.
- Preserves a single customer receipt/checkout experience.

**Rejected alternatives:**
- Single flat order with vendor_id on items only — weak fulfillment/shipping status modeling.
- Multiple independent checkouts forced on customer — poor UX.

---

## ADR-002 — Shipping belongs to Vendor Orders

**Status:** Accepted  

**Context:** Different vendors may charge different shipping fees and ship separately.

**Decision:** Associate shipping amount/status/rules evaluation with Vendor Orders. Parent Order may display the sum.

**Rationale:** Shipping is a fulfillment concern, not only a cart total concern. Evolving shipping rules later should not require restructuring Parent Order as the sole shipping owner.

**Implications:** ShippingCalculator interface receives vendor-scoped drafts.

---

## ADR-003 — Inventory is authoritative at variant level

**Status:** Accepted  

**Context:** Products like apparel need Color/Size combinations with distinct SKU, price, and stock.

**Decision:** When variants exist, stock is tracked on variants. Checkout locks and decrements variant inventory inside a transaction.

**Rationale:** Prevents ambiguous “product stock” when sellable units differ. Supports oversell protection per purchasable unit.

**Related confirmation:** V1 uses an **always-variant** model; base product stock is unused (ADR-029).

---

## ADR-004 — Translations use separate translation tables (not `*_ar` / `*_en` columns)

**Status:** Accepted  

**Context:** V1 needs Arabic and English; more languages may be added later.

**Decision:** Store translatable content in translation tables keyed by `(entity_id, locale)`.

**Rationale:**
- Adding a language does not require schema changes per field.
- Avoids wide tables and null-heavy bilingual columns.
- Aligns with scalable i18n practice.

**Rejected alternatives:**
- `name_ar`, `name_en` columns — brittle and explicitly forbidden by requirements.
- JSON columns per locale blob only — possible later optimization, but normalized tables are clearer for V1 querying/indexing.

**Also:** UI strings use Laravel language files; catalog/store content uses DB translations.

---

## ADR-005 — Payment handling is abstracted; V1 implements COD only

**Status:** Accepted  

**Context:** V1 requires Cash on Delivery, but gateways may be added later.

**Decision:** Introduce a `PaymentGateway` (or equivalent) contract. COD is the first implementation. Checkout depends on the contract.

**Rationale:** Prevents rewriting order placement when adding card/wallet providers. Refund methods can exist on the contract as future-safe operations.

**Rejected alternatives:**
- Hard-coding COD fields/conditionals throughout order code.
- Integrating a real gateway in V1 (out of scope).

---

## ADR-006 — Commission is configurable with optional vendor override + order snapshot

**Status:** Accepted  

**Context:** Platform takes commission; percentage must not be hard-coded; vendors may need special rates.

**Decision:** Persist global commission settings and vendor-specific overrides. Resolve effective rate at checkout and snapshot it on the Vendor Order (or commission line).

**Rationale:** Historical orders remain correct when configuration changes. Supports future subscription plans as an additional commercial dimension without replacing commission snapshots.

**Out of scope:** Subscription billing implementation in V1.

---

## ADR-007 — MySQL is sufficient for V1 search

**Status:** Accepted  

**Context:** Product search needs keyword + filters + sorting. Dedicated search engines add ops complexity.

**Decision:** Implement search/filter with MySQL (Query Builder/Eloquent), proper indexes, and optional FULLTEXT if needed.

**Rationale:** Appropriate for university/V1 scale; satisfies requirement to avoid Elasticsearch et al. Can revisit if performance/relevance becomes inadequate.

---

## ADR-008 — Native Laravel authorization first (no third-party authz package yet)

**Status:** Accepted (for V1 planning)  

**Context:** Need Super Admin, Admin (granular), Vendor, Customer isolation. Package choice was deferred by requirement.

**Decision:** Use Laravel Policies, Gates, middleware, and application-owned role/permission tables as needed.

**Rationale:** Documents requirements before package lock-in; keeps dependency surface small; sufficient for V1 if permission catalog stays moderate.

**Revisit trigger:** If admin permission management becomes large/complex, evaluate Spatie Permission or similar later.

---

## ADR-009 — Business logic lives in application services; controllers stay thin

**Status:** Accepted  

**Context:** Checkout, inventory, coupons, and commissions are multi-step and must be testable.

**Decision:** Encapsulate use cases in services (and optional single-purpose actions). Controllers authorize, validate, delegate, respond.

**Rationale:** Separation of concerns, DRY, testability, prevents Blade/controller business logic sprawl — without mandating heavy DDD ceremony.

---

## ADR-010 — Modular monolith on Laravel + Docker Compose

**Status:** Accepted  

**Context:** One product with storefront, vendor panel, admin panel.

**Decision:** Single Laravel codebase (modular monolith) with Docker Compose services: app, nginx, mysql, redis, queue worker.

**Rationale:** Simplest architecture that solves the problem; aligns with stack requirements; avoids premature microservices.

---

## ADR-011 — Exchange rates used in orders are snapshotted

**Status:** Accepted  

**Context:** SYP and USD supported; rates change over time.

**Decision:** Persist the rate and converted amounts used at order time. Admin-managed rates for V1 (no external provider).

**Rationale:** Historical integrity for receipts, commissions, and reports.

**Related OPEN:** How mixed-currency carts convert at checkout.

---

## ADR-012 — Events + queued notifications with extensible channels

**Status:** Accepted  

**Context:** Customers/vendors/admins need lifecycle notifications; channels may grow (email/SMS).

**Decision:** Emit application events; send Laravel Notifications; queue delivery; keep channel list configurable.

**Rationale:** Decouples domain flow from delivery mechanisms.

---

## ADR-013 — Media via filesystem disks; DB stores paths

**Status:** Accepted  

**Context:** Multi-image products; store logo/banner; future optimization/CDN.

**Decision:** Use Laravel storage disks; persist relative paths/metadata; design for later derived thumbnails.

---

## ADR-014 — Vendor application vs suspension state model (P0-1)

**Status:** Accepted  

**Decision:** Vendor **applications** use statuses `pending`, `approved`, `rejected` only. Post-approval **`suspended`** is modeled on the **vendor account** and **store**, not on the application record.

**Closes:** OPEN-003; aligns FR-VND-02 / BR-APP-02 after correction.

**Source:** `docs/p0-decisions.md` P0-1 option B.

---

## ADR-015 — One store per vendor in V1 (P0-2)

**Status:** Accepted  

**Decision:** Each approved vendor owns exactly one store (`stores.vendor_id` unique). Multi-store is out of V1.

**Closes:** OPEN-001.

**Source:** `docs/p0-decisions.md` P0-2 option A.

---

## ADR-016 — User identity: email + phone (P0-3)

**Status:** Accepted  

**Decision:** Registration requires unique email and unique phone. Login uses email + password. Password reset via email. No phone OTP/SMS verification in V1.

**Closes:** OPEN-016; BR-CUS-03/04.

**Source:** `docs/p0-decisions.md` P0-3 option C.

---

## ADR-017 — Customer and Vendor on the same account (P0-4)

**Status:** Accepted  

**Decision:** After approval, the same user may hold Customer and Vendor capabilities simultaneously. Staff Admin/Super Admin accounts should remain separate from marketplace buyer/seller accounts in V1 (convention).

**Closes:** OPEN-002.

**Source:** `docs/p0-decisions.md` P0-4 option A.

---

## ADR-018 — Distinct Super Admin role (P0-5)

**Status:** Accepted  

**Decision:** Super Admin is a distinct role (`super_admin`) with full access. Admins use role `admin` plus granular permissions. Super Admin bypasses granular permission checks. Only Super Admin assigns Admin role/permissions.

**Closes:** BR-PERM-09 open question.

**Source:** `docs/p0-decisions.md` P0-5 option A.

---

## ADR-019 — Guest cart; authenticated checkout (P0-6)

**Status:** Accepted  

**Decision:** Guests may browse/search and use a guest cart. Checkout requires login/register. No full guest checkout in V1. Cart persistence and login-merge details are settled in **ADR-041** (closes former BR-CART-04/05).

**Closes:** OPEN-014 (guest vs authenticated checkout policy).

**Source:** `docs/p0-decisions.md` P0-6 option C.

---

## ADR-020 — Password policy and email verification (P0-7)

**Status:** Accepted  

**Decision:** Password minimum 8 characters, must be confirmed; no mandatory complexity class. Email verification is supported; unverified users may use the storefront; **vendor application** requires verified email.

**Closes:** Architecture “email verification OPEN”; audit M-01/M-19 for Phase 1.

**Source:** `docs/p0-decisions.md` P0-7 options P-A + V-B.

---

## ADR-021 — Locale persistence hybrid (P0-8)

**Status:** Accepted  

**Decision:** First visit may use `Accept-Language` if `ar`/`en`, else default Arabic. Explicit selection stored in a cookie. Authenticated users also store preferred locale on the user profile, kept in sync with the cookie. Session is not the source of truth. Missing-translation presentation fallback is closed by ADR-040 / BR-TR-04 (requested → English → Arabic → stable canonical).

**Source:** `docs/p0-decisions.md` P0-8 option E.

---

## ADR-022 — Product ownership via `store_id` only (CAT-P1-01)

**Status:** Accepted  

**Decision:** Every product belongs to exactly one store via `products.store_id`. Do **not** duplicate `vendor_id` on products. Vendor ownership is derived through `store.vendor_id` (one store per vendor — ADR-015).

**Rationale:** Avoids denormalized ownership drift. Policies resolve vendor isolation via the store relationship.

**Source:** Catalog Decision Audit CAT-P1-01 (approved 2026-08-12).

---

## ADR-023 — Category hierarchy and leaf assignment (CAT-P1-02, CAT-P1-03)

**Status:** Accepted  

**Decision:**
- Categories use an adjacency-list tree (`parent_id`), maximum **three** levels: Root → Subcategory → Leaf.
- No nested-set or closure-table packages.
- Each product is assigned to **exactly one leaf** category in V1 (`products.category_id`).
- Products may omit category while `draft`; publication requires a leaf category.

**Source:** Catalog Decision Audit CAT-P1-02/03 (approved with hierarchy override 2026-08-12).

---

## ADR-024 — Admin-managed categories and brands (CAT-P1-04, OPEN-015)

**Status:** Accepted  

**Decision:** Categories and brands are created, updated, and deactivated by Admin/Super Admin only. Vendors may only **select** active categories and brands when managing products. Brands are global (no `vendor_id` on brands).

**Closes:** OPEN-015; BR-PRD-08.

**Source:** Catalog Decision Audit CAT-P1-04 (approved 2026-08-12).

---

## ADR-025 — Catalog translations without localized slugs (CAT-P1-05)

**Status:** Accepted  

**Decision:** Use translation tables (not `name_ar` / `name_en`) for:
- category: name, optional description
- brand: name, optional description
- product: name, optional short description, optional full description
- attribute: name
- attribute value: name

Slugs are **not** stored on translation tables (see ADR-026). Incomplete drafts are allowed. **Publication** requires Arabic and English **names**. Descriptions remain optional; any provided locale content uses translation tables.

**Source:** Catalog Decision Audit CAT-P1-05 (approved with overrides 2026-08-12).

---

## ADR-026 — Canonical non-localized slugs (CAT-P1-06)

**Status:** Accepted  

**Decision:** Store one stable canonical slug on the main entity:
- `products.slug`
- `categories.slug`
- `brands.slug`

Each slug is **globally unique within its entity table** and independent of UI locale. Current routes (`/p/{slug}`, `/c/{slug}`, …) have no `/ar` or `/en` prefix; locale-specific slugs are **rejected** for V1 because the same URL must not resolve to different entities based on cookie locale.

Slug generation may use the English name when available, or a stable unique fallback. Slugs must **not** auto-change when a translated name changes. Locale-prefixed SEO URLs are deferred until routing supports them.

**Source:** Catalog Decision Audit CAT-P1-06 (approved with override 2026-08-12).

---

## ADR-027 — Product lifecycle, vendor publish, admin suspension (CAT-P1-07, CAT-P1-08, OPEN-004)

**Status:** Accepted  

**Decision:** V1 product statuses:
- `draft` — vendor work in progress
- `published` — eligible for storefront (subject to store/vendor sellability)
- `unpublished` — vendor-hidden; not storefront-visible
- `suspended` — platform moderation block; vendor cannot republish
- `archived` — retired from normal vendor operations

Rules:
- Vendors may create drafts; publish valid own products; unpublish own products; archive own products.
- No mandatory admin approval queue before first publish.
- Admin/Super Admin may **suspend** a product (moderation). Store `suspended_at`, `suspended_by`, and optional suspension reason.
- Suspended products cannot be republished by the vendor. Only authorized staff may clear suspension; restore moves the product to **`unpublished`** (intentional publish required afterward).
- `archived` ≠ moderation suspension. Do not use archive as a substitute for `suspended`.

**Closes:** OPEN-004; BR-PRD-04.

**Source:** Catalog Decision Audit CAT-P1-07/08 (approved with overrides 2026-08-12).

---

## ADR-028 — Storefront visibility (CAT-P1-09)

**Status:** Accepted  

**Decision:** A product is **storefront-visible** only when all hold:
- product status is `published`
- product is not soft-deleted (even if the caller used `withTrashed()`)
- owning store status is sellable (`active`)
- owning vendor status is `approved`
- assigned category is active, currently a leaf, and all ancestors are active
- brand is null **or** active
- currency is active

S7B implements this as reusable Eloquent scopes `Product::published()` and `Product::storefrontVisible()` (SQL/query-based; no per-row `ProductReadinessService`). Scope semantics stay aligned with readiness `visibilityIssues`. Zero-stock Published products remain visible but later non-purchasable. Inactive historical Attribute/Attribute Values do **not** hide products.

**Purchasable** (for later cart/checkout) additionally requires available stock on the selected variant (`quantity` sufficient). Published out-of-stock products may remain visible but not purchasable. Vendor/store suspension hides catalog for **new** visibility/purchase without rewriting product rows. In-flight order handling on vendor suspend remains OPEN-017.

S8B wired these scopes into every public Storefront catalog route and removed the legacy fixture runtime. S8C keeps the same fail-closed scope as the single visibility boundary while hardening presentation, SEO, and query orchestration.

**Source:** Catalog Decision Audit CAT-P1-09 (approved 2026-08-12); clarified by S7B (2026-08-21).

---

## ADR-029 — Always-variant sellable unit (CAT-P1-10; confirms ADR-003)

**Status:** Accepted  

**Decision:** Every product has one or more `product_variants`. Simple products use a single **default** variant. SKU, price amounts, and stock live on the variant — **not** on the product. Future cart lines and order items reference **`variant_id` only** (no product-or-variant polymorphism).

**Confirms:** ADR-003 pending note — when variants exist (always in V1), base product stock is unused.  
**Closes:** BR-PRD-07.

**Source:** Catalog Decision Audit CAT-P1-10 (approved 2026-08-12).

---

## ADR-030 — Global attributes and unique variant combinations (CAT-P1-11, CAT-P1-12)

**Status:** Accepted  

**Decision:** Attributes and attribute values are **admin-global**. Vendors assign which attributes apply to a product and create combinations (e.g. Color × Size). Each variable-product variant must include exactly one value per assigned attribute; combinations are unique per product via a canonical `combination_key` (or equivalent unique constraint). Simple/default variants have no attribute value rows.

**Source:** Catalog Decision Audit CAT-P1-11/12 (approved 2026-08-12).

---

## ADR-031 — SKU uniqueness with DB-enforced store consistency (CAT-P1-13, OPEN-019)

**Status:** Accepted  

**Decision:**
- SKU is **required** on every product variant; **not** stored on product.
- Uniqueness scope: **per store** — unique `(store_id, sku)` on `product_variants`.
- SKU values are normalized (trimmed and case-normalized) consistently.
- Soft-deleted SKUs remain reserved in V1 (uniqueness includes soft-deleted rows).
- Denormalized `product_variants.store_id` is allowed **only** to support DB uniqueness, and **must** stay equal to `products.store_id` via database enforcement, e.g.:
  - unique/indexed `(id, store_id)` on `products`
  - composite foreign key from `product_variants (product_id, store_id)` → `products (id, store_id)`
  - unique `(store_id, sku)` on `product_variants`
- Application services alone are **not** sufficient for this integrity guarantee.

**Closes:** OPEN-019.

**Source:** Catalog Decision Audit CAT-P1-13 (approved with integrity override 2026-08-12).

---

## ADR-032 — Catalog inventory storage (CAT-P1-14, CAT-P1-16); negative stock closed (CAT-P1-15)

**Status:** Accepted  

**Decision:**
- Authoritative stock is `product_variants.quantity` (unsigned integer). No separate `inventories` table in Catalog V1.
- Inventory **movement/ledger** tables are **not** required in the Catalog phase (explicit deferral).
- **Negative stock is forbidden.** **Backorders are not allowed** in V1. (Closes documentation contradiction C-04 / FR-INV-02 vs BR-INV-05.)
- Checkout reserve-vs-decrement timing (C-05 / BR-INV-03) remains **OPEN** and is **deferred to the Checkout phase**. Catalog only stores on-hand quantity; do not implement reservation or decrement in Catalog.

**Closes:** BR-INV-07 as RULE (no backorders); aligns FR-INV-02 with forbid-negative.

**Source:** Catalog Decision Audit CAT-P1-14/15/16/17 (approved 2026-08-12).

---

## ADR-033 — Catalog money and product currency (CAT-P1-18, CAT-P1-19, CAT-P1-20)

**Status:** Accepted  

**Decision:**
- Monetary amounts use **unsigned integer minor units** (no floats). Currency seed includes exponent: SYP = 0, USD = 2.
- `stores.default_currency_code` (default SYP).
- `products.currency_code` inherits store default at creation; may be explicitly SYP or USD.
- Currency exists **once on the product**. Variants do **not** have a currency column; all variants of a product share the product currency.
- Authoritative prices: `product_variants.price_amount_minor` (required); optional `compare_at_amount_minor` (display strikethrough only; must be greater than price if set).
- Products do **not** store authoritative prices. Cards show a single price or min–max range from active variants.
- **Mixed-currency checkout remains OPEN** (OPEN-005 / BR-CUR-04 / BR-CUR-08). Do not resolve conversion/charge currency in Catalog.

**Source:** Catalog Decision Audit CAT-P1-18/19/20 (approved with currency override 2026-08-12).

---

## ADR-034 — Product images (CAT-P1-21)

**Status:** Accepted  

**Decision:** Products support multiple images with ordering and one primary image. Persist relative paths on the Laravel `public` disk (same pattern as store logo/banner). Optional `alt_text`. Vendor may manage images for own products; admin may remove images for moderation. Exact max count/MIME/size remain implementation defaults until BR-MED-03 is finalized (recommended starting defaults: up to 8 images, JPEG/PNG/WebP, size limits via validation). No variant-level images in V1.

**Source:** Catalog Decision Audit CAT-P1-21 (approved 2026-08-12).

---

## ADR-035 — Vendor and admin catalog authority (CAT-P1-22, CAT-P1-23)

**Status:** Accepted  

**Decision:**
- Approved, non-suspended vendors manage only their own store’s products, variants, images, stock, and prices; select (not create) categories/brands; publish/unpublish/archive per ADR-027.
- Staff may manage taxonomy; list all products; unpublish; **suspend**; archive; recategorize/rebrand; remove images. Staff do **not** create products on behalf of vendors in V1. Price/stock impersonation is out of V1 admin catalog scope. Granular admin permission catalog remains OPEN (BR-PERM-07); until then staff checks may use existing `isStaff()` patterns.

**Source:** Catalog Decision Audit CAT-P1-22/23 (approved 2026-08-12).

---

## ADR-036 — Soft delete, archive, and historical safety (CAT-P1-24)

**Status:** Accepted  

**Decision:**
- Normal Vendor/Admin UI must **not** hard-delete products or variants.
- Vendor removal action = **archive** (+ soft delete for technical safety).
- SoftDeletes on products and variants; soft-deleted SKUs remain reserved.
- Categories/brands that are referenced are **deactivated**, not hard-deleted (`restrict` FKs).
- Store deletion must **not** cascade-delete products.
- Future order items must **snapshot** product name, SKU, unit price (minor units), currency, and seller/store identity; keep `variant_id` reference where possible.
- Physical purge is a later controlled maintenance operation, not a normal Catalog feature.
- Product soft-delete set for Catalog is decided; soft-delete for other entities (e.g. users) may remain separately scoped.

**Source:** Catalog Decision Audit CAT-P1-24 (approved with overrides 2026-08-12).

---

## ADR-037 — Variable product domain (S4B1; CAT-P1-11/12 follow-through)

**Status:** Accepted  

**Decision (approved 2026-08-12, S4B audit with overrides):**

- **Schema:** `product_attributes`, `product_attribute_values` (both SoftDeletes; restore-in-place), and `product_variant_attribute_values` (immutable historical links, no SoftDeletes). Composite unique indexes + composite FKs enforce assignment/value/variant/product consistency. Unassigned values cannot be linked. At most one value per assigned attribute per variant. Completeness (“exactly one per assignment”) is a service invariant; publication checks remain S7.
- **Default variant:** `products.default_variant_id` is the sole source of truth. Composite FK `(default_variant_id, id)` → `product_variants(id, product_id)`. `is_default` is removed. `Product::defaultVariant()` is `BelongsTo` (withTrashed). Live products must not reference a soft-deleted default; archived products may. Migration preflight fails on anomalies (no silent repair).
- **Type:** Immutable after creation. Dedicated simple and variable creation paths. No Simple ↔ Variable conversion in V1.
- **Limits:** max 3 attributes / product; max 8 selected values / attribute; raw Cartesian max 48 (reject before write); max 48 live variants. Submitted matrix may be a subset (≥1 variant).
- **Combination key:** server-generated only. Sort by `attribute_id` ascending: `a{attribute_id}:v{attribute_value_id}|…`. Literal `default` reserved for simple products. Unique `(product_id, combination_key)` includes soft-deleted rows; reuse restores the same variant row. Never mutate a variant’s combination identity.
- **Lifecycle:** Structural matrix sync only while `status = draft` and `published_at` is null. After first publication, topology and new combinations are frozen; SKU/price/compare-at/quantity/default remain editable when status permits; a non-last live variant may be archived; an archived combination may be restored only if global attribute/values are active.
- **Inactive globals:** historical links remain; no new assignment or new/restored live combination; no cascade rewrite. S7 publication validation deferred.
- **Transactions:** lock product → recheck type/status → lock variants (withTrashed) → lock globals deterministically → sync → set `default_variant_id` → assert ≥1 live variant and live default → commit. Unique SKU/combination constraints are final race protection.

**Confirms:** ADR-029, ADR-030, ADR-031, ADR-036.  
**Vendor matrix UI** remains S4B2.

**Source:** S4B Variable Products Decision Audit, approved with overrides 2026-08-12.

---

## ADR-038 — Product image domain, normalized storage, Vendor HTTP (S5A)

**Status:** Accepted  

**Decision (approved 2026-08-16, S5 audit with overrides):**

- **Schema:** `product_images` (no SoftDeletes, no `variant_id`, no `is_primary`, no alt columns on the image row) with required `path`, `mime_type`, `size_bytes`, `width`, `height`, `position`; globally unique `path`; unique `(id, product_id)` and `(product_id, position)`; composite FK `(product_id, store_id)` → `products(id, store_id)` restrict. `product_image_translations` hold optional `alt_text` per `ar`/`en` (unique image+locale; cascade on physical image delete).
- **Primary:** `products.primary_image_id` is the sole source of truth. Composite FK `(primary_image_id, id)` → `product_images(id, product_id)` restrict. Nullable when the Product has no images. Any Product with images must have a valid same-Product primary; services assert this and do not silently repair corruption.
- **Limits:** max 8 images/product; one upload per request; max 5 MiB input and normalized output; JPEG/PNG/WebP only; min 400×400; max 6000px per side; max 16,000,000 pixels; no aspect-ratio rule. Client filenames/extensions are never trusted.
- **Storage:** Laravel `public` disk; relative path `products/{product_id}/{ulid}.{detected-ext}`. Store exactly one GD-normalized master (decode, JPEG EXIF orientation including mirrors, re-encode, strip EXIF/XMP/GPS). No thumbnails, resizing, CDN, or image packages. GD WebP is required (host verified; Docker `gd --with-webp`).
- **Alt fallback:** requested locale → English → Arabic → localized Product name.
- **HTTP:** Vendor nested upload / reorder / set-primary / alt update / remove. `ProductPolicy::update` only. Staff moderation deferred. Two-phase position rewrite for unique `(product_id, position)`. Vendor image row+file delete is allowed; Product/Variant hard-delete remains forbidden (ADR-036). Archive retains image rows and files. Physical purge is later maintenance.
- **S5A** is domain/storage/HTTP + `Storage::fake()` tests. **S5B** is the polished gallery UI. **S7** publication will require at least one image and a valid `primary_image_id`.

**Confirms:** ADR-013, ADR-034, ADR-035 (staff removal deferred), ADR-036.  
**Closes:** BR-MED-03.

**Source:** Catalog Slice S5 decision audit, approved with overrides 2026-08-16.

---

## ADR-039 — Product publication and readiness (S7A)

**Status:** Accepted  

**Decision (approved 2026-08-20, S7 audit with overrides):**

- **Architecture:** `ProductReadinessService` is the read-only evaluator. Immutable DTOs (`ReadinessIssue`, `ReadinessResult`) carry machine-readable codes, section, and optional metadata only — no localized strings. `ProductPublicationService` owns lifecycle transitions (`publish` / `unpublish`). Presentation/HTTP layers translate issue codes.
- **Result buckets:** `integrityIssues` (product-owned), `publicationDependencyIssues` (category leaf/active ancestry, brand, currency, vendor, store, first-publication globals), `visibilityIssues` (contextual hide signals for S7B), `publicationIssues = integrity + publication dependencies`, `isPublishable()`.
- **Publish gate:** AR + EN names; active leaf category with active ancestors; optional brand active; active currency; approved vendor; sellable store; ≥1 image + valid same-product primary; ≥1 live variant + live default; valid SKU/price/compare-at/quantity; Simple exact-one/`default` combination; Variable persisted-matrix invariants (subset of Cartesian allowed). Zero stock is allowed. Do not check physical storage-file existence. Slug uniqueness and immutable type remain existing domain/DB invariants, not readiness checklist items.
- **Inactive globals:** First-ever publication (`published_at === null`) requires active assigned attributes/values. After first publication, inactive historical globals do **not** automatically hide the product and do **not** block republish when topology is unchanged. New assignments and new/restored combinations remain blocked by existing matrix rules (ADR-037).
- **External deactivation** of Store/Vendor/Category/Brand/Currency after publish: status stays `published`; no bulk product rewrites; S7B visibility scopes will hide dynamically.
- **Transitions (vendor):** Draft→Published when ready; Published→Unpublished; Unpublished→Published when ready; Draft→Unpublished invalid; publish/unpublish idempotent no-ops on already Published/Unpublished; Suspended/Archived forbidden; Archive remains separate/terminal.
- **`published_at`:** First-ever publication timestamp only; never overwritten on republish; never cleared on unpublish; permanently freezes Variable topology via `allowsStructuralMatrixSync()`.
- **Published mutations:** Reject vendor mutations that break product-owned integrity; do not auto-unpublish; write + integrity re-check inside the same transaction. Guard integration: `ProductService` simple update, variable update (via matrix sync), direct matrix sync, `ProductImageService::remove`. Upload/reorder/alt/set-primary rely on existing gallery invariants. Unpublished products may become incomplete; republish revalidates fully.
- **Inactive taxonomy keep-on-edit:** Unchanged existing Category/Brand/Currency IDs may remain during unrelated edits even if later deactivated; assigning a different ID still requires an active valid selection.
- **HTTP:** `POST /vendor/products/{product}/publish|unpublish` via thin `ProductPublicationController`. `ProductPolicy::publish|unpublish` check ownership and eligible lifecycle only (include already Published/Unpublished for idempotence) and **explicitly reject staff** even when the same account also has Vendor + approved Vendor + owned Store. Readiness failures are validation errors (422), never 403.
- **Transactions:** Publish locks Product first, reloads, rechecks transition, evaluates readiness, sets Published, sets `published_at` only when null. Do not lock every taxonomy/global row for publish. Unpublish is a smaller Product-only transition. Vendor product **updates** lock Product first, then resolve Category/Brand/Currency against the locked row (keep-on-edit compares to locked current IDs, never a stale in-memory argument), then lock Store/Variant as needed.
- **Readiness issue codes (machine-readable):** `missing_translation_ar`, `missing_translation_en`, `missing_category`, `category_not_leaf` (publication dependency **and** visibility — non-leaf after children appear must hide Published products), `inactive_category`, `inactive_category_ancestor`, `inactive_brand`, `inactive_currency`, `vendor_not_approved`, `store_not_sellable`, `missing_product_image`, `missing_primary_image`, `invalid_primary_image`, `missing_live_variant`, `missing_default_variant`, `default_variant_not_live`, `invalid_simple_variant_count`, `invalid_simple_combination`, `invalid_simple_attributes`, `missing_variable_assignment`, `missing_assignment_values`, `incomplete_variant_combination`, `invalid_combination_key`, `soft_deleted_variant_attribute_value`, `orphan_variant_attribute_link`, `matrix_assignment_limit_exceeded`, `matrix_value_limit_exceeded`, `matrix_cartesian_limit_exceeded`, `matrix_variant_limit_exceeded`, `inactive_first_publication_attribute`, `inactive_first_publication_value`, `invalid_sku` (normalized trim+uppercase must match stored SKU), `invalid_price`, `invalid_compare_at_price`, `invalid_quantity`.
- **S7B Vendor UI:** `VendorProductReadinessState` presents readiness for the edit sidebar (grouped/deduped issues, anchors, authorized action routes, JSON payload). Alpine `vendorProductReadiness` coordinates dirty Product/Gallery state with Publish/Unpublish controls without recomputing business rules. Gallery upload/remove JSON includes authoritative readiness; reorder/alt/primary do not.
- **S7B visibility scopes:** `Product::published()` and `Product::storefrontVisible()` (ADR-028). S8B subsequently wired both into the public catalog routes.
- **Storefront Catalog status:** S8B replaced the legacy fixture and wired Home/browse/PDP to persisted visible products. S8C production hardening is complete (recovery S8C-R1/R2/R3, 2026-08-22).
- Admin moderation, events/queues/notifications/cache, and status-history tables remain out of S7.

**Confirms:** ADR-025, ADR-027, ADR-028, ADR-029, ADR-037, ADR-038.  

**Source:** Catalog Slice S7 decision audit, approved with overrides 2026-08-20; S7A hardening 2026-08-20; S7B 2026-08-21.

---

## ADR-040 — Real Storefront Catalog query & public presentation (S8A/S8B/S8C)

**Status:** Accepted decision — S8A/S8B/S8C implemented (S8C final gate accepted 2026-08-22)  

**Decision (approved 2026-08-22, S8 audit with mandatory overrides):**

### Slicing
- **S8A:** HTTP-independent catalog criteria, SQL eligibility scopes, composable `StorefrontProductQuery`, listing aggregates, unwired detail loader, query-free card/detail presenters, docs, and tests. It intentionally left public routes on the legacy fixture.
- **S8B (implemented 2026-08-22):** Atomic real-data cutover of `/`, `/search`, `/c/{slug}`, `/s/{slug}`, `/p/{slug}` and Storefront layout/navigation; the legacy fixture runtime was removed.
- **S8C (implemented 2026-08-22; final gate accepted via S8C-R1/R2/R3):** Production hardening includes focused query orchestration, represented filter dictionaries, sparse Variable selection, accessible responsive UI, gallery correctness, SEO metadata, query budgets, and MySQL EXPLAIN evidence. No index migration was added.

### S8B public cutover
- Thin invokable controllers resolve Product/Category/Store through their public eligibility scopes; missing or hidden entities return 404, never public-catalog 403.
- `StorefrontBrowseService` sanitizes page input, resolves criteria, paginates 24 cards, presents every Product before Blade, and builds canonical pagination parameters that omit path-implied Category/Store filters.
- `StorefrontFilterOptionsService` returns query-free presentation arrays for navigable Categories, active Brands, eligible Stores, represented Currencies, and represented active Attribute/Value options. Ratings and facet counts remain absent.
- Public Blade pages and commerce components consume presentation arrays only. Search/Category/Store support sanitized GET filters, chips, result counts, mobile filtering, and canonical pagination. PDP uses the query-free detail state, image dimensions, an Alpine Variable selector fed only by the server payload, and a maximum-48 no-JavaScript combination list.
- Public catalog UI contains no Cart/Wishlist purchase controls, Reviews/Ratings, conversion, caching, or checkout behavior.

### S8C production hardening
- `AsciiSlug` is the shared read-side validator for Laravel `alpha_dash:ascii` semantics, including `_`, consecutive separators, and leading/trailing `_`/`-`. Criteria inspection stays bounded at 3 Attributes × 8 Values and rejects hostile array shapes.
- Criteria flow is raw → normalized → effective. Rejected price bounds never appear as applied fields/chips/links; min > max drops both bounds; removing Currency removes price bounds and price sorting. Valid-looking unresolved entities remain visible with an alert and a fail-closed empty result.
- Public pages remain 24 cards/page and are capped at `MAX_PUBLIC_PAGE = 50`, bounding OFFSET work to 1,176 rows. Oversized numeric strings and `PHP_INT_MAX` clamp deterministically.
- Presentation payload assertions recursively reject Eloquent Models and framework Collections. A Product with more than 48 live Variants is treated as corrupt and the public PDP returns 404 rather than truncating its matrix.
- `StorefrontNavigationService` provides represented navigable roots only. `StorefrontHomeService` provides at most 8 newest cards, at most 6 eligible Stores ordered by visible count then name, and no paginator. Full filter dictionaries remain browse-only.
- Filter dictionaries expose represented Category paths, Brands, Stores, Currencies, and active Attribute/Value options only. Attribute labels are resolved in one joined/batched query; there are no ratings, facet counts, Redis, or Blade queries.
- The Variable selector follows server Attribute order and the sparse live matrix: preceding selections determine availability, impossible values are disabled, earlier changes clear incompatible later choices, and zero-stock choices remain selectable. Its states are `incomplete`, `unavailable`, `in_stock`, and `out_of_stock`.
- The gallery keeps thumbnail order by position but initializes the hero from `products.primary_image_id`; every image has localized alt text, dimensions, deterministic loading priority, a one-shot broken-image fallback, no-JavaScript links, and `aria-current`.
- Responsive navigation/filter dialogs expose expanded/control relationships, modal semantics, Escape, focus trap/return, background inerting, and scroll locking. Mobile filters also have a `<noscript>` form. Pagination is a Storefront design-system component with logical RTL/LTR arrows.

### S8C SEO contract
- Storefront head state bounds plain-text title/description, allows only absolute HTTP(S) metadata URLs, escapes output, and emits canonical, robots, and Open Graph title/description/type/url/image.
- Home and PDP are `index,follow`; an unfiltered first Category/Store page is `index,follow`; Search, filtered browse, and page > 1 are `noindex,follow`.
- Canonicals contain only normalized effective parameters and include the sanitized page when page > 1. Product canonical/OG image use the real Product URL and primary image.
- No `hreflang`, JSON-LD, fake ratings, or external merchandising media are emitted in S8C.

### S8C query evidence
- A disposable MySQL 8.4 transaction with 120 visible Products, 120 live Variants, 8 Stores, 6 Categories, 8 Brands, and 40 Attribute-linked Products measured rendered route counts at: Home 7, Search 13, Category 18, Store 14, and Product 20.
- On the same dataset, the pre-refactor orchestration equivalents measured Home full browse+paginator+full filters at 13 queries versus 7 through `StorefrontHomeService`; PDP+full filters measured 26 versus 20 with navigation-only loading.
- EXPLAIN used PK/unique/FK access (`const`, `eq_ref`, or `ref`) for Currency, Store/Vendor, taxonomy ancestry, represented Attribute links, translations, and PDP slug lookup. Tiny dictionary scans were 2 Attributes/3 Values; the optimizer chose a 120-row scan for some Variant aggregates/navigation rather than the existing Product/Variant indexes.
- No S8C index migration was added: Variant fan-out is domain-capped at 48 and existing `product_id`/unique/FK indexes bound correlated access; the observed small-table scans are cost-based and do not prove a production benefit sufficient to justify added write/storage cost. Re-evaluate with production cardinality and slow-query evidence.

### Criteria contract
- Immutable `CatalogCriteria` / `CatalogCriteriaResult` (no DB, no localized strings).
- Supports: `q`, category/brand/store slugs, currency, min/max price, availability, attribute code→value codes, sort.
- Allowed sorts: `newest` (default), `name`, `price_asc`, `price_desc`; deterministic Product ID tie-break.
- Deduplicate array values; max 3 attributes; max 8 values per attribute; unknown params ignored; malformed known values → machine-readable issue codes.
- Slug/code resolution and active-entity checks belong in the query service layer.

### Mixed currency & price
- SYP/USD may coexist for newest/name sorting.
- Price filtering/sorting requires one active Currency; parse decimals via `Money` using that Currency exponent; require min ≤ max.
- Malformed known filters produce issue codes; do not silently pretend invalid price filters were applied.
- Price filter: ≥1 live Variant inside range. Price sort: minimum live Variant price.

### Keyword search (closes BR-SRH-03 for V1)
- Escaped MySQL `LIKE` only; AR+EN Product translations; `name` + `short_description` only; max 80 characters (truncate + issue).
- User `%` / `_` / `\` are literal; no FULLTEXT/package/external engine in S8.

### Attribute filtering
- OR within one Attribute; AND across Attributes; one single live Variant must satisfy the complete combination.
- Inactive Attribute/Value codes rejected as unresolved filters.

### Listing aggregates
- SQL aggregate/subselect fields for min/max live price, any-in-stock, and Simple unambiguous compare-at.
- Do not eager-load every Variant for listing cards.
- Soft-deleted Variants never affect aggregates; zero-stock live Variants remain in min/max pricing.

### Detail & related
- The public PDP uses the detail loader from `Product::storefrontVisible()`; hidden, unknown, invalid-default, and over-cap topology → ModelNotFound/404.
- Related: exclude current; same leaf Category first; fill from same Store; dedupe; `published_at` desc then ID; max 4; never random; always storefront-visible.

### Locale (closes BR-TR-04)
- Presentation fallback: requested locale → English → Arabic → stable canonical fallback.
- Applies to Product, Category, Brand, Attribute, Attribute Value.
- Store name remains canonical (locale-invariant) in S8A; Store description localization is documented debt (no Store translation schema in S8).

### Presenters
- Query-free card/detail states; consume loaded relations/aggregates only; JSON-safe; money minor units as decimal **strings** for JS payloads.
- No Cart/Wishlist/purchase CTA in presentation contracts until real endpoints exist.

### Indexes / caching
- No index migration in S8A. Collect MySQL EXPLAIN evidence; propose indexes only with plan rationale.
- `currencies.code` PK is sufficient; do not add `currencies.is_active` index.
- `(locale,name)` does not meaningfully optimize `%term%`; no FULLTEXT.
- Caching deferred.

### Boundaries after S8C
- Rating filter / Store rating source; Wishlist/Checkout/Reviews; Store translations; conversion and caching remain deferred. **Cart C1** is implemented under ADR-041 / `docs/tasks/cart-c1.md` (after S8C). S8C added no purchase workflow, rating source, public SKU/quantity, Redis, FULLTEXT, or variant API.

**Confirms:** ADR-007, ADR-025, ADR-026, ADR-028, ADR-029, ADR-033, ADR-039.  
**Closes:** BR-TR-04 (fallback chain), BR-SRH-03 (escaped LIKE for V1), exact V1 sort set.  

**Source:** Catalog Slice S8 decision audit, approved with mandatory overrides 2026-08-22; S8B cutover implemented; S8C final acceptance recorded in `docs/tasks/storefront-s8c-recovery.md` (focused 49/516; full Docker 327/2351; browser smoke 6/0; R3 gate 2026-08-22).

---

## ADR-041 — Cart persistence, login merge, and mixed-currency cart totals (C1)

**Status:** Accepted  

**Decision (approved 2026-08-23, Cart C1 planning):**

### Persistence
- **Guest cart:** server **session** only (not Redis, not DB).
- **Authenticated cart:** **database** rows owned by the user (not session-as-source-of-truth).
- Cart lines reference **`variant_id` only** (ADR-029). Multi-vendor carts remain allowed (BR-CART-01).

### Login / register merge
- When a guest with a session cart authenticates, **merge session → DB cart by `variant_id`**.
- Matching lines **sum quantities**, then **cap to current on-hand stock** (`product_variants.quantity`).
- Lines that cannot be kept (variant missing, not storefront-purchasable, or stock 0 with no residual qty) are **dropped as unavailable**.
- The merge **reports** adjusted lines (qty reduced to stock) and unavailable lines to the UI; then the session cart is cleared.
- Merge does **not** decrement inventory and does **not** create orders.

### Mixed currencies in the cart
- A cart **may** contain lines priced in different product currencies (e.g. SYP + USD).
- Cart presentation shows **separate subtotals per currency**.
- **No FX conversion** in Cart C1 (no rate lookup, no single converted grand total).
- This rejects “forbid mixed carts” for the cart phase. **Checkout charge / settlement currency** (OPEN-005 remainder, BR-CUR-04/BR-CUR-08) remains OPEN and is **out of Cart C1**.

### Explicit non-goals for Cart C1
- **Checkout** / Parent Order placement.
- **Inventory decrement or reservation** (OPEN-021).
- **Wishlist**.
- Reviews, ratings, coupons, shipping quotes, Redis carts, and payment capture.

**Closes:** BR-CART-04, BR-CART-05; cart side of former “cart persistence / merge” P1 open.  
**Does not close:** OPEN-005 checkout FX/charge currency; OPEN-021 inventory timing; OPEN-018 Wishlist target.  
**Confirms:** ADR-019, ADR-029, ADR-032, ADR-033.  

**Source:** Cart C1 planning approval in project chat 2026-08-23; execution plan `docs/tasks/cart-c1.md`.  
**Implemented:** Cart C1 (C1-A…C1-D3) accepted 2026-08-23 — final gate focused **42 / 437**, full Docker PHPUnit **369 / 2791**.

---

## ADR-042 — Checkout V1: mixed-currency COD, stock decrement, flat shipping, payment grain

**Status:** Accepted  

**Decision (approved 2026-08-23, Checkout CHK-0):**

### Mixed currency
- Place mixed-currency carts **without FX conversion**.
- Parent receipt shows **separate COD dues per currency**.
- Each Vendor Order (and its COD Payment) remains **single-currency**.

### Inventory
- On successful place-order, **decrement** `product_variants.quantity` inside the same DB transaction with `lockForUpdate`.
- No soft-reserve table in V1.

### Shipping (V1)
- **Configurable flat fee per Vendor Order** (store setting with platform default fallback), in the VO currency.
- Not hard-coded in application constants.

### Payment / COD
- **One Payment record per Vendor Order**.
- COD statuses: `pending` | `collected` | `cancelled`.

### Address & codes
- One Parent shipping address snapshot at placement, copied onto Vendor Orders.
- Public codes: Parent `PO-…`, Vendor `VO-…` (internal bigint PKs remain separate).
- Syria-only governorate+city seed; **no area** level in V1.

### Commission
- Base = Vendor Order **item subtotal excluding shipping**.
- Rate and amount **snapshotted at placement**; recognized for reporting when VO reaches **`delivered`**.
- No vendor wallet/settlement ledger in V1.

### Notifications (Checkout V1 minimum)
- **Mail + database** on successful placement (customer Parent + each vendor VO).
- SMS and richer channels remain OPEN-013 remainder.

### Explicit non-goals for Checkout V1 / CHK
- Wishlist, Coupons, Reviews/ratings, card/wallet charge, Redis, FULLTEXT, settlement ledger, cancellations matrix (Phase 9).

**Closes:** OPEN-005 (checkout place/charge currency for V1), OPEN-006, OPEN-011, OPEN-012, OPEN-021; related BR-CHK / BR-PAY / BR-SHP / BR-COM / BR-CUR / BR-INV / BR-GEO RULE rows synced in CHK-0.  
**Narrows:** OPEN-013 to mail+DB minimum for Checkout; SMS later.  
**Does not close:** OPEN-007 coupons; OPEN-008/009 reviews; OPEN-010 cancellation; OPEN-018 Wishlist.  

**Source:** Checkout CHK-0 approval; readiness `docs/tasks/checkout-readiness.md`; execution `docs/tasks/checkout-chk.md`.  
**Implemented:** Checkout CHK (CHK-A…CHK-E) accepted 2026-08-24 — final gate focused **24 / 252**, full Docker PHPUnit **393 / 3044**.

---

## OPEN Decisions (Require Human Approval)

### OPEN-001 — Stores per vendor

**Status:** Closed → ADR-015  

### OPEN-002 — Same account as Customer and Vendor

**Status:** Closed → ADR-017  

### OPEN-003 — Meaning of `suspended`

**Status:** Closed → ADR-014  

### OPEN-004 — Product publishing moderation

**Status:** Closed → ADR-027  

### OPEN-005 — Multi-currency checkout policy

**Status:** Closed → ADR-042  

### OPEN-006 — Commission base and COD recognition timing

**Status:** Closed → ADR-042  

### OPEN-007 — Coupon stacking

**Question:** Single coupon only vs one platform + per-vendor coupons?  
**Recommendation:** Start strict: one platform coupon OR multiple vendor coupons each limited to their vendor — pick one simple rule and document examples.

### OPEN-008 — Review eligibility

**Question:** Is delivered mandatory for V1, or is purchased enough?  
**Recommendation:** Require delivered for higher review quality.

### OPEN-009 — Review uniqueness

**Question:** One review per customer per product, or per order?  
**Recommendation:** One per customer per product for V1 simplicity.

### OPEN-010 — Cancellation matrix

**Questions:** Who can cancel what, until which status? Can one Vendor Order be cancelled independently?  
**Must decide before Phase 9 (and ideally before Phase 7 UI copy).

### OPEN-011 — Payment record granularity for COD

**Status:** Closed → ADR-042  

### OPEN-012 — V1 shipping fee algorithm

**Status:** Closed → ADR-042  

### OPEN-013 — Notification channels for V1

**Status:** Partially closed → ADR-042 (Checkout V1 minimum = mail + database; SMS/other channels remain open)  
**Remainder:** SMS and additional channels beyond mail+database.  

### OPEN-014 — Guest cart / guest checkout

**Status:** Closed → ADR-019  

### OPEN-015 — Brand ownership

**Status:** Closed → ADR-024  

### OPEN-016 — Identity: email and/or phone

**Status:** Closed → ADR-016  

### OPEN-017 — In-flight orders when vendor is suspended

**Question:** Complete existing Vendor Orders or auto-cancel?  
**Recommendation:** Allow completion of non-cancelled in-flight orders; block new ones.

### OPEN-018 — Wishlist targets product vs variant

**Recommendation:** Product-level wishlist for V1.

### OPEN-019 — SKU uniqueness scope

**Status:** Closed → ADR-031  

### OPEN-020 — Admin KPI/report set

**Question:** Exact dashboard metrics and export needs for grading/demo.

### OPEN-021 — Inventory reserve vs decrement at checkout (C-05)

**Status:** Closed → ADR-042 (decrement in place-order transaction with `lockForUpdate`; no soft-reserve table in V1)  

---

## Contradictions & Ambiguities Identified (Pre-Decision)

| Item | Tension | Resolution approach |
|------|---------|---------------------|
| Application status includes `suspended` | Suspension usually post-approval | **Resolved** ADR-014 / P0-1 |
| “Each approved vendor can own a store” | Singular wording vs possible multi-store | **Resolved** ADR-015 / P0-2 |
| Negative stock RULE vs OPEN wording (C-04) | FR vs BR conflict | **Resolved** ADR-032 — forbid negative; no backorders |
| Inventory decrement RULE vs reserve OPEN (C-05) | BR-INV-02 vs BR-INV-03 | **Resolved** ADR-042 — decrement at checkout |
| Sellable unit / no-variant products | Product vs variant FKs | **Resolved** ADR-029 — always-variant |
| Brand ownership OPEN | Admin vs vendor brands | **Resolved** ADR-024 |
| Publishing moderation OPEN | Self-publish vs queue | **Resolved** ADR-027 |
| SKU uniqueness OPEN | Global vs per vendor/store | **Resolved** ADR-031 |
| Reviews after purchase **and preferably** after delivery | Preference vs hard gate | OPEN-008 |
| Coupons feature-rich vs “do not over-engineer” | Wide coupon matrix | Implement core fields; strict stacking (OPEN-007) |
| Multi-currency products + single COD collection | Charge currency unclear | **Resolved** ADR-042 (place without FX; per-currency COD dues) |
| Commission configurable + COD cash flow | When commission is “earned” unclear | **Resolved** ADR-042 |
| Shipping evolution vs V1 concreteness | Need one calculator now | **Resolved** ADR-042 — flat per VO |
| Roles include Vendor & Customer | Dual-role account unclear | **Resolved** ADR-017 / P0-4 |

P0 product decisions remain accepted via `docs/p0-decisions.md` (do not reopen). Catalog gate decisions above are accepted 2026-08-12. Remaining commerce OPENs stay open.

---

## Decision Process

1. Stakeholders answer OPEN-* items (remaining P1+ commerce/post-purchase).  
2. Update this file: move OPEN → ADR (Accepted) or Rejected.  
3. Sync `business-rules.md` RULE rows.  
4. P0 gate is complete; Catalog ADRs ADR-022…036 are accepted — Catalog implementation may proceed only after this documentation sync. Checkout/cart OPENs still gate later phases.

---

## Index of Related Docs

- `requirements.md` — scope and FR/NFR  
- `business-rules.md` — detailed rules + OPEN markers  
- `use-cases.md` — actor flows  
- `architecture.md` — Laravel technical architecture  
- `development-plan.md` — phased delivery  
- `p0-decisions.md` — approved Phase 1 gate decisions (historical; do not rewrite)  
- `documentation-audit.md` — consistency audit baseline + Catalog sync notes  

