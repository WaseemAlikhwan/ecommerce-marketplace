# Business Rules

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Status:** Draft for stakeholder review  

Legend:
- **RULE** — Adopted for V1 planning (may still be refined).
- **OPEN DECISION** — Requires human approval; do not implement as if settled.
- **FUTURE** — Explicitly deferred beyond V1 core.

---

## 1. Customer Registration & Accounts

| ID | Type | Rule |
|----|------|------|
| BR-CUS-01 | RULE | A new registration creates a **Customer** capability/role. It does **not** create a Vendor. |
| BR-CUS-02 | RULE | Customers may maintain a profile and shipping addresses. |
| BR-CUS-03 | RULE | Registration requires **email** and **phone** (both unique). Login uses **email** + password (P0-3). |
| BR-CUS-04 | RULE | Phone OTP/SMS verification is **not** required in V1; validate phone format only. |
| BR-CUS-05 | RULE | Guests may browse and maintain a **guest cart**. Checkout requires authentication. Full guest checkout is out of V1 (P0-6). |
| BR-CUS-06 | RULE | Customers may access only their own profile, addresses, orders, wishlist, and reviews. |
| BR-CUS-07 | RULE | Password minimum length is **8** characters; password must be confirmed on registration/reset. No mandatory complexity class in V1 (P0-7). |
| BR-CUS-08 | RULE | Email verification is supported. Unverified users may use the storefront (login, cart, checkout). **Vendor application** submission requires verified email (P0-7). |

---

## 2. Vendor Applications

| ID | Type | Rule |
|----|------|------|
| BR-APP-01 | RULE | Any authenticated user with verified email and without an active vendor account may submit a vendor application (subject to BR-APP-07). |
| BR-APP-02 | RULE | Application statuses are: `pending`, `approved`, `rejected`. Approved/rejected are terminal for that application record. **`suspended` is not an application status** (P0-1). |
| BR-APP-03 | RULE | New applications start as `pending`. |
| BR-APP-04 | RULE | Only admins (with permission) may change application status to `approved` or `rejected`. |
| BR-APP-05 | RULE | Approval grants Vendor capability and unlocks store creation/activation. |
| BR-APP-06 | RULE | Rejection does not delete the user account; user remains a Customer. |
| BR-APP-07 | OPEN DECISION | After rejection, may the user re-apply immediately, after cooldown, or never without admin unlock? |
| BR-APP-08 | RULE | Post-approval suspension is modeled on the **vendor account** and **store**, not by rewriting the application to `suspended` (P0-1). |
| BR-APP-09 | RULE | Applicants and admins are notified of submission/result events. |
| BR-APP-10 | OPEN DECISION | Required application fields (business name, ID documents, tax info, address, etc.). |

**Note:** Application onboarding statuses are separate from vendor/store operational suspension (P0-1 / closed OPEN-003).

---

## 3. Vendor Approval & Vendor Account

| ID | Type | Rule |
|----|------|------|
| BR-VND-01 | RULE | Vendor privileges exist only after admin approval. |
| BR-VND-02 | RULE | A vendor must never access another vendor’s private resources. |
| BR-VND-03 | RULE | The same user account may hold **Customer** and **Vendor** capabilities simultaneously after approval (P0-4). |
| BR-VND-04 | OPEN DECISION | Who can suspend a vendor after approval: Super Admin only, or any Admin with permission? |
| BR-VND-05 | RULE | Suspended vendors cannot sell or accept new orders; handling of in-flight orders is **OPEN DECISION** (OPEN-017). |

---

## 4. Store Ownership

| ID | Type | Rule |
|----|------|------|
| BR-STR-01 | RULE | An approved vendor owns exactly **one** store used for selling (P0-2). |
| BR-STR-02 | RULE | V1 enforces one store per vendor (`stores.vendor_id` unique). Multi-store is out of V1. |
| BR-STR-03 | RULE | Store includes: name, description, logo, banner, contact information, status, rating, and default currency code (ADR-033). |
| BR-STR-04 | OPEN DECISION | Store statuses (e.g., `draft`, `active`, `inactive`, `suspended`) — exact enum beyond sellable vs suspended. |
| BR-STR-05 | RULE | Only active (or equivalently sellable) stores’ products are purchasable / storefront-sellable. Suspended stores are not sellable (ADR-028). |
| BR-STR-06 | OPEN DECISION | Is store rating aggregated from product reviews, a separate store-review entity, or admin-set? **Out of REV V1** (product reviews do not write store rating). |
| BR-STR-07 | RULE | Store content fields that are customer-facing should be translatable where applicable. |

---

## 5. Product Ownership & Publishing

| ID | Type | Rule |
|----|------|------|
| BR-PRD-01 | RULE | Every product belongs to exactly one store via `store_id`. Vendor ownership is derived through the store (ADR-022). Do not duplicate `vendor_id` on products. |
| BR-PRD-02 | RULE | Vendors may create/edit/archive/soft-delete only their own products. Normal UI does **not** hard-delete products or variants (ADR-036). |
| BR-PRD-03 | RULE | Admins may moderate any product (unpublish, suspend, archive, taxonomy/image moderation). Admins do **not** create products on behalf of vendors in V1 (ADR-035). |
| BR-PRD-04 | RULE | Vendors may self-publish valid own products. No mandatory admin approval queue. Admins may **suspend** products for moderation; vendors cannot republish while suspended (ADR-027). |
| BR-PRD-05 | RULE | Products support: one leaf category, optional brand, images, translated descriptions, attributes, variants, currency; variants hold SKU, price (minor units), and stock. |
| BR-PRD-06 | RULE | Every product has ≥1 variant. SKU, price amounts, and stock are authoritative on the **variant**. Products do not store authoritative price or stock (ADR-029). |
| BR-PRD-07 | RULE | Always-variant model: base product stock is unused; inventory is variant-only (ADR-029). |
| BR-PRD-08 | RULE | Brands (and categories) are **admin-managed** global taxonomy. Vendors select only (ADR-024). |
| BR-PRD-09 | RULE | Each product is assigned to exactly **one leaf** category. Category tree max depth is three levels (Root → Subcategory → Leaf) (ADR-023). |
| BR-PRD-10 | RULE | Only `published` products that also satisfy storefront visibility rules are public-catalog-visible: not soft-deleted; sellable store; approved vendor; assigned category active + leaf + active ancestors; optional brand active; currency active (ADR-028). Zero stock remains visible but later non-purchasable. `Product::storefrontVisible()` is wired to Home/Search/Category/Store/Product routes; invalid/missing live default or more than 48 live Variants additionally makes the PDP fail closed (S8B/S8C). |
| BR-PRD-11 | RULE | Product statuses: `draft`, `published`, `unpublished`, `suspended`, `archived`. `suspended` is platform moderation; `archived` is vendor/staff retirement. Clearing suspension restores to `unpublished` (ADR-027). |
| BR-PRD-12 | RULE | Canonical entity slugs (`products.slug`, `categories.slug`, `brands.slug`) are globally unique per table and **not** locale-specific (ADR-026). |
| BR-PRD-13 | RULE | Publication requires Arabic and English product **names** in translation tables. Descriptions are optional (ADR-025). |
| BR-PRD-14 | RULE | SKU is required on every variant, unique per store, normalized (trim + case-normalize), not stored on product. Soft-deleted SKUs remain reserved. `product_variants.store_id` must equal `products.store_id` via **database** composite FK/constraints (ADR-031). |
| BR-PRD-15 | RULE | Product `type` (`simple` / `variable`) is **immutable** after creation. No Simple ↔ Variable conversion in V1 (ADR-037). |
| BR-PRD-16 | RULE | Authoritative default sellable unit is `products.default_variant_id` with composite FK to the same product’s variant. A committed live product must have ≥1 live variant and a live default (ADR-029, ADR-037). |
| BR-PRD-17 | RULE | Vendor publication requires intrinsic readiness plus publication dependencies (AR/EN names, active leaf category with active ancestors, optional active brand, active currency, approved vendor, sellable store, ≥1 image + valid primary, live default variant, Simple/Variable invariants). Zero stock is allowed. `ProductReadinessService` is authoritative; `ProductPublicationService` owns transitions (ADR-039). |
| BR-PRD-18 | RULE | `published_at` is the first-ever publication timestamp: set only when null, preserved across unpublish/republish, and permanently freezes Variable topology (ADR-037, ADR-039). |
| BR-PRD-19 | RULE | Vendor transitions: Draft→Published (via publish when ready), Published→Unpublished, Unpublished→Published (when ready). Draft→Unpublished is invalid. Suspended/Archived cannot publish/unpublish. Publish/unpublish are idempotent on already-Published/Unpublished. Archive remains separate/terminal (ADR-027, ADR-039). |
| BR-PRD-20 | RULE | Published products reject vendor mutations that break product-owned integrity (no auto-unpublish). Unpublished products may be incomplete while editing; republish revalidates. External taxonomy/vendor/store deactivation does not rewrite product status (ADR-028, ADR-039). |
| BR-PRD-21 | RULE | First-ever publication requires active assigned global Attributes/Values. After first publication, inactive historical globals do not hide the product or block republish when topology is unchanged; new/restored combinations remain blocked (ADR-037, ADR-039). |

---

## 5A. Categories, Brands & Attributes

| ID | Type | Rule |
|----|------|------|
| BR-CAT-01 | RULE | Admins manage categories; adjacency list; max three levels; no nested-set/closure packages (ADR-023). |
| BR-CAT-02 | RULE | Products may only be assigned to **leaf** categories. |
| BR-CAT-03 | RULE | Category/brand translations store name and optional description; slugs live on the main entity (ADR-025/026). |
| BR-BRD-01 | RULE | Brands are admin-global; deactivate instead of hard-delete when referenced (ADR-024, ADR-036). |
| BR-ATTR-01 | RULE | Attributes and attribute values are admin-global; names are translatable (ADR-030). |
| BR-ATTR-02 | RULE | Variable products enforce unique attribute combinations per product; each live variant includes exactly one value per assigned attribute (ADR-030, ADR-037). |
| BR-ATTR-03 | RULE | Product attribute assignments and selected values use `product_attributes` / `product_attribute_values` (SoftDeletes, restore-in-place). Variant combination links (`product_variant_attribute_values`) are immutable historical rows and are not detached when a variant is archived (ADR-037). |
| BR-ATTR-04 | RULE | Combination keys are server-generated: sort by `attribute_id` ascending as `a{id}:v{id}|…`. Literal `default` is reserved for simple products. Unique `(product_id, combination_key)` includes soft-deleted rows; reuse restores the same variant (ADR-037). |
| BR-ATTR-05 | RULE | V1 limits: max 3 attributes per variable product; max 8 selected values per attribute; raw Cartesian ≤ 48 (reject before write); max 48 live variants. Submitted matrix may be a subset with ≥1 variant (ADR-037). |
| BR-ATTR-06 | RULE | Structural matrix changes are allowed only while draft and `published_at` is null. After first publication, topology is frozen. Inactive global attributes/values cannot be newly assigned or used for new/restored live combinations; historical links remain. First publication still requires active assigned globals; republish with unchanged inactive historical globals is allowed (ADR-037, ADR-039). |

---

## 6. Inventory

| ID | Type | Rule |
|----|------|------|
| BR-INV-01 | RULE | Inventory is authoritative on `product_variants.quantity`. No separate inventory table in Catalog V1 (ADR-032). |
| BR-INV-02 | RULE | Checkout-phase inventory mutation must run inside a successful order transaction with concurrency protection (`lockForUpdate`). Catalog phase only maintains on-hand quantity. |
| BR-INV-03 | RULE | At successful checkout, **decrement** `product_variants.quantity` inside the same transaction (ADR-042 / OPEN-021 closed). No soft-reserve table in V1. |
| BR-INV-04 | RULE | System must prevent overselling under concurrency (row locks / equivalent) at checkout. |
| BR-INV-05 | RULE | **Negative stock is forbidden** in V1 (C-04 closed / ADR-032). |
| BR-INV-06 | RULE | Cancelling a Vendor Order before fulfillment should restore inventory for affected items (subject to cancellation rules). |
| BR-INV-07 | RULE | **Backorders are not allowed** in V1 (ADR-032). |
| BR-INV-08 | RULE | Inventory movement/ledger tables are **not** required in the Catalog phase (explicit deferral; ADR-032). |

---

## 7. Cart

| ID | Type | Rule |
|----|------|------|
| BR-CART-01 | RULE | A cart may contain items from multiple vendors. |
| BR-CART-02 | RULE | Cart line items reference a **product variant** (`variant_id`) as the only sellable unit (ADR-029). |
| BR-CART-03 | RULE | Quantity cannot exceed available stock at checkout (and should warn earlier when possible). Cart mutations in C1 also cap quantity to current on-hand stock (ADR-041). |
| BR-CART-04 | RULE | **Guest cart** persists in the **session**. **Authenticated cart** persists in the **database** (ADR-041). |
| BR-CART-05 | RULE | On login/register, merge guest session cart into the user’s DB cart **by `variant_id`**, sum quantities, **cap to current stock**, drop unsellable lines, and **report adjusted and unavailable lines** (ADR-041). |
| BR-CART-06 | RULE | Price shown in cart is informational; authoritative price is revalidated at checkout. |
| BR-CART-07 | RULE | Mixed product currencies in one cart are **allowed**. Cart shows **separate totals per currency** and performs **no FX conversion** (ADR-041). Checkout may place mixed-currency orders without conversion (ADR-042 / BR-CUR-04). |
| BR-CART-08 | RULE | Cart C1 does **not** place orders, decrement/reserve inventory, or implement Wishlist. |

---

## 8. Checkout & Multi-Vendor Orders

| ID | Type | Rule |
|----|------|------|
| BR-CHK-01 | RULE | One checkout creates one **Parent Order**. |
| BR-CHK-02 | RULE | Parent Order splits into one **Vendor Order** per vendor represented in the cart. |
| BR-CHK-03 | RULE | Each Vendor Order contains **Order Items** for that vendor only. |
| BR-CHK-04 | RULE | Checkout fails atomically if any critical validation fails (stock, availability, required address, payment method). |
| BR-CHK-05 | RULE | Successful checkout clears/consumes the purchased cart items. |
| BR-CHK-06 | RULE | One customer shipping address on the Parent Order, snapshotted at placement and copied onto Vendor Orders (ADR-042). |
| BR-CHK-07 | RULE | Public order codes: Parent `PO-…`, Vendor `VO-…`; internal bigint primary keys remain separate (ADR-042). |

---

## 9. Vendor Orders

| ID | Type | Rule |
|----|------|------|
| BR-VO-01 | RULE | Each Vendor Order belongs to exactly one vendor. |
| BR-VO-02 | RULE | Vendors may view/update only their Vendor Orders. |
| BR-VO-03 | RULE | Vendor Order has its own status lifecycle (at least enough to support confirmed/shipped/delivered notifications). |
| BR-VO-04 | OPEN DECISION | Exact Vendor Order statuses (suggested candidate: `pending`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`). |
| BR-VO-05 | OPEN DECISION | Parent Order status derivation from Vendor Order statuses (e.g., all delivered → delivered; any cancelled → partial?). |
| BR-VO-06 | RULE | Shipping fee and shipping state are tracked per Vendor Order. |

---

## 10. Shipping

| ID | Type | Rule |
|----|------|------|
| BR-SHP-01 | RULE | Shipping is associated with Vendor Orders. |
| BR-SHP-02 | RULE | Vendors may have different shipping fees. |
| BR-SHP-03 | RULE | Locations are data-managed (governorates, cities, extensible areas) — not hard-coded city lists in PHP. |
| BR-SHP-04 | RULE | V1 shipping fee is a **configurable flat amount per Vendor Order** (store setting with platform default fallback), in the Vendor Order currency (ADR-042). Not hard-coded in application code. |
| BR-SHP-05 | RULE | Shipping architecture must allow richer rules later without rewriting order placement core. |
| BR-SHP-06 | OPEN DECISION | Who fulfills shipping operationally: vendor self-delivery, platform courier, customer pickup? |

---

## 11. Commissions

| ID | Type | Rule |
|----|------|------|
| BR-COM-01 | RULE | Commission percentage/configuration is stored and configurable — never hard-coded. |
| BR-COM-02 | RULE | A global default commission exists. |
| BR-COM-03 | RULE | A vendor-specific override may replace the global default for that vendor. |
| BR-COM-04 | RULE | Effective commission rate used for a Vendor Order should be **snapshotted** on the order (or commission line) so later config changes do not rewrite history. |
| BR-COM-05 | RULE | Commission **base** for V1 = Vendor Order **item subtotal** (sum of line minor units), **excluding shipping**. Coupons are out of the first Checkout slice (Phase 8) (ADR-042). |
| BR-COM-06 | RULE | Commission rate and amount are **snapshotted at placement**; commission is **recognized** for reporting when the Vendor Order reaches **`delivered`**. No vendor wallet/settlement ledger in V1 (ADR-042). |
| BR-COM-07 | FUTURE | Vendor subscription plans may later coexist with commissions; billing is not V1. |
| BR-COM-08 | OPEN DECISION | Commission type for V1: percentage only vs percentage + optional fixed fee. |

---

## 12. Coupons

| ID | Type | Rule |
|----|------|------|
| BR-CPN-01 | RULE | Coupons may be platform-scoped or vendor-scoped. |
| BR-CPN-02 | RULE | Discount types: percentage and fixed amount. |
| BR-CPN-03 | RULE | Coupons may restrict by product and/or category. |
| BR-CPN-04 | RULE | Coupons support min order amount, max discount, schedule window, global usage limit, per-user usage limit. |
| BR-CPN-05 | RULE | V1: a cart may **not** apply both a platform coupon and a vendor coupon at once — **exactly one coupon code per checkout** (OPEN-007 closed by CPN). |
| BR-CPN-06 | RULE | V1 maximum coupons per checkout = **one** (single code on the Parent place attempt). No platform+per-VO stacking matrix in V1 (OPEN-007 closed by CPN). |
| BR-CPN-07 | RULE | Vendor coupons may discount only that vendor’s eligible items. |
| BR-CPN-08 | RULE | Coupon redemption is recorded and counted toward usage limits inside the checkout transaction. |
| BR-CPN-09 | RULE | Do not over-engineer V1 (no complex promotion engines). |

---

## 13. Payments

| ID | Type | Rule |
|----|------|------|
| BR-PAY-01 | RULE | V1 payment method: Cash on Delivery (COD). |
| BR-PAY-02 | RULE | Payment processing goes through a payment gateway abstraction/interface. |
| BR-PAY-03 | RULE | For COD, create **one Payment record per Vendor Order** (ADR-042). |
| BR-PAY-04 | RULE | COD payment statuses: `pending`, `collected`, `cancelled` (ADR-042). |
| BR-PAY-05 | OPEN DECISION | Operational rule: who collects COD cash (vendor courier vs platform)? This affects settlement but may be outside software V1. |
| BR-PAY-06 | FUTURE | Card/wallet gateways plug into the same abstraction later. |

---

## 14. Reviews

| ID | Type | Rule |
|----|------|------|
| BR-REV-01 | RULE | Only customers who purchased the product may review it. |
| BR-REV-02 | RULE | V1 requires the purchase to sit on a Vendor Order that reached **`delivered`** before review (OPEN-008 closed by REV). |
| BR-REV-03 | RULE | Duplicate reviews prevented per uniqueness rule. |
| BR-REV-04 | RULE | Uniqueness key for V1: **one review per customer per product** (OPEN-009 closed by REV). |
| BR-REV-05 | RULE | Reviews can be moderated by admins. |
| BR-REV-06 | RULE | Vendor replies to reviews are **out of REV V1** (no vendor response entity/UI). |

---

## 15. Cancellations

| ID | Type | Rule |
|----|------|------|
| BR-CAN-01 | OPEN DECISION | Can customers cancel a Parent Order only while all Vendor Orders are still `pending`/`unconfirmed`? |
| BR-CAN-02 | OPEN DECISION | Can a customer cancel one Vendor Order without cancelling others? |
| BR-CAN-03 | OPEN DECISION | Can vendors cancel their Vendor Order? Under what statuses? |
| BR-CAN-04 | RULE | Cancellation restores inventory for cancelled items when stock was decremented/reserved. |
| BR-CAN-05 | RULE | Cancellation releases coupon usage where business-appropriate (same transaction / compensating update). |
| BR-CAN-06 | OPEN DECISION | Partial cancellation effects on Parent Order totals, commission, and shipping. |

---

## 16. Refunds (Future Consideration)

| ID | Type | Rule |
|----|------|------|
| BR-REF-01 | FUTURE | Full refund/settlement ledger is not a core V1 finance module. |
| BR-REF-02 | OPEN DECISION | For COD cancelled before delivery, is “refund” simply “do not collect,” with no payment capture to reverse? |
| BR-REF-03 | FUTURE | After non-COD gateways exist, refund APIs must map through the payment abstraction. |
| BR-REF-04 | RULE | Documentation and architecture must not block a future refund model (payment + order adjustment records). |

---

## 17. Currencies

| ID | Type | Rule |
|----|------|------|
| BR-CUR-01 | RULE | Supported currencies: SYP, USD. |
| BR-CUR-02 | RULE | Each **store** has a default currency code (`stores.default_currency_code`, default SYP) (ADR-033). |
| BR-CUR-03 | RULE | Each **product** has exactly one `currency_code` (inherits store default at creation; may be set to SYP or USD). Variants do **not** store currency; they share the product currency (ADR-033). |
| BR-CUR-04 | RULE | Mixed-currency **checkout placement is allowed without FX conversion**. Parent receipt shows **separate COD dues per currency**; each Vendor Order remains single-currency (ADR-042). Option “forbid mixed carts/orders” is rejected. |
| BR-CUR-05 | RULE | If conversion is used in a later phase, persist `exchange_rate`, source currency, target currency, and converted amounts on the order (or order items) at checkout time. V1 placement under ADR-042 does **not** convert. |
| BR-CUR-06 | RULE | Historical orders do not change when admin updates rates later. |
| BR-CUR-07 | RULE | V1 exchange rates are admin-maintained (no external provider integration). |
| BR-CUR-08 | OPEN DECISION | Customer preferred **display** currency (browse UI). **Charge currency** is not a separate V1 concept under ADR-042 (dues follow line/Vendor Order currencies). |
| BR-CUR-09 | RULE | Catalog monetary amounts use unsigned integer **minor units** with currency exponents (SYP = 0, USD = 2). No floating-point money columns (ADR-033). |
| BR-CUR-10 | RULE | Authoritative listing prices live on variants (`price_amount_minor`; optional `compare_at_amount_minor` for display only). Products do not store authoritative prices (ADR-033). |

---

## 18. Translations

| ID | Type | Rule |
|----|------|------|
| BR-TR-01 | RULE | UI languages: Arabic, English. |
| BR-TR-02 | RULE | Do **not** model translations as `name_ar` / `name_en` columns. |
| BR-TR-03 | RULE | Use translation tables keyed by `(entity_id, locale)` for catalog content listed in ADR-025. |
| BR-TR-04 | RULE | Storefront presentation fallback: requested locale → English → Arabic → stable canonical fallback (ADR-040). Publication requiring both AR/EN names reduces missing-title risk; presenters remain safe for legacy/corrupt rows. Store name stays canonical (not locale-switched) in S8; Store description localization is deferred debt. |
| BR-TR-05 | RULE | Laravel lang files for UI strings; DB translations for catalog/store content. |
| BR-TR-06 | RULE | Arabic requires RTL presentation support. |
| BR-TR-07 | RULE | Locale persistence (P0-8): first visit may use `Accept-Language` if `ar`/`en`, else default **Arabic**; explicit selection in a **cookie**; authenticated users also store preferred locale on the **user profile**, synced with the cookie. Session may cache resolved locale for the request but is not the source of truth. |
| BR-TR-08 | RULE | Entity slugs are canonical and non-localized on the main table (ADR-026). Do not put slugs in translation tables. |

---

## 19. Permissions & Authorization

| ID | Type | Rule |
|----|------|------|
| BR-PERM-01 | RULE | Conceptual roles: Super Admin, Admin, Vendor, Customer. |
| BR-PERM-02 | RULE | Super Admin has full access. |
| BR-PERM-03 | RULE | Admins receive granular permissions for admin modules. |
| BR-PERM-04 | RULE | Vendor permissions are scoped to owned resources. |
| BR-PERM-05 | RULE | Customer permissions are scoped to owned resources. |
| BR-PERM-06 | RULE | Authorization checks occur in Policies/Gates / server-side application services — not only in Blade. |
| BR-PERM-07 | OPEN DECISION | Exact admin permission catalog (users.manage, vendors.approve, products.moderate, …). |
| BR-PERM-08 | RULE | No third-party authz package selected in this phase; native Laravel approach first. |
| BR-PERM-09 | RULE | Super Admin is a **distinct role** (`super_admin`), separate from Admin. Admins use role `admin` plus granular permissions. Super Admin bypasses granular permission checks (P0-5). |

---

## 20. Wishlist

| ID | Type | Rule |
|----|------|------|
| BR-WSH-01 | RULE | Authenticated customers can add/remove products. |
| BR-WSH-02 | RULE | Wishlist items are unique per customer+product (V1; not variant — OPEN-018 closed by WSH). |
| BR-WSH-03 | RULE | V1 wishlist stores **product only** (not a specific variant). Closed by WSH / OPEN-018 (2026-08-24). |

---

## 21. Notifications

| ID | Type | Rule |
|----|------|------|
| BR-NTF-01 | RULE | Customer events: order created, confirmed, shipped, delivered. |
| BR-NTF-02 | RULE | Vendor events: new order; application approved/rejected (and suspension if applicable). |
| BR-NTF-03 | RULE | Admin events: new vendor application; other important system events as defined. |
| BR-NTF-04 | OPEN DECISION | V1 channels: database notifications only, email, and/or SMS? |
| BR-NTF-05 | RULE | Channel set must be extensible via Laravel notification channels. |

---

## 22. Search

| ID | Type | Rule |
|----|------|------|
| BR-SRH-01 | RULE | V1 search/filter uses MySQL. |
| BR-SRH-02 | RULE | S8B/S8C supports keyword + category + brand + store + currency + price + availability + attributes + sorting. Public option dictionaries contain represented entities only; rating filters, ratings, and facet counts remain deferred until Reviews. |
| BR-SRH-03 | RULE | V1 keyword search uses escaped MySQL `LIKE` only (ADR-040). Match AR+EN Product `name` + `short_description`; max 80 chars; user `%`/`_`/`\` are literal; no FULLTEXT/external engine in V1. |
| BR-SRH-04 | RULE | No Elasticsearch (or similar) in V1. |
| BR-SRH-05 | RULE | Public browse pages paginate 24 presented cards and cap the sanitized page at 50 (maximum OFFSET 1,176); malformed page input falls back to page 1. Pagination/chips preserve only effective normalized known criteria, and path Category/Store criteria are never duplicated in query strings. |
| BR-SRH-06 | RULE | Public Product/Category/Store lookup is fail-closed through storefront eligibility scopes. Hidden and unknown resources return 404, not 403. |
| BR-SRH-07 | RULE | Price bounds and price sorting require Currency. Invalid bounds are not presented as active; min > max drops both; removing Currency also removes both bounds and price sorting. Valid-looking unresolved filters remain removable and force an empty result with an accessible issue alert. |
| BR-SRH-08 | RULE | Public Search, filtered browse, and browse page > 1 are `noindex,follow`; Home, Product, and unfiltered first Category/Store pages are `index,follow`. Canonicals use only effective criteria; no JSON-LD or `hreflang` in S8C (ADR-040). |

---

## 23. Syrian Market / Addresses

| ID | Type | Rule |
|----|------|------|
| BR-GEO-01 | RULE | Marketplace serves Syria only. |
| BR-GEO-02 | RULE | Address model supports governorate and city as managed entities. |
| BR-GEO-03 | FUTURE | Area/neighborhood/district as a third address level — **out of V1** (ADR-042). |
| BR-GEO-04 | RULE | Do not hard-code all cities in application code; seed/manage via data. |
| BR-GEO-05 | RULE | Shipping addresses must be within Syria only (ADR-042). |

---

## 24. Media

| ID | Type | Rule |
|----|------|------|
| BR-MED-01 | RULE | Product galleries support multiple ordered images with one primary image (`products.primary_image_id` composite FK — ADR-034, ADR-038). |
| BR-MED-02 | RULE | Stores support logo and banner. |
| BR-MED-03 | RULE | Max 8 images per product; 5 MiB input and normalized master; JPEG/PNG/WebP only; min 400×400; max 6000px/side; max 16MP; optional AR/EN alt translations (ADR-038). |
| BR-MED-04 | RULE | Storage via Laravel filesystem disks (`public` for product images in V1); DB stores relative paths (ADR-013, ADR-034, ADR-038). |
| BR-MED-05 | RULE | No variant-level images in V1; gallery is product-scoped (ADR-034, ADR-038). |

---

## 25. Admin Reporting

| ID | Type | Rule |
|----|------|------|
| BR-RPT-01 | RULE | Admin dashboard shows core stats (orders, users, vendors, GMV-like totals — exact KPIs OPEN DECISION). |
| BR-RPT-02 | OPEN DECISION | Report export formats (CSV only vs PDF). |
| BR-RPT-03 | RULE | Financial figures for historical orders use snapshotted rates/commissions. |

---

## Summary: Critical OPEN DECISIONS for Human Approval

**P0 resolved** (see `docs/p0-decisions.md`): suspension model, one store/vendor, identity, dual Customer+Vendor, Super Admin role, guest cart/checkout, password + email verification, locale persistence.

**Catalog resolved** (see `docs/decisions.md` ADR-022…036, approved 2026-08-12): product `store_id` ownership; category depth/leaf assignment; admin taxonomy; translations + canonical slugs; product statuses including moderation `suspended`; always-variant sellable unit; attributes; SKU per store with composite FK; variant quantity stock; forbid negative stock/backorders; minor-unit money; product-level currency; images; vendor/admin catalog boundaries; soft-delete/archive (no hard-delete UI).

Remaining (post-purchase / Phase 8–10; do not invent):

1. Coupon stacking beyond V1 single-code (OPEN-007 V1 closed by CPN — platform+vendor simultaneous / richer matrices still out)  
2. ~~Review requires delivered vs purchased (OPEN-008)~~ — **V1 closed by REV** (delivered VO required; 2026-08-25)  
3. ~~Review uniqueness (OPEN-009)~~ — **V1 closed by REV** (one per customer+product; 2026-08-25)  
4. Cancellation matrix (OPEN-010) — V1 closed by CAN (see decisions); Phase 9 remainder still open  
5. Notification channels beyond Checkout V1 mail + database minimum (OPEN-013 remainder; SMS later)  
6. ~~Wishlist product vs variant (OPEN-018)~~ — **V1 closed by WSH** (product-level; 2026-08-24)  
7. In-flight orders on vendor suspend (OPEN-017)  
8. Admin KPI/report set (OPEN-020); BR-PAY-05 / BR-SHP-06 operational collector/shipper; BR-CUR-08 display preference; BR-GEO-03 area level; BR-VO-04/05 full status matrices; application re-apply/fields; admin permission catalog; store rating productization (BR-STR-06) / vendor review replies beyond REV V1 boundary  

*(Cart persistence/merge closed → ADR-041. Checkout V1 contract closed → ADR-042 / CHK-0.)*  
