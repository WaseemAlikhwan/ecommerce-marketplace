# Use Cases

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Status:** Draft for stakeholder review  

Conventions:
- Status values and some preconditions reference **OPEN DECISION** items in `business-rules.md`.
- “System” means the marketplace application.

---

## A. Customer Use Cases

### UC-C01 — Register as Customer

| Field | Detail |
|-------|--------|
| **Actor** | Guest |
| **Preconditions** | Guest is not authenticated; email and phone available for registration (P0-3). |
| **Main flow** | 1. Guest opens registration. 2. Submits email, phone, password (min 8, confirmed). 3. System validates uniqueness and password rules. 4. System creates user with Customer capability. 5. System signs the user in (preferred) or prompts login; sends email verification notice. |
| **Alternative flow** | Identifier already used → show error. Validation fails → redisplay form. |
| **Business rules** | BR-CUS-01, BR-CUS-03, BR-CUS-07, BR-CUS-08 |
| **Result** | Customer account exists; user is **not** a vendor. Email may be unverified. |

### UC-C02 — Browse and Search Products

| Field | Detail |
|-------|--------|
| **Actor** | Guest or Customer |
| **Preconditions** | Products exist in a sellable/storefront-visible state (published + sellable store + approved vendor — ADR-028). |
| **Main flow** | 1. Actor opens catalog/search. 2. Enters keyword and/or filters (category, brand, vendor/store, price, availability, attributes; rating deferred). 3. Chooses sort (`newest` default, `name`, `price_asc`, `price_desc` � ADR-040). 4. System returns matching storefront-visible products via MySQL. |
| **Alternative flow** | No results → empty state. Invalid filter → ignore/validate error. |
| **Business rules** | BR-SRH-01..04, BR-PRD-10, BR-PRD-11 |
| **Result** | Actor views product list/detail pages. |

### UC-C03 — Manage Cart

| Field | Detail |
|-------|--------|
| **Actor** | Guest or Customer |
| **Preconditions** | Product **variant** is available (ADR-029). |
| **Main flow** | 1. Add variant with quantity. 2. Update quantities. 3. Remove lines. 4. Cart may include multiple vendors. Guest cart allowed (P0-6). |
| **Alternative flow** | Requested qty > stock → reject or clamp with message. Product inactivated / suspended / store not sellable → remove/block at checkout. |
| **Business rules** | BR-CART-01..06, BR-CUS-05, BR-PRD-10 |
| **Result** | Cart reflects intended purchase set. |

### UC-C04 — Checkout (Multi-Vendor)

| Field | Detail |
|-------|--------|
| **Actor** | Customer |
| **Preconditions** | Authenticated Customer (guest must login/register first — P0-6). Cart non-empty. Valid shipping address in Syria. COD selected. Stock available. |
| **Main flow** | 1. Customer starts checkout (redirect to auth if guest). 2. Confirms **one** Parent shipping address (Syria governorate+city) and payment method (COD). 3. Coupons are **out of the first Checkout vertical slice** (Phase 8 / OPEN-007). 4. System revalidates prices, stock, and per-vendor flat shipping fees. 5. System opens DB transaction. 6. Creates Parent Order, Vendor Orders, Order Items with snapshots. 7. Snapshots commission rates/amounts (item subtotal base; no FX conversion). 8. Decrements inventory. 9. Records COD Payment **per Vendor Order** (`pending`). 10. Commits transaction. 11. Clears cart. 12. Notifies customer and each vendor via **mail + database**. |
| **Alternative flow** | Stock race → fail gracefully, no partial order. Mixed currencies → **allowed**; Parent shows per-currency COD dues (ADR-042). |
| **Business rules** | BR-CHK-*, BR-VO-*, BR-SHP-*, BR-PAY-*, BR-INV-*, BR-CUR-*, BR-COM-*, BR-CUS-05; coupons deferred (BR-CPN-* / Phase 8) |
| **Result** | Parent Order created with one Vendor Order per vendor; public codes `PO-…` / `VO-…`. |

### UC-C05 — Track Orders

| Field | Detail |
|-------|--------|
| **Actor** | Customer |
| **Preconditions** | Customer owns the order. |
| **Main flow** | 1. Customer opens order history. 2. Views Parent Order and nested Vendor Orders/items/statuses. |
| **Alternative flow** | Unauthorized access → denied. |
| **Business rules** | BR-CUS-06, BR-VO-05 |
| **Result** | Customer sees accurate order state. |

### UC-C06 — Cancel Order / Vendor Order

| Field | Detail |
|-------|--------|
| **Actor** | Customer |
| **Preconditions** | Cancellation allowed for current statuses (**OPEN DECISION** matrix). |
| **Main flow** | 1. Customer requests cancellation of Parent Order or a Vendor Order (if allowed). 2. System validates status. 3. Marks cancelled. 4. Restores inventory. 5. Adjusts coupon usage if required. 6. Notifies vendor/customer. |
| **Alternative flow** | Status not cancellable → error. Partial cancel updates Parent aggregates (OPEN DECISION). |
| **Business rules** | BR-CAN-* |
| **Result** | Order (or part) cancelled; stock restored. |

### UC-C07 — Review Product

| Field | Detail |
|-------|--------|
| **Actor** | Customer |
| **Preconditions** | Customer purchased product; preferably related Vendor Order delivered (**OPEN DECISION** if mandatory). No duplicate per uniqueness rule. |
| **Main flow** | 1. Customer submits rating/text. 2. System verifies purchase/delivery eligibility. 3. Saves review. 4. Updates aggregates if applicable. |
| **Alternative flow** | Not eligible → deny. Duplicate → deny. |
| **Business rules** | BR-REV-* |
| **Result** | Review published (or pending moderation — OPEN DECISION). |

### UC-C08 — Manage Wishlist

| Field | Detail |
|-------|--------|
| **Actor** | Customer |
| **Preconditions** | Authenticated; product exists. |
| **Main flow** | Add product; list wishlist; remove product. |
| **Alternative flow** | Duplicate add → idempotent or error (prefer idempotent). |
| **Business rules** | BR-WSH-* |
| **Result** | Wishlist updated. |

### UC-C09 — Switch Language / Currency Display

| Field | Detail |
|-------|--------|
| **Actor** | Guest or Customer |
| **Preconditions** | None. |
| **Main flow** | 1. Actor selects Arabic or English. 2. System persists locale per BR-TR-07 (cookie; profile if authenticated). 3. Optionally select display currency (SYP/USD) if allowed (OPEN — P1). |
| **Alternative flow** | Missing translation → requested locale → English → Arabic → stable canonical fallback (BR-TR-04 / ADR-040). |
| **Business rules** | BR-TR-*, BR-CUR-08 |
| **Result** | UI/content shown in selected locale; display currency rules applied when decided. |

---

## B. Vendor Use Cases

### UC-V01 — Submit Vendor Application

| Field | Detail |
|-------|--------|
| **Actor** | Customer (applicant) |
| **Preconditions** | Authenticated; email verified (P0-7); no conflicting active vendor application/account per rules. |
| **Main flow** | 1. Opens application form. 2. Submits required business details. 3. System creates application `pending`. 4. Notifies admins. |
| **Alternative flow** | Unverified email → deny until verified. Existing pending application → reject new one. Re-apply policy (**OPEN DECISION** BR-APP-07). |
| **Business rules** | BR-APP-*, BR-CUS-08 |
| **Result** | Application awaiting review. |

### UC-V02 — Receive Application Decision

| Field | Detail |
|-------|--------|
| **Actor** | Applicant / System |
| **Preconditions** | Application exists. |
| **Main flow** | Admin approves/rejects → system updates application status (`approved`/`rejected`) → notifies applicant → on approve, grant Vendor capability (Customer capability retained — P0-4). |
| **Alternative flow** | Post-approval suspension is applied to vendor/store (not application status) — P0-1. |
| **Business rules** | BR-APP-04..06, BR-APP-08, BR-VND-01, BR-VND-03 |
| **Result** | Applicant becomes Vendor (and remains Customer) or remains Customer with rejection record. |

### UC-V03 — Set Up / Manage Store

| Field | Detail |
|-------|--------|
| **Actor** | Vendor |
| **Preconditions** | Approved vendor; exactly one store per vendor (P0-2). |
| **Main flow** | Create/update the vendor’s single store profile: name, description, logo, banner, contact info, status. |
| **Alternative flow** | Unapproved/suspended vendor → deny. |
| **Business rules** | BR-STR-* |
| **Result** | Store ready (subject to status) for catalog selling. |

### UC-V04 — Manage Catalog (Products, Variants, Inventory)

| Field | Detail |
|-------|--------|
| **Actor** | Vendor |
| **Preconditions** | Owns store; authorized (approved, non-suspended vendor). |
| **Main flow** | Create product (draft); assign **one leaf** category and optional brand; upload images; define attributes/variants (or default variant for simple products); set SKU/price/stock on variants; set product currency; provide AR/EN names; publish/unpublish/archive per ADR-027. |
| **Alternative flow** | Validation errors; publish blocked if names/leaf category/variant rules incomplete; cannot republish while **suspended** by admin. |
| **Business rules** | BR-PRD-*, BR-CAT-*, BR-ATTR-*, BR-INV-01, BR-CUR-02..03, BR-CUR-09..10 |
| **Result** | Products available according to status and storefront visibility rules. |

### UC-V05 — Manage Vendor Orders

| Field | Detail |
|-------|--------|
| **Actor** | Vendor |
| **Preconditions** | Vendor Order belongs to actor. |
| **Main flow** | List orders; view details; update status (confirm, ship, deliver) per allowed transitions; view shipping fee/status. |
| **Alternative flow** | Access other vendor’s order → deny. Invalid transition → deny. |
| **Business rules** | BR-VO-01..06, BR-SHP-01..02 |
| **Result** | Vendor Order progresses; customer notified on key transitions. |

### UC-V06 — Manage Vendor Coupons

| Field | Detail |
|-------|--------|
| **Actor** | Vendor |
| **Preconditions** | Approved vendor. |
| **Main flow** | Create/update vendor-scoped coupons with limits/restrictions. |
| **Alternative flow** | Invalid schedule/limits → validation errors. |
| **Business rules** | BR-CPN-01..04, BR-CPN-07 |
| **Result** | Vendor coupons available at checkout for eligible carts. |

### UC-V07 — View Sales / Commission Context

| Field | Detail |
|-------|--------|
| **Actor** | Vendor |
| **Preconditions** | Approved vendor. |
| **Main flow** | View own Vendor Orders and amounts including snapshotted commission fields (presentation level TBD). |
| **Alternative flow** | None significant. |
| **Business rules** | BR-COM-04 |
| **Result** | Vendor understands net/commission context for own orders. |

---

## C. Admin Use Cases

### UC-A01 — Review Vendor Application

| Field | Detail |
|-------|--------|
| **Actor** | Admin / Super Admin (with permission) |
| **Preconditions** | Pending application exists. |
| **Main flow** | Open application; approve or reject with optional reason; system updates status; grants vendor on approve; notifies applicant. |
| **Alternative flow** | Concurrent review conflict → last-write/optimistic guard (**OPEN DECISION**). |
| **Business rules** | BR-APP-04..06, BR-PERM-* |
| **Result** | Application resolved. |

### UC-A02 — Manage Users, Vendors, Stores

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Has granular permissions. |
| **Main flow** | Search/list users; suspend/reinstate **vendors/stores** (not application `suspended` status — P0-1); edit constrained fields as allowed. |
| **Alternative flow** | Insufficient permission → deny. |
| **Business rules** | BR-VND-04..05, BR-STR-04..05, BR-PERM-* |
| **Result** | Platform participants governed. |

### UC-A03 — Moderate Catalog

| Field | Detail |
|-------|--------|
| **Actor** | Admin / Super Admin |
| **Preconditions** | Staff access (granular permission catalog still OPEN — BR-PERM-07). |
| **Main flow** | Manage categories/brands; unpublish products; **suspend** violating products (with reason); clear suspension → `unpublished`; archive; recategorize/rebrand; remove images. Do not create products on behalf of vendors. |
| **Alternative flow** | Vendor notify-on-edit remains optional / not required in V1. |
| **Business rules** | BR-PRD-03, BR-PRD-04, BR-PRD-11, BR-CAT-*, BR-BRD-01 |
| **Result** | Catalog quality maintained; product `suspended` is distinct from product `archived`. |

### UC-A04 — Oversee Orders & Payments

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Permission. |
| **Main flow** | View Parent/Vendor Orders; inspect COD payment statuses; intervene (cancel/mark collected) per rules. |
| **Alternative flow** | Illegal status transition → deny. |
| **Business rules** | BR-PAY-*, BR-CAN-*, BR-VO-* |
| **Result** | Operational control over order/payment exceptions. |

### UC-A05 — Configure Commissions

| Field | Detail |
|-------|--------|
| **Actor** | Admin / Super Admin |
| **Preconditions** | Permission. |
| **Main flow** | Set global commission; set vendor override; changes apply to **future** orders only. |
| **Alternative flow** | Invalid percentage → validation error. |
| **Business rules** | BR-COM-01..04 |
| **Result** | Configurable commission schedule without code changes. |

### UC-A06 — Manage Platform Coupons

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Permission. |
| **Main flow** | Create platform coupons with restrictions/limits. |
| **Alternative flow** | Validation failures. |
| **Business rules** | BR-CPN-* |
| **Result** | Platform promotions available. |

### UC-A07 — Moderate Reviews

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Permission. |
| **Main flow** | Hide/remove inappropriate reviews; optionally restore. |
| **Alternative flow** | None significant. |
| **Business rules** | BR-REV-05 |
| **Result** | Review integrity/safety maintained. |

### UC-A08 — Manage Locations, Currencies, Translations Settings

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Permission. |
| **Main flow** | Maintain governorates/cities; maintain FX rates; ensure locale content workflows. |
| **Alternative flow** | Deleting a city in use → prevent or soft-disable (**OPEN DECISION**). |
| **Business rules** | BR-GEO-*, BR-CUR-07, BR-TR-* |
| **Result** | Syria-ready geo and currency reference data. |

### UC-A09 — Dashboard & Reports

| Field | Detail |
|-------|--------|
| **Actor** | Admin |
| **Preconditions** | Permission. |
| **Main flow** | View dashboard KPIs; run basic reports on orders/vendors/products/commissions. |
| **Alternative flow** | Export if approved (**OPEN DECISION**). |
| **Business rules** | BR-RPT-* |
| **Result** | Management visibility. |

### UC-A10 — Manage Admin Permissions

| Field | Detail |
|-------|--------|
| **Actor** | Super Admin (distinct `super_admin` role — P0-5) |
| **Preconditions** | Actor is Super Admin. |
| **Main flow** | Assign Admin role and granular permissions to staff users. Super Admin cannot be self-granted by a normal Admin. |
| **Alternative flow** | Non–Super Admin attempt → deny. |
| **Business rules** | BR-PERM-01..03, BR-PERM-09 |
| **Result** | Controlled admin access. |

---

## D. Cross-Cutting System Use Cases

### UC-S01 — Notify Stakeholders on Domain Events

| Field | Detail |
|-------|--------|
| **Actor** | System |
| **Preconditions** | Domain event occurred (order shipped, application submitted, etc.). |
| **Main flow** | Emit event → notification → deliver via configured channels (queue preferred). |
| **Alternative flow** | Channel failure → retry via queue; log failure. |
| **Business rules** | BR-NTF-* |
| **Result** | Users informed; channels extensible. |

### UC-S02 — Authorize Resource Access

| Field | Detail |
|-------|--------|
| **Actor** | Any authenticated user |
| **Preconditions** | Request targets a protected resource. |
| **Main flow** | Policy/Gate evaluates role + ownership + permissions. |
| **Alternative flow** | Deny → 403. |
| **Business rules** | BR-PERM-*, BR-VND-02, BR-CUS-06 |
| **Result** | Strict isolation across Customer/Vendor/Admin. |

---

## E. Use Case Priority (V1)

**P0 (must):** UC-C01, UC-C02, UC-C03, UC-C04, UC-C05, UC-V01, UC-V02, UC-V03, UC-V04, UC-V05, UC-A01, UC-A04, UC-A05, UC-S01, UC-S02  

**P1:** UC-C07, UC-C08, UC-C06, UC-V06, UC-A02, UC-A03, UC-A06, UC-A07, UC-A08, UC-A09  

**P2:** UC-C09 polish, UC-V07, UC-A10 advanced permission UX, exports  
