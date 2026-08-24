# Requirements Document

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Phase:** Planning / Documentation  
**Status:** Draft for stakeholder review  
**Stack (planned):** Laravel, PHP, Blade, MySQL, Redis, Nginx, Docker, Docker Compose

---

## 1. Purpose

Build a multi-vendor e-commerce marketplace for the Syrian market where vendors operate stores and sell general products to customers, with administrators managing platform operations.

The project is a university deliverable that must demonstrate production-oriented engineering practices: clear requirements, explicit business rules, layered architecture, secure authorization, transactional integrity, and an incremental implementation plan.

---

## 2. System Scope

### 2.1 In Scope (V1)

| Area | Scope |
|------|--------|
| Users & auth | Registration, login, password reset, role-based access |
| Roles | Super Admin, Admin, Vendor, Customer |
| Vendor onboarding | Application → admin review → approve/reject → vendor account → store |
| Stores | Exactly one store per approved vendor (P0-2) |
| Catalog | Categories, brands, products, images, attributes, variants, SKU, price, currency, inventory |
| Cart & checkout | Multi-vendor cart; single checkout producing Parent Order + Vendor Orders + Order Items |
| Shipping | Per Vendor Order; location model suited to Syrian governorates/cities |
| Payments | Cash on Delivery (COD) via a payment abstraction |
| Commissions | Configurable global commission; optional vendor-specific override |
| Coupons | Platform and vendor coupons with core restriction/limit rules (pragmatic V1) |
| Reviews | Verified purchase reviews (delivery preference documented) |
| Wishlist | Add/remove products |
| Search/filter | MySQL-backed keyword + filters + sorting |
| i18n | Arabic and English UI and translatable content |
| Currency | SYP and USD with preserved order exchange rates |
| Notifications | Event-driven notifications with channel extensibility |
| Admin | Dashboard stats and management of core marketplace entities |
| Infrastructure | Docker/Docker Compose for local/dev-aligned environments |

### 2.2 Geographic Scope

- Marketplace operates within **Syria only**.
- Address and shipping models must support Syrian administrative geography (governorates, cities, and extensible lower levels).

### 2.3 Language & Currency Scope

- Languages: **Arabic**, **English** (architecture must allow more languages later).
- Currencies: **SYP**, **USD**.

---

## 3. Out of Scope (V1)

The following are explicitly **out of scope** for V1 unless later approved:

- Real payment gateway integrations (cards, wallets, bank transfers beyond COD)
- Vendor subscription billing / SaaS plan charging
- Elasticsearch / OpenSearch / Meilisearch / Algolia
- Mobile native apps (iOS/Android)
- Live chat / messaging between customers and vendors
- Advanced logistics / third-party carrier API integrations
- Automated tax calculation engines (beyond simple order totals if needed later)
- Multi-country expansion
- Marketplace advertising / sponsored listings
- Affiliate programs
- Fixed bilingual DB columns (`name_ar` / `name_en` style) as the translation strategy
- Hard-coded commission percentages in application code
- Automatic vendor elevation on registration
- Full refund/settlement ledger as a production finance module (document as future; see business rules)

---

## 4. Actors

| Actor | Description |
|-------|-------------|
| Guest | Unauthenticated visitor; may browse/search and use a guest cart; must authenticate to checkout (P0-6) |
| Customer | Registered buyer |
| Vendor Applicant | Customer (or user) who submitted a vendor application |
| Vendor | Approved seller who owns a store |
| Admin | Platform staff with granular permissions |
| Super Admin | Highest privilege administrator |

---

## 5. Functional Requirements

### 5.1 Authentication & Identity

| ID | Requirement |
|----|-------------|
| FR-AUTH-01 | Users register with **email and phone** (both required, both unique). Login uses **email** and password. |
| FR-AUTH-02 | Users can log in and log out securely. |
| FR-AUTH-03 | Users can request password reset via email. |
| FR-AUTH-04 | Sessions must be invalidated appropriately on logout and password change. |
| FR-AUTH-05 | A user does not become a vendor solely by registering. |
| FR-AUTH-06 | Password must be at least 8 characters and confirmed on registration/reset. No mandatory complexity class (uppercase/symbol) in V1. |
| FR-AUTH-07 | Email verification is supported. Unverified users may log in, browse, use cart, and checkout. Submitting a **vendor application** requires a verified email. |
| FR-AUTH-08 | Phone OTP / SMS verification is **not** required in V1; phone is stored with format validation for contact/COD. |

### 5.2 Roles & Authorization

| ID | Requirement |
|----|-------------|
| FR-RBAC-01 | System supports conceptual roles/capabilities: Super Admin, Admin, Vendor, Customer. A marketplace user may hold **Customer and Vendor** capabilities on the same account after approval (P0-4). |
| FR-RBAC-02 | Administrators may have granular permissions. Super Admin is a distinct role (`super_admin`) with full access and is not an Admin with a flag (P0-5). |
| FR-RBAC-03 | Vendors may access only their own private resources (store, products, vendor orders, etc.). |
| FR-RBAC-04 | Customers may access only their own private resources (orders, wishlist, profile, addresses). |
| FR-RBAC-05 | Authorization must be enforceable via Policies/Gates (Laravel), not view-layer checks alone. |
| FR-RBAC-06 | Authorization design must be extensible without selecting a third-party package in V1 planning. |

### 5.3 Vendor Application & Onboarding

| ID | Requirement |
|----|-------------|
| FR-VND-01 | Users can submit a vendor application (email must be verified — FR-AUTH-07). |
| FR-VND-02 | Vendor **applications** support statuses: `pending`, `approved`, `rejected` (terminal after decision). Application status does **not** include `suspended`. |
| FR-VND-03 | Admins can review, approve, or reject applications. After approval, admins (with permission) may **suspend** the vendor account and/or store per documented rules (P0-1). |
| FR-VND-04 | Approval creates/activates a Vendor capability and enables store setup. |
| FR-VND-05 | Rejection communicates result to the applicant (notification). |
| FR-VND-06 | Applicants and admins receive notifications for application lifecycle events; vendors are notified of suspension and related account events when applicable. |

### 5.4 Stores

| ID | Requirement |
|----|-------------|
| FR-STR-01 | Each approved vendor owns **exactly one** store in V1 (`stores.vendor_id` unique). |
| FR-STR-02 | Store fields include at least: name, description, logo, banner, contact information, status, rating. |
| FR-STR-03 | Store visibility/selling capability depends on store status (including suspension). |
| FR-STR-04 | Store rating is derived from defined aggregation rules (see business rules / OPEN DECISION — P1). |

### 5.5 Catalog (Categories, Brands, Products)

| ID | Requirement |
|----|-------------|
| FR-CAT-01 | Admins manage categories as an adjacency-list hierarchy with maximum **three** levels (Root → Subcategory → Leaf). No nested-set/closure packages (ADR-023). |
| FR-CAT-02 | Brands are **admin-managed** global entities. Vendors select active brands only (ADR-024). |
| FR-PRD-01 | Vendors create and manage products belonging to their own store (`store_id` only; ADR-022). |
| FR-PRD-02 | Products support: exactly one leaf category, optional brand, multiple images, translated descriptions, attributes, variants; product-level currency; variants hold SKU, price (minor units), and stock. |
| FR-PRD-03 | Every product has one or more variants. Variants have SKU, price, and inventory (e.g., T-Shirt × Color × Size). Simple products use a default variant (ADR-029). |
| FR-PRD-04 | Product statuses: `draft`, `published`, `unpublished`, `suspended`, `archived`. Vendors may self-publish; admins may suspend for moderation (ADR-027). |
| FR-PRD-05 | User-facing catalog names/descriptions use translation tables (not `*_ar`/`*_en`). Publication requires Arabic and English product names (ADR-025). |
| FR-PRD-06 | Canonical non-localized slugs on `products`, `categories`, and `brands` are unique per entity table (ADR-026). |
| FR-PRD-07 | SKU is required on every variant, unique per store, with database-enforced `product_variants.store_id == products.store_id` (ADR-031). |
| FR-PRD-08 | Normal UI does not hard-delete products/variants; archive + soft-delete; referenced categories/brands are deactivated (ADR-036). |

### 5.6 Inventory

| ID | Requirement |
|----|-------------|
| FR-INV-01 | Inventory is tracked on `product_variants.quantity` (always-variant model; ADR-029/032). |
| FR-INV-02 | Inventory updates must reject invalid stock changes. **Negative stock is forbidden. Backorders are not allowed** (ADR-032; C-04 closed). |
| FR-INV-03 | Order creation and inventory mutations must use database transactions where appropriate (Checkout phase). |
| FR-INV-04 | Concurrent checkout must not oversell (locking/transaction strategy in architecture). At successful checkout, **decrement** variant stock inside the same transaction (ADR-042 / OPEN-021 closed). |

### 5.7 Cart & Checkout

| ID | Requirement |
|----|-------------|
| FR-CART-01 | Guests and authenticated customers can add, update quantity, and remove cart items (P0-6). |
| FR-CART-02 | Cart may contain items from multiple vendors. |
| FR-CHK-01 | Checkout requires an authenticated Customer. Checkout creates a Parent Order containing Vendor Orders and Order Items. |
| FR-CHK-02 | Each Vendor Order belongs to exactly one vendor. |
| FR-CHK-03 | Checkout validates stock, product availability, shipping inputs, and payment method. |
| FR-CHK-04 | Checkout snapshots money, currency, commission, and address data needed for historical integrity. V1 places mixed-currency orders **without** FX conversion; Parent shows per-currency COD dues (ADR-042). |
| FR-CHK-05 | Successful checkout clears/consumes purchased cart lines (BR-CHK-05). |
| FR-CHK-06 | Parent Order stores one shipping address snapshot; public codes use `PO-…` / `VO-…` (ADR-042). |

### 5.8 Orders

| ID | Requirement |
|----|-------------|
| FR-ORD-01 | Customers can view their Parent Orders and related Vendor Orders/items. |
| FR-ORD-02 | Vendors can view and manage only their Vendor Orders. |
| FR-ORD-03 | Admins can view Parent Orders and all Vendor Orders. |
| FR-ORD-04 | Order status lifecycle is defined for Parent Order and Vendor Order (see business rules). |
| FR-ORD-05 | Customers receive notifications for key order events. |
| FR-ORD-06 | Vendors receive notifications for new Vendor Orders. |

### 5.9 Shipping

| ID | Requirement |
|----|-------------|
| FR-SHP-01 | Shipping is associated with Vendor Orders (not only Parent Order). |
| FR-SHP-02 | Different vendors may charge different shipping fees. V1 uses a **configurable flat fee per Vendor Order** (ADR-042). |
| FR-SHP-03 | Location data supports Syrian governorates and cities and is administratively manageable. |
| FR-SHP-04 | Shipping rule model must allow future evolution without rewriting order core. |

### 5.10 Commissions

| ID | Requirement |
|----|-------------|
| FR-COM-01 | Platform takes commission on vendor sales. |
| FR-COM-02 | Global platform commission is configurable (not hard-coded). |
| FR-COM-03 | Vendor-specific commission override is supported. |
| FR-COM-04 | Commission rate and amount are snapshotted on the Vendor Order at placement; base = item subtotal excluding shipping; recognition for reporting at Vendor Order `delivered` (ADR-042). |
| FR-COM-05 | Architecture leaves room for future vendor subscription plans; subscription billing is not a V1 feature. |

### 5.11 Coupons

| ID | Requirement |
|----|-------------|
| FR-CPN-01 | Support platform coupons and vendor coupons. |
| FR-CPN-02 | Support percentage and fixed discounts. |
| FR-CPN-03 | Support restrictions: product/category (and vendor scope for vendor coupons). |
| FR-CPN-04 | Support min order amount, max discount, start/end dates, usage limits, per-user usage limits. |
| FR-CPN-05 | V1 coupon stacking/interaction rules must be explicit and simple (see business rules / OPEN DECISIONS). |

### 5.12 Payments

| ID | Requirement |
|----|-------------|
| FR-PAY-01 | V1 supports Cash on Delivery. |
| FR-PAY-02 | Payment handling uses an abstraction so future gateways can be added without rewriting the order system. |
| FR-PAY-03 | COD payment status is tracked **per Vendor Order** (`pending` / `collected` / `cancelled`) (ADR-042). |
| FR-PAY-04 | No real external payment gateway integration in V1. |

### 5.13 Reviews

| ID | Requirement |
|----|-------------|
| FR-REV-01 | Customers can review products. |
| FR-REV-02 | A customer may review a product only after purchasing it. |
| FR-REV-03 | Prefer requiring the relevant Vendor Order (or item) to be delivered before review. |
| FR-REV-04 | Duplicate reviews are prevented per documented uniqueness rules. |
| FR-REV-05 | Admins can moderate reviews (hide/remove) as needed. |

### 5.14 Wishlist

| ID | Requirement |
|----|-------------|
| FR-WSH-01 | Authenticated customers can add products to a wishlist. |
| FR-WSH-02 | Customers can remove products from their wishlist. |
| FR-WSH-03 | Wishlist is private to the owning customer. |

### 5.15 Search & Browse

| ID | Requirement |
|----|-------------|
| FR-SRH-01 | Keyword search over products. |
| FR-SRH-02 | S8B/S8C filters: category, brand, store, currency, price, availability, attributes. Options are represented by visible products; rating filtering and facet counts are deferred until Reviews. |
| FR-SRH-03 | Sorting options for V1 storefront catalog: `newest` (default), `name`, `price_asc`, `price_desc` (ADR-040). Rating sort deferred until Reviews. |
| FR-SRH-04 | V1 search uses MySQL only (no dedicated search engine). |
| FR-SRH-05 | Browse renders 24 cards/page and caps public page input at 50; malformed input returns page 1, oversized valid integers clamp to 50. |
| FR-SRH-06 | Price filtering/sorting requires a Currency; rejected criteria are not displayed as applied, while unresolved valid-looking filters remain removable and fail closed. |
| FR-SRH-07 | Variable selection supports sparse live matrices, disables impossible values, retains zero-stock choices, and exposes incomplete/unavailable/in-stock/out-of-stock states without a lazy Variant API. |
| FR-SRH-08 | Public catalog pages provide bounded canonical/robots/Open Graph metadata. Search, filtered browse, and page > 1 are noindex; no JSON-LD or hreflang in S8C. |

### 5.16 Internationalization

| ID | Requirement |
|----|-------------|
| FR-I18N-01 | UI supports Arabic and English. |
| FR-I18N-02 | Translatable catalog/content uses a scalable translation model (not fixed `*_ar`/`*_en` columns). |
| FR-I18N-03 | Locale persistence (P0-8): first visit may use `Accept-Language` if `ar` or `en`, else default **Arabic**; explicit choice stored in a **cookie**; authenticated users also store preferred locale on the **user profile**, kept in sync with the cookie. |
| FR-I18N-04 | Arabic UI must support RTL layout in Blade views. |

### 5.17 Currency

| ID | Requirement |
|----|-------------|
| FR-CUR-01 | Platform supports SYP and USD. |
| FR-CUR-02 | Each **store** has a default currency code (default SYP) (ADR-033). |
| FR-CUR-03 | Each **product** has an explicit currency code (inherits store default; SYP or USD). Variants share the product currency (no variant currency column). |
| FR-CUR-04 | Multi-currency **checkout** rules remain OPEN and must be documented before Checkout implementation (OPEN-005). |
| FR-CUR-05 | When conversion is used, the exchange rate applied to an order is persisted; historical orders must not change when rates change. |
| FR-CUR-06 | External exchange-rate provider integration is out of scope for V1; rates may be admin-managed. |
| FR-CUR-07 | Catalog prices use unsigned integer minor units (SYP exponent 0, USD exponent 2) on variants (ADR-033). |

### 5.18 Media / Images

| ID | Requirement |
|----|-------------|
| FR-MED-01 | Products support multiple ordered images with a primary image (ADR-034). |
| FR-MED-02 | Stores support logo and banner. |
| FR-MED-03 | Files are stored via a storage abstraction suitable for local and future object storage (`public` disk paths in DB). |
| FR-MED-04 | Architecture allows future image optimization (thumbnails, CDN) without redesigning entities. |

### 5.19 Notifications

| ID | Requirement |
|----|-------------|
| FR-NTF-01 | Notify customers: order created, confirmed, shipped, delivered. |
| FR-NTF-02 | Notify vendors: new order; vendor application result. |
| FR-NTF-03 | Notify admins: new vendor application; important system events. |
| FR-NTF-04 | Notification design allows additional channels later (email, SMS, in-app, etc.). |

### 5.20 Administration

| ID | Requirement |
|----|-------------|
| FR-ADM-01 | Admin dashboard with core statistics. |
| FR-ADM-02 | Admins manage: users, vendors, vendor applications, stores, products, categories, brands, orders, payments, commissions, coupons, reviews, reports. |
| FR-ADM-03 | Admin actions respect granular permissions where configured. |

---

## 6. Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-SEC-01 | Passwords hashed with modern algorithms (Laravel defaults). |
| NFR-SEC-02 | CSRF protection on state-changing web requests. |
| NFR-SEC-03 | Authorization enforced server-side for all sensitive actions. |
| NFR-SEC-04 | Sensitive configuration via environment variables; secrets not committed. |
| NFR-REL-01 | Critical purchase flows use DB transactions. |
| NFR-REL-02 | Inventory integrity under concurrent requests. |
| NFR-PERF-01 | Appropriate indexes for search/filter and foreign keys. |
| NFR-PERF-02 | Redis available for cache/queues/sessions as architecture decides. |
| NFR-SCAL-01 | Controllers remain thin; business logic testable outside HTTP layer. |
| NFR-MAINT-01 | Follow Laravel conventions; avoid unnecessary abstractions. |
| NFR-I18N-01 | Correct locale and RTL behavior for Arabic. |
| NFR-OPS-01 | Runnable via Docker Compose for development alignment. |
| NFR-TEST-01 | Automated tests for critical business rules (authz, inventory, checkout, commissions). |
| NFR-AUD-01 | Important admin/vendor state changes should be traceable (at least via timestamps/status history where defined). |

---

## 7. Constraints

1. Do not hard-code commission percentages.
2. Do not use fixed bilingual columns for translations.
3. Do not introduce a search engine in V1.
4. Do not integrate a real payment gateway in V1.
5. Do not implement vendor subscription billing in V1.
6. Do not select a third-party authorization package during this documentation phase; document requirements first.
7. Marketplace is Syria-only.
8. University project: prefer simple, correct architecture over enterprise over-engineering.

---

## 8. Ambiguities & Missing Requirements (Pre-Decision Log)

P0 items resolved in `docs/p0-decisions.md` (approved 2026-08-11) are **removed** from this log. Catalog items resolved in `docs/decisions.md` ADR-022…036 (approved 2026-08-12) are **removed** from this log. Remaining items must not be silently assumed. Full treatment is in `business-rules.md` and `decisions.md`.

| Topic | Ambiguity |
|-------|-----------|
| Multi-currency cart / checkout | **Cart (ADR-041):** mixed currencies allowed; per-currency subtotals; no conversion. **Checkout (ADR-042):** place without FX; per-currency COD dues. |
| Commission timing | **Closed** ADR-042 — snapshot at placement; recognize at delivered |
| COD settlement | Operational who-collects remains BR-PAY-05 OPEN; software tracks Payment per VO |
| Coupon stacking | OPEN-007 — out of first Checkout slice (Phase 8) |
| Review gate | Delivered required or purchased sufficient for V1? (OPEN-008) |
| Cancellation window | Who cancels what, until which status? (OPEN-010) |
| Refunds | Any V1 partial support for COD cancel-before-delivery? |
| Shipping fee rules V1 | **Closed** ADR-042 — configurable flat fee per Vendor Order |
| Parent vs Vendor Order payment status | **Closed** ADR-042 — Payment per Vendor Order |
| Notification channels V1 | Checkout minimum mail + database (ADR-042); SMS later (OPEN-013 remainder) |
| Cart persistence | **Closed → ADR-041 / BR-CART-04/05** |
| Inventory reserve vs decrement | **Closed → ADR-042 / BR-INV-03** |
| Store rating source | Product reviews aggregate vs separate store reviews? |
| Store status enum details | Exact store statuses beyond sellable vs suspended |
| In-flight orders on vendor suspend | Complete vs auto-cancel (OPEN-017) |
| Vendor application re-apply / required fields | BR-APP-07, BR-APP-10 |
| Exact media limits | **Closed** → ADR-038 / BR-MED-03 |
| Admin permission catalog | BR-PERM-07 |
| Wishlist target | Product (OPEN-018 V1 closed by WSH) |

---

## 9. Success Criteria (Documentation Phase)

- Requirements distinguish functional, non-functional, in-scope, and out-of-scope items.
- Business rules mark uncertain items as **OPEN DECISION**.
- Architecture is implementable in Laravel without premature packages for authz/search/payments.
- Development plan is phased and testable.
- Major decisions and open decisions are recorded in `decisions.md`.

---

## 10. Related Documents

- `docs/business-rules.md` — explicit rules and OPEN DECISIONS  
- `docs/use-cases.md` — major use cases  
- `docs/architecture.md` — Laravel architecture proposal  
- `docs/development-plan.md` — phased implementation  
- `docs/decisions.md` — ADRs / decision log (P0: ADR-014…021; Catalog: ADR-022…036)  
- `docs/p0-decisions.md` — approved Phase 1 gate decisions (historical; do not reopen)  
- `docs/documentation-audit.md` — consistency audit baseline + Catalog sync notes  
