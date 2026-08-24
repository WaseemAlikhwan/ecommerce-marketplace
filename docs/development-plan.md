# Development Plan

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Status:** Draft for stakeholder review  
**Constraint:** Documentation phase first — no Laravel app scaffolding until planning decisions are accepted.

This plan is incremental. Each phase produces a demonstrable, testable slice. Later phases depend on earlier foundations.

---

## Phase 0 — Requirements Freeze & Decision Gate

| Field | Content |
|-------|---------|
| **Objective** | Resolve Phase 1 (P0) blockers; keep P1 commerce OPENs tracked for later phases. |
| **Features** | Stakeholder review of docs; P0 decisions in `p0-decisions.md`; decision log updates. |
| **Dependencies** | Completed drafts in `/docs`. |
| **Expected output** | **Done for P0 (2026-08-11):** suspension model, one store/vendor, identity, dual Customer+Vendor, Super Admin role, guest cart/auth checkout, password + email verification, locale persistence. Remaining P1 OPENs (currency checkout, commission timing, coupon stacking, review gate, cancellation matrix, shipping V1 rule, notification channels, etc.) stay open and do **not** block Phase 1. |
| **Testing requirements** | N/A (process gate). |
| **Exit criteria** | P0 Phase 1 Gate approved and docs synchronized → proceed to Phase 1. |

**P0 gate is complete and documentation is synchronized.** Phase 1 Laravel scaffolding is implemented in the repository.

---

## Phase 1 — Project Bootstrap & Platform Skeleton

| Field | Content |
|-------|---------|
| **Objective** | Create Laravel application structure, Docker Compose, baseline config. |
| **Features** | Laravel app; Nginx/PHP/MySQL/Redis Compose; env samples; CI-less local runbook; base Blade layouts (LTR/RTL + locale middleware per ADR-021); auth scaffolding (register with email+phone, login with email, password min 8 + confirm, reset via email, email verification flow). |
| **Dependencies** | Phase 0 P0 decisions approved (identity, password, verification, locale). |
| **Expected output** | Running empty app with health check page and auth screens meeting ADR-016/020/021. |
| **Testing requirements** | Feature tests for registration/login/password rules; email verification can be asserted without blocking login; Compose smoke test checklist. |

---

## Phase 2 — Users, Roles, Authorization Foundation

| Field | Content |
|-------|---------|
| **Objective** | Enforce role boundaries and admin permission model. |
| **Features** | Distinct roles `super_admin`, `admin`, `vendor`, `customer`; same user may have Customer+Vendor; admin permissions schema (catalog details still refineable); Policies/Gates skeleton; middleware for `/admin` and `/vendor`; seed Super Admin. |
| **Dependencies** | Phase 1; ADR-017, ADR-018. |
| **Expected output** | Unauthorized cross-area access returns 403; Super Admin distinct from Admin; permission checks demonstrable. **Implemented (2026-08-11):** `staff`/`vendor` middleware, policies for applications/stores/vendors, dual Customer+Vendor capability. Fine-grained permission catalog remains OPEN (BR-PERM-07). |
| **Testing requirements** | Policy/feature tests for isolation; dual-capability user; admin permission deny/allow cases. |

---

## Phase 3 — i18n, Geo, Currency Reference Data

| Field | Content |
|-------|---------|
| **Objective** | Establish Syria-ready locale/geo/currency foundations. |
| **Features** | Locale switcher implementing ADR-021; Arabic RTL layout baseline; translation table pattern for a sample entity; governorates/cities models + seeds; currencies SYP/USD; admin-managed FX rates. |
| **Dependencies** | Phase 2. |
| **Expected output** | UI switches ar/en; location data manageable; FX rate CRUD (admin). |
| **Testing requirements** | Locale middleware tests; FX snapshot helper unit tests; geo FK constraints. |

---

## Phase 4 — Vendor Onboarding & Stores

| Field | Content |
|-------|---------|
| **Objective** | Implement application → review → vendor → store flow. |
| **Features** | Vendor application CRUD/submit (requires verified email); admin review approve/reject; post-approval suspend/reinstate on **vendor/store** (not application status); notifications; single store profile per vendor (name, description, logo, banner, contact, status, rating placeholder). |
| **Dependencies** | Phases 2–3; media disk config; ADR-014, ADR-015, ADR-020. |
| **Expected output** | End-to-end vendor onboarding demo. **Implemented (2026-08-11) for application → review → one store:** submit/approve/reject, vendor role grant, unique `stores.vendor_id`, store identity edit. Suspension/reinstate UI is not in this slice (UC-A02 remains later). Notification *channels* remain OPEN (OPEN-013); onboarding uses queued email only. |
| **Testing requirements** | Application state transitions; cannot access vendor panel before approval; store ownership policy tests. |

---

## Phase 5 — Catalog, Variants, Inventory, Media

| Field | Content |
|-------|---------|
| **Objective** | Enable vendors to manage sellable catalog. |
| **Features** | Admin categories (depth ≤3, leaf assignment) and brands; products with translation tables + canonical slugs; always-variant model; attributes; SKU/price/stock on variants; product currency; images; statuses including admin `suspended`; storefront visibility scopes. |
| **Dependencies** | Phase 4 complete; Catalog ADRs ADR-022…036 accepted (2026-08-12). Publishing, brands, SKU, always-variant, and negative-stock rules are **resolved**. Mixed-currency checkout and inventory decrement timing remain OPEN and are **out of this phase**. |
| **Expected output** | Vendor can create simple/variable products with stock; admin can moderate/suspend; storefront can show published products from sellable stores (replacing the legacy fixture for wired routes). |
| **Testing requirements** | Ownership authz; SKU unique per store + composite store consistency; combination uniqueness; visibility scopes; publish requires AR/EN names; Form Request validation; no negative stock. |

**Catalog Slice S1 implemented (2026-08-12):** Admin-managed Categories and Brands with translation tables, canonical slugs, depth-3 hierarchy rules, activate/deactivate (no hard-delete UI), policies under `staff` middleware, and feature tests. Products/variants/attributes/inventory/media are **not** included. The fixture storefront remained unchanged.

**Catalog Slice S2 implemented (2026-08-12):** `currencies` reference table (SYP exponent 0, USD exponent 2); `stores.default_currency_code` FK defaulting to SYP; vendor store form can select SYP/USD; no exchange rates, product pricing, or checkout FX (OPEN-005 remains open).

**Catalog Slice S3 implemented (2026-08-12):** Vendor-owned **simple** product drafts with translation rows, canonical slug, product currency, and an atomic default `product_variants` row (SKU, minor-unit price, optional compare-at, quantity). Minimal variant price/stock is intentionally included here so ADR-029’s always-variant invariant is never violated by an intermediate Product-only model. Variable products, attributes, images, publishing, storefront wiring, and admin moderation remain **out of scope**. Fixture-backed public routes remained unchanged. OPEN-005 / C-05 remain open.

**Catalog Slice S4A implemented (2026-08-12):** Admin-managed **global Attribute dictionary** and **Attribute Values** with translation tables, stable non-localized codes, ordering, and activate/deactivate (no hard-delete UI). Product Attribute assignment, Variable Products, and variant combinations are **not** included — those belong to **S4B**. Simple Product Core is unchanged. Fixture-backed public routes remained unchanged.

**Catalog Slice S4B1 implemented (2026-08-12):** Variable product **domain and schema** only (ADR-037): `product_attributes` / `product_attribute_values` (SoftDeletes) / `product_variant_attribute_values` (immutable links); `products.default_variant_id` composite FK (removed `is_default`); canonical combination keys; `ProductVariantMatrixService` + variable draft creation; type immutable; 3/8/48 limits; draft matrix sync vs post-publication freeze. Vendor HTTP remains **simple-only** — matrix UI is **S4B2**. No publication, images, storefront, or conversion.

**Catalog Slice S4B2 implemented (2026-08-12):** Vendor **Variable Product builder** (Blade + Alpine.js): type selection on create, locked type on edit, atomic Variable metadata+matrix update, draft topology sync vs post-publication freeze, historical assignment/variant display, product index type/count/price range. No conversion, publication, images, storefront, or Attribute seeds.

**Catalog Slice S5A implemented (2026-08-16):** Product **image domain, normalized public-disk storage, and Vendor HTTP** (ADR-038): `product_images` + `product_image_translations`; `products.primary_image_id` composite FK; GD master normalization (JPEG/PNG/WebP); atomic upload/reorder/primary/alt/remove; `Storage::fake()` tests. Docker/MySQL gate accepted 2026-08-20 (`docs/s5a-gate-acceptance.md`).

**Catalog Slice S5B implemented (2026-08-20):** Vendor **Product gallery UI** on create/edit/index: bilingual Blade + Alpine.js gallery (`vendorProductGallery`), `VendorProductGalleryState` presenter, authoritative JSON gallery payloads, sequential upload queue, drag/button reorder with explicit save, alt-text editor, primary selection, read-only suspended/archived states, index primary thumbnail. **S5B interactive hardening:** explicit order/alt dirty model (no silent discard), queue completed/failed/dismiss + object-URL revoke, broken-image fallback, presenter N+1 fallback removed, read-only bootstrap omits mutation URLs, Chromium smoke against Docker `:8081`. Publication image requirement remains **S7**. No variant images, thumbnails/resizing beyond normalized master, staff moderation, or purge.

**Catalog Slice S7A implemented (2026-08-20):** Product **readiness domain and publication transitions** (ADR-039): `ProductReadinessService` + immutable issue/result DTOs; `ProductPublicationService::publish/unpublish`; `ProductPolicy::publish|unpublish` (staff rejected even with dual Vendor role); Vendor `POST .../publish|unpublish` endpoints; published-product integrity guards on simple/variable updates, matrix sync, and last-image removal; first-ever `published_at` semantics; inactive historical Attribute/Value republish exception; lock-first keep-on-edit for Category/Brand/Currency; HTTP update allows exact existing inactive Currency.

**Catalog Slice S7B implemented (2026-08-21):** Vendor **publication readiness UI** and **reusable visibility scopes** (ADR-028/039): `VendorProductReadinessState` presenter; edit-page readiness sidebar with Publish/Unpublish controls; Alpine dirty-state coordination; gallery upload/remove JSON readiness sync; `Product::published()` / `Product::storefrontVisible()`. The public Storefront still used a fixture. Fixture replacement and real catalog/browse/PDP wiring belonged to the later **Storefront Catalog** slice. No admin moderation, events/queues, status-history, migrations, or packages.

**Storefront Catalog Slice S8A implemented (2026-08-22):** Real catalog **query/domain/presenter foundation** only (ADR-040): `User::assignRole()` loaded-roles invalidation; `Category::storefrontNavigable()` / `Store::publiclyEligible()` SQL scopes; immutable `CatalogCriteria`/`CatalogCriteriaResult`; composable `StorefrontProductQuery` (browse aggregates, detail loader, related max 4); query-free `ProductCardPresenter` / `ProductDetailPresenter`. Public fixture routes/views remained unchanged. S8B = atomic public cutover + fixture removal; S8C = premium UI/SEO/EXPLAIN hardening/browser smoke. No migrations, packages, Cart/Wishlist/Checkout/Reviews, or public controllers.

**S8A hardening (2026-08-22):** Public-boundary safety on the unwired foundation — hostile GET shape normalization with machine-readable issue codes; blocking vs non-blocking issue semantics (`allIssues()` / `hasUnresolvedFilters()`); JSON-safe money minors as decimal strings; detail loader fail-closed for missing/soft-deleted default Variants; minimized public Variant payload (no SKU/quantity); strengthened nested relation assertions for zero-query presenters; search/filter regression coverage; docs synced with ADR-040 (fallback closed; sorts newest/name/price_asc/price_desc). It remained unwired to public routes.

**Storefront Catalog Slice S8B implemented (2026-08-22):** Atomic persisted-data cutover for Home/Search/Category/Store/Product; thin eligibility-scoped controllers; sanitized 24-item browse orchestration and canonical pagination; query-free filter dictionaries and page states; localized criteria issues; real Store counts/media; image dimensions and one-shot fallbacks; Variable PDP Alpine selector with bounded no-JavaScript combinations; AR/EN GET filter UI. The fixture catalog was deleted. Public Ratings, Reviews, Cart, Wishlist, Checkout, conversion, caching, migrations, indexes, and packages remain out of scope; S8C completed separately the same day.

**Storefront Catalog Slice S8C implemented (2026-08-22):** Production hardening completed and accepted through recovery S8C-R1/R2/R3: shared `alpha_dash:ascii` criteria validation; raw → normalized → effective filters; page-50 public cap; recursive model-free payload guards; fail-closed over-cap Variant topology; focused Navigation/Home services; represented hierarchical filter options; sparse Variable selector; primary-ID gallery initialization and no-JavaScript fallbacks; accessible mobile navigation/filters and design-system pagination; bounded canonical/robots/Open Graph metadata; absolute route query budgets; disposable MySQL EXPLAIN evidence (Home/Search/Category/Store/Product 7/13/18/14/20 on a 120-Product dataset; no index migration). Final gate: focused **49 tests / 516 assertions**; full Docker PHPUnit **327 / 2351**; Pint `--test` pass; `view:cache` pass; `npm run build` pass; AR/EN parity **874/874**; fresh migrate+seed pass; browser smoke **6/0**; smoke leftovers **0**. No Ratings, Reviews, Cart, Wishlist, Checkout, conversion, Redis, FULLTEXT, JSON-LD, public SKU/quantity, packages, or external merchandising media.

---

## Phase 6 — Cart, Search/Browse, Wishlist

| Field | Content |
|-------|---------|
| **Objective** | Customer discovery and pre-purchase flows. |
| **Features** | Product listing/search/filter/sort (MySQL); cart add/update/remove (multi-vendor) including **guest cart**; wishlist add/remove; price display rules; checkout redirect to auth for guests (ADR-019). Cart lines use **`variant_id`** only (ADR-029). |
| **Dependencies** | Phase 5; ADR-019; **ADR-041** (session guest cart, DB auth cart, merge-by-variant with stock cap + report, mixed-currency cart totals without conversion). **Wishlist V1 product target closed by WSH / OPEN-018.** Checkout FX/charge currency still OPEN-005. |
| **Expected output** | Customer can find products and build a multi-vendor cart. |
| **Testing requirements** | Search filter feature tests; cart quantity vs stock checks; wishlist uniqueness. |

**Cart Slice C1 implemented (2026-08-23):** ADR-041 executed via `docs/tasks/cart-c1.md` (C1-A…C1-D3). Guest session cart + authenticated DB cart; login/register merge by `variant_id` with stock cap + flash report; query-free per-currency cart subtotals (no FX); storefront HTTP mutations + Blade cart UI. Final gate: focused Cart **42 / 437**; full Docker PHPUnit **369 / 2791**; Pint (Cart-scoped) pass; `view:cache` pass; `npm run build` pass; AR/EN **908/908**; forbidden-ref pass; HTTP smoke guest→register merge→mixed SYP/USD subtotals with leftovers **0**. Checkout, inventory decrement/reserve, and Wishlist remain out of C1.

**Wishlist WSH (OPEN-018 V1) implemented (2026-08-24):** Product-level wishlist (`product_id`, not variant) for authenticated customers; unique `(user_id, product_id)`; storefront-visible add gate; account list via catalog-safe cards; PDP add/remove; fail-closed ownership (stranger → 404); no guest wishlist, convert-to-cart, Coupons, Reviews, Checkout changes, or Redis. Final gate: focused WSH **17 / 136**; Pint (WSH-scoped) pass; `view:cache` pass; AR/EN **1003 / 1003**; forbidden-ref pass; smoke login→PDP add→account list→remove with leftovers **0**.

---

## Phase 7 — Checkout, Orders, Shipping, COD Payment

| Field | Content |
|-------|---------|
| **Objective** | Transactional multi-vendor purchase pipeline. |
| **Features** | CheckoutService; Parent Order / Vendor Orders / Items; address snapshot; shipping calculator V1; PaymentGateway + COD; inventory lock/decrement; currency/commission snapshots; customer/vendor order UIs; status transitions + notifications. |
| **Dependencies** | Phases 3–6; shipping/payment/currency OPEN DECISIONS resolved. |
| **Expected output** | Full purchase of multi-vendor cart via COD. |
| **Testing requirements** | Checkout feature tests; concurrent stock safety tests; vendor cannot see others’ orders; payment abstraction smoke test with COD driver. |

**Checkout CHK (ADR-042) implemented (2026-08-24):** Multi-vendor COD place-order via `CheckoutService` with stock `lockForUpdate` decrement; flat per-VO shipping; one COD Payment per Vendor Order; Parent address snapshot; `PO-…` / `VO-…` codes; commission snapshot at placement; Syria geo + customer addresses; mail+database placement notifications; customer Parent Order and vendor Vendor Order UIs. Final gate: focused Checkout **24 / 252**; full Docker PHPUnit **393 / 3044**; Pint (Checkout-scoped) pass; `view:cache` pass; `npm run build` pass; AR/EN **960/960**; forbidden-ref pass; HTTP smoke login→cart→COD→order views with leftovers **0**. Coupons, Wishlist, Reviews, cancellations, and card charge remain out of Phase 7.

**Vendor Order lifecycle VOL (ADR-042) implemented (2026-08-24):** Vendor-only forward VO transitions `pending→confirmed→shipped→delivered` with commission recognition on `delivered`, mail+database status notifications, vendor panel advance actions, and customer Parent Order show/index live VO status labels (Parent stays `placed`; COD Payment status and cancellations out of VOL). Final gate: focused VOL **10 / 104**; Pint (VOL-scoped) pass; `view:cache` pass; AR/EN **990 / 990**; forbidden-ref pass; smoke vendor advances VO → customer sees status with leftovers **0**. Wishlist, Coupons, and Reviews remain out of Phase 7.

**Order Cancellation CAN (OPEN-010 V1) implemented (2026-08-24):** Domain cancel service + vendor VO cancel UI + customer Parent cancel UI enforcing the V1 matrix (customer: all-VO-pending Parent cancel; vendor: own VO pending/confirmed; stock restore; COD Payment→cancelled; notifications; fail-closed 404). Final gate: focused CAN **14 / 143**; Pint (CAN-scoped) pass; `view:cache` pass; AR/EN **998 / 998**; forbidden-ref pass; smoke vendor cancel VO + customer cancel Parent with leftovers **0**. Admin cancel, post-ship returns, COD `collected`, Parent auto-derivation, Wishlist/Coupons/Reviews remain out of this Phase 7 remainder.

---

## Phase 8 — Commissions & Coupons

| Field | Content |
|-------|---------|
| **Objective** | Configurable economics and simple promotions. |
| **Features** | Global commission config; vendor overrides; snapshots already wired in Phase 7 hardened; platform/vendor coupons; limits/restrictions; redemption accounting. |
| **Dependencies** | Phase 7; coupon stacking decision. |
| **Expected output** | Admin configures commission; coupons reduce totals correctly. |
| **Testing requirements** | Unit tests for discount math/limits; feature tests for redemption concurrency; commission override resolution tests. |

---

## Phase 9 — Reviews, Cancellations, Order Lifecycle Hardening

| Field | Content |
|-------|---------|
| **Objective** | Post-purchase quality and lifecycle completeness. |
| **Features** | Verified reviews; duplicate prevention; admin moderation; cancellation flows restoring stock/coupons; delivered transitions; store/product rating aggregation per approved rule. |
| **Dependencies** | Phase 7; review/cancel OPEN DECISIONS. |
| **Expected output** | Delivered order enables review; cancellations reverse stock. |
| **Testing requirements** | Review eligibility tests; cancellation matrix tests; rating aggregate tests. |

**Note (2026-08-24):** OPEN-010 **V1** cancel matrix is already implemented via Order Cancellation CAN (Phase 7 remainder) — customer Parent cancel (all-VO-pending) + vendor VO cancel (pending/confirmed) + stock restore + COD Payment→cancelled. Phase 9 still owns reviews/ratings, admin cancel, post-ship returns/refunds, coupon release, settlement ledger, and any Parent auto-derivation product beyond CAN’s narrow cancel-side coherence.

---

## Phase 10 — Admin Dashboard, Reports, Operational Tools

| Field | Content |
|-------|---------|
| **Objective** | Platform operations completeness for V1 demo/defense. |
| **Features** | Dashboard KPIs; management screens for users/vendors/applications/stores/products/categories/brands/orders/payments/commissions/coupons/reviews; basic reports. |
| **Dependencies** | Phases 4–9. |
| **Expected output** | Admin can operate the marketplace without DB tools. |
| **Testing requirements** | Permission-gated admin feature tests; report total consistency with snapshots. |

---

## Phase 11 — Hardening, Performance Basics, Documentation Handoff

| Field | Content |
|-------|---------|
| **Objective** | Production-oriented polish for evaluation. |
| **Features** | Index review; queue reliability; upload limits; rate limits; error pages; README runbook; seed demo dataset; security checklist. |
| **Dependencies** | Phase 10. |
| **Expected output** | Stable demo environment + developer docs. |
| **Testing requirements** | Regression suite green; manual UAT script executed. |

---

## Future Phases (Explicitly After V1)

| Phase | Candidate scope |
|-------|-----------------|
| F1 | Real payment gateways via PaymentGateway implementations |
| F2 | Refunds/settlements ledger |
| F3 | Vendor subscription plans |
| F4 | Advanced shipping rules / carrier integrations |
| F5 | External search engine if MySQL proves insufficient |
| F6 | Image CDN/optimization pipeline |
| F7 | SMS notification channel |

---

## Cross-Phase Engineering Standards

Applies from Phase 1 onward:

1. Thin controllers; Form Requests; Policies.
2. Business logic in services/actions.
3. Migrations with constraints/indexes.
4. Transactions for multi-table writes.
5. Feature tests for each completed phase exit.
6. No hard-coded commission percentages.
7. No `name_ar`/`name_en` columns.
8. No search engine dependency.
9. No real payment gateway until Future F1.

---

## Suggested Demo Milestones (University)

| Milestone | After phase | Demo story |
|-----------|-------------|------------|
| M1 | 4 | User applies; admin approves; store created |
| M2 | 6 | Browse/search; multi-vendor cart |
| M3 | 7 | Checkout COD; vendor processes shipment |
| M4 | 8–9 | Coupon + commission snapshot; review after delivery |
| M5 | 10–11 | Full admin operations + Arabic/English |

---

## Staffing / Workstream Hint (Optional)

If working as a small team:

- **Stream A:** Auth, admin, vendor onboarding  
- **Stream B:** Catalog, search, media  
- **Stream C:** Cart, checkout, orders, payments  

Synchronize on shared schemas before Stream C starts (end of Phase 5).

---

## Immediate Next Step

Phase 1 bootstrap and the implementation slice covering docs Phase 2 (role boundaries) plus docs Phase 4 onboarding (application, review, one store) are in the repository. **Catalog ADRs (ADR-022…036) are documented and approved.** Catalog **code** may start only when explicitly requested; do not begin cart/checkout or other commerce until their OPENs are resolved for those phases. Mixed-currency checkout (OPEN-005) and inventory decrement timing (OPEN-021) stay open.
