# Documentation Consistency Audit

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Audit date:** 2026-08-11  
**P0 sync note:** P0 decisions in `docs/p0-decisions.md` were **approved 2026-08-11** and synchronized into requirements, business-rules, decisions (ADR-014…021), use-cases, architecture, and development-plan. Historical findings below remain as the audit baseline; treat P0-related ERROR/CONTRADICTION rows (C-01, C-02, dual-role, identity, guest checkout, password/locale gaps) as **resolved** unless reintroduced.

**Catalog sync note (2026-08-12):** Catalog Decision Audit recommendations were approved with stakeholder overrides and synchronized as **ADR-022…036**. Treat Catalog-related ERROR/CONTRADICTION and OPEN rows below as updated by this note:

| Audit ID | Post-Catalog-sync status |
|----------|---------------------------|
| C-04 (negative stock OPEN vs RULE) | **Resolved** — forbid negative stock; no backorders (ADR-032) |
| C-05 (decrement RULE vs reserve OPEN) | **Still OPEN** — deferred to Checkout as OPEN-021; Catalog stores on-hand qty only |
| C-11 / BR-PRD-07 / M-03 / M-24 (sellable unit) | **Resolved** — always-variant; cart/order `variant_id` only (ADR-029) |
| OPEN-004 publishing | **Closed** → ADR-027 |
| OPEN-015 brands | **Closed** → ADR-024 |
| OPEN-019 SKU | **Closed** → ADR-031 |
| BR-PRD-09 / M-12 category cardinality/depth | **Resolved** — single leaf; max depth 3 (ADR-023) |
| M-11 attributes | **Resolved** — ADR-030 |
| M-15 / A-06 admin create vendor products | **Resolved** — staff moderate only; no create-on-behalf (ADR-035) |
| M-22 soft-delete (products) | **Resolved for Catalog** — ADR-036; other entities may remain open |
| OPEN-005 mixed-currency checkout | **Remains OPEN** (Catalog currency storage settled in ADR-033) |

Remaining blockers are commerce/post-purchase P1+ (checkout FX, cart persistence, payments, shipping, commissions, coupons, reviews, cancellations, OPEN-021).

**Scope:** Consistency review of planning docs only  
**Constraint honored:** No application code; existing docs not modified at audit time (later sync is a separate pass)


**Documents audited:**
- `docs/requirements.md`
- `docs/business-rules.md`
- `docs/use-cases.md`
- `docs/architecture.md`
- `docs/development-plan.md`
- `docs/decisions.md`

**Finding labels used below:**
- **ERROR / CONTRADICTION** — conflicting statements across docs (or within one doc)
- **MISSING REQUIREMENT** — needed for unambiguous implementation; not invented as a decision here
- **OPEN DECISION** — already marked open, or should be treated as open
- **RECOMMENDATION** — non-binding audit guidance
- **OK** — consistent / acceptable for current phase

---

## Catalog documentation readiness (2026-08-12)

| Checkpoint | Status |
|------------|--------|
| Catalog ownership / taxonomy / publish / always-variant / SKU / stock / money ADRs exist | OK (ADR-022…036) |
| C-04 closed consistently across requirements + business-rules | OK |
| C-05 / OPEN-021 left open for Checkout | OK |
| Mixed-currency checkout left open | OK |
| Always-variant wording (no product-or-variant ambiguity) | OK |
| Canonical non-localized slugs documented | OK |
| Product `suspended` moderation state distinct from `archived` | OK |
| DB-enforced variant `store_id` / SKU integrity documented | OK |
| Currency once on product; prices on variants | OK |
| Ready for first Catalog **implementation** slice (after explicit code request) | **YES** — documentation gate closed; no code started in this sync |

---

## 1. Executive Summary

The documentation set is broadly aligned on the core architecture (Parent Order → Vendor Orders → Items, variant inventory, translation tables, payment abstraction, MySQL search, native Laravel authz). Accepted ADRs are internally coherent.

However, the set is **not yet Phase-1 ready**. Several items are presented as settled requirements while still marked OPEN; order/status/payment/currency rules remain incomplete; Phase 7 checkout assumes commissions/coupons behaviors that Phase 8 owns; and multiple schema-shaping decisions are unresolved.

**Readiness verdict:** Suitable as a planning baseline; **not ready** to freeze for database design or Laravel scaffolding until P0 items in §11 / final priority list are resolved.

| Area | Verdict |
|------|---------|
| Core multi-vendor order model | OK |
| Translation / search / payment abstraction ADRs | OK |
| Application status `suspended` vs vendor suspension | ERROR / CONTRADICTION |
| “One store per vendor” stated as scope + still OPEN | ERROR / CONTRADICTION |
| Order lifecycle “defined” vs actually OPEN | ERROR / CONTRADICTION |
| Negative stock RULE vs OPEN wording | ERROR / CONTRADICTION |
| Phase 7 vs Phase 8 commission/coupon ownership | ERROR / CONTRADICTION (dependency) |
| Identity, currency checkout, payment FK, store cardinality | OPEN DECISION (DB blockers) |
| Admin permission catalog / dual-role account | OPEN DECISION (authz blockers) |
| Password policy, locale persistence, no-variant products, coupon allocation math | MISSING REQUIREMENT |

---

## 2. Document Inventory

| File | Role | Status in set | Cross-links |
|------|------|---------------|-------------|
| `requirements.md` | FR/NFR, scope, out-of-scope | Draft | Points to all others |
| `business-rules.md` | RULE / OPEN / FUTURE rules | Draft | Primary rule catalog; referenced heavily by use cases |
| `use-cases.md` | Actor flows | Draft | References `BR-*` IDs; assumes open decisions |
| `architecture.md` | Laravel layering & seams | Draft | Aligns with ADRs; some extra OPENs not numbered in `decisions.md` |
| `development-plan.md` | Phased delivery | Draft | Depends on Phase 0 decision freeze |
| `decisions.md` | ADR + OPEN log | Living draft | Should be source of truth for accepted vs open |

**OK:** All six files exist and cover the intended planning surface.  
**RECOMMENDATION:** Treat `decisions.md` + `business-rules.md` as the freeze targets after this audit; sync `requirements.md` wording afterward so scope tables do not look decided when they are not.

---

## 3. Contradictions

| ID | Severity | Type | Finding | Evidence | Impact |
|----|----------|------|---------|----------|--------|
| C-01 | High | ERROR / CONTRADICTION | Application statuses must include `suspended`, but suspension is recommended **not** to live on applications | `requirements.md` FR-VND-02; `business-rules.md` BR-APP-02 (RULE); vs `decisions.md` OPEN-003 recommendation (`pending/approved/rejected` on applications; suspend vendor/store) | Status enum / state machine / admin UI cannot be designed consistently |
| C-02 | High | ERROR / CONTRADICTION | Scope table states “One store per approved vendor” while store cardinality remains OPEN | `requirements.md` §2.1; BR-STR-02; OPEN-001 | Vendor–store relationship (1:1 vs 1:n) ambiguous for ERD |
| C-03 | High | ERROR / CONTRADICTION | Requirements claim order lifecycle **is defined** in business rules; statuses/derivation are OPEN | FR-ORD-04 vs BR-VO-04, BR-VO-05 | Parent/Vendor order status columns and transitions undefined |
| C-04 | Medium | ERROR / CONTRADICTION | Negative stock: requirements mark OPEN; business rules mark RULE (forbid) | FR-INV-02 vs BR-INV-05 | **RESOLVED 2026-08-12** — ADR-032; FR-INV-02 and BR-INV-05/07 forbid negative stock and backorders |
| C-05 | Medium | ERROR / CONTRADICTION | Inventory decrement timing stated as RULE while reserve-vs-decrement remains OPEN | BR-INV-02 vs BR-INV-03 | **OPEN / DEFERRED** — OPEN-021 Checkout; BR-INV-02 clarified that Catalog only stores on-hand qty |
| C-06 | High | ERROR / CONTRADICTION | Phase 7 places **commission snapshots** in checkout; Phase 8 owns commission configuration; UC-C04 also applies coupons in checkout while coupons are Phase 8 | `development-plan.md` Phases 7–8; UC-C04 | Phase 7 either needs commission/coupon primitives earlier, or checkout scope must be narrowed |
| C-07 | Medium | ERROR / CONTRADICTION | Architecture order items mention **tax** snapshots; requirements put automated tax engines out of scope with no V1 tax rule | `architecture.md` §5.3; `requirements.md` §3 | Unknown whether `tax` fields exist |
| C-08 | Low | ERROR / CONTRADICTION | FR-VND-06 says notify “Vendors” on application lifecycle; BR-APP-09 says notify **applicants** and admins | FR-VND-06 vs BR-APP-09 | Notification recipient model wording drift (applicant ≠ vendor until approval) |
| C-09 | Medium | ERROR / CONTRADICTION | Use-case priority marks UC-A08 (geo/currency admin) as P1, but Phase 3 needs geo/FX foundations earlier | `use-cases.md` §E vs Phase 3 | Priority list misaligned with dependency reality |
| C-10 | Low | ERROR / CONTRADICTION | Phase 0 exit lists many late-phase decisions as required before any coding, while Phase 1 only declares dependency on identity | Phase 0 vs Phase 1 dependency text | Unclear which OPENs truly gate scaffolding vs later phases |
| C-11 | Low | OPEN DECISION / inconsistency | ADR-003 “pending confirmation” that base product stock is unused when variants exist; BR-PRD-07 still OPEN | ADR-003 note; BR-PRD-07 | **RESOLVED 2026-08-12** — ADR-029 always-variant |

**OK (non-contradictions worth noting):**
- Parent/Vendor/Item model consistent across requirements, architecture, decisions, use cases.
- Shipping-on-Vendor-Order consistent (ADR-002 / FR-SHP-01 / BR-SHP-01).
- No search engine in V1 consistent everywhere.
- COD + payment abstraction consistent (ADR-005).
- Translation-table strategy consistent (ADR-004 / FR-I18N-02 / BR-TR-02).

---

## 4. Missing Business Rules

Rules referenced or implied but **not actually defined** as actionable RULE text:

| ID | Type | Gap | Where implied | Why it matters |
|----|------|-----|---------------|----------------|
| M-01 | MISSING REQUIREMENT | Password policy (length, complexity, breach checks) | UC-C01 “password rules” | Auth validation cannot be specified |
| M-02 | MISSING REQUIREMENT | Locale persistence approach (session, user preference, cookie, Accept-Language) | FR-I18N-03 “per documented approach” — approach absent | i18n middleware design |
| M-03 | MISSING REQUIREMENT | Products **without** variants: where is stock/SKU/price authoritative? | FR-INV-01 / ADR-003 only cover “when variants exist” | Catalog/inventory schema fork |
| M-04 | MISSING REQUIREMENT | Parent Order status set and transition rules | FR-ORD-04; only Vendor Order candidates in BR-VO-04 | Customer order UI + aggregation |
| M-05 | MISSING REQUIREMENT | Exact Vendor Order transition matrix (who may move which status → which) | UC-V05; BR-VO-03 | Vendor panel + notifications |
| M-06 | MISSING REQUIREMENT | Platform coupon allocation across multi-vendor lines (pro-rata? eligible subset only?) | FR-CPN-*; BR-CPN-*; UC-C04 | Discount math / vendor order totals |
| M-07 | MISSING REQUIREMENT | Coupon `min_order_amount` basis: Parent total vs vendor subtotal vs eligible items | BR-CPN-04 | Validation at checkout |
| M-08 | MISSING REQUIREMENT | Whether discounted unit prices are snapshotted on order items vs order-level discount rows | architecture mentions discount snapshots; no rule | Order schema + refunds later |
| M-09 | MISSING REQUIREMENT | Store “contact information” structure (phone, email, WhatsApp, address fields) | FR-STR-02; BR-STR-03 | Store table columns |
| M-10 | MISSING REQUIREMENT | Customer profile fields beyond “profile and addresses” | BR-CUS-02 | Registration/profile forms |
| M-11 | MISSING REQUIREMENT | Attribute/variant modeling rules (global attributes vs per-vendor; required option sets) | FR-PRD-02/03 | Attribute tables |
| M-12 | MISSING REQUIREMENT | Category hierarchy depth / leaf-only product assignment | FR-CAT-01 OPEN depth; no rule | Category UX + constraints |
| M-13 | MISSING REQUIREMENT | Who may mark COD payment `collected` / `failed` | UC-A04; BR-PAY-04 OPEN | Payment authz + status writes |
| M-14 | MISSING REQUIREMENT | Customer edit/delete of own reviews after submit | FR-REV-*; UC-C07 | Review policy |
| M-15 | MISSING REQUIREMENT | Whether admins may create/edit products on behalf of a vendor | FR-PRD-03 “manage/moderate” vague | Admin catalog tools |
| M-16 | MISSING REQUIREMENT | Behavior when shipping calculator cannot serve an address for one vendor in a multi-vendor cart | FR-SHP-*; UC-C04 | Checkout partial failure policy |
| M-17 | MISSING REQUIREMENT | Double-submit / idempotent checkout protection | UC-C04 atomicity only | Duplicate Parent Orders risk |
| M-18 | MISSING REQUIREMENT | Rounding rules for FX and percentage discounts | FR-CUR-*; FR-CPN-* | Deterministic totals |
| M-19 | MISSING REQUIREMENT | Email verification requirement | `architecture.md` lists as OPEN; not in `decisions.md` OPEN list | Auth schema/flow |
| M-20 | MISSING REQUIREMENT | Auto-login after registration | UC-C01 OPEN; not in `decisions.md` | Auth UX |
| M-21 | MISSING REQUIREMENT | Review default visibility (published immediately vs pending moderation) | UC-C07 result OPEN; not numbered in decisions | Review workflow |
| M-22 | MISSING REQUIREMENT | Soft-delete entity set | architecture OPEN; not in decisions OPEN list | Migrations |
| M-23 | MISSING REQUIREMENT | Important “system events” for admin notifications beyond vendor applications | FR-NTF-03 | Notification catalog |
| M-24 | MISSING REQUIREMENT | Cart line identity: always variant_id, or product_id when no variants? | BR-CART-02 | Cart schema |

**Referenced BR IDs:** Use-case `BR-*` references resolve to existing IDs or intentional wildcards (`BR-CHK-*`, etc.). No ghost BR IDs found.  
**Gap type:** many wildcards include OPEN rows, so “referenced” ≠ “defined enough to implement.”

---

## 5. Critical Open Decisions

Mapped to implementation blocking power:

### 5.1 Already numbered in `decisions.md`

| OPEN | Topic | Blocks |
|------|-------|--------|
| OPEN-001 | Stores per vendor | DB relationship Vendor–Store; Phase 4 |
| OPEN-002 | Customer+Vendor same account | Roles model; Phase 2 authz |
| OPEN-003 | Meaning of `suspended` | Status enums; Phase 4 |
| OPEN-004 | Product publishing moderation | Product status workflow; Phase 5 | **Closed → ADR-027** |
| OPEN-005 | Multi-currency checkout policy | Order money columns; Phase 7 |
| OPEN-006 | Commission base + COD recognition timing | Commission fields/reports; Phases 7–8 |
| OPEN-007 | Coupon stacking | Checkout/coupon schema logic; Phase 8 (and UC-C04) |
| OPEN-008 | Review requires delivered? | Review eligibility; Phase 9 |
| OPEN-009 | Review uniqueness key | Unique index; Phase 9 |
| OPEN-010 | Cancellation matrix | Order services; Phases 7/9 |
| OPEN-011 | Payment at Parent vs Vendor Order | `payments` FK; Phase 7 |
| OPEN-012 | V1 shipping fee algorithm | Shipping tables; Phase 7 |
| OPEN-013 | Notification channels | Infra/mail; Phase 4+ |
| OPEN-014 | Guest cart/checkout | Cart/auth flows; Phases 1/6/7 | **Closed → ADR-019** |
| OPEN-015 | Brand ownership | `brands` schema; Phase 5 | **Closed → ADR-024** |
| OPEN-016 | Email and/or phone identity | `users` schema; **Phase 1** | **Closed → ADR-016** |
| OPEN-017 | In-flight orders on vendor suspend | Order ops; Phases 4/7 |
| OPEN-018 | Wishlist product vs variant | Wishlist FK; Phase 6 |
| OPEN-019 | SKU uniqueness scope | Unique indexes; Phase 5 | **Closed → ADR-031** |
| OPEN-020 | Admin KPIs/exports | Phase 10 only |
| OPEN-021 | Inventory reserve vs decrement | Checkout stock mutation; Phase 7 | **OPEN (added 2026-08-12)** |
### 5.2 Open in business rules / architecture but weak or missing in `decisions.md`

| Item | Type | Notes |
|------|------|-------|
| BR-CHK-06 shipping address parent vs per vendor order | OPEN DECISION | Schema for address snapshots |
| BR-CHK-07 order numbering | OPEN DECISION | Can be P2 |
| BR-CART-04/05 cart persistence & merge | OPEN DECISION | Cart tables vs session |
| BR-PRD-07/09 variant-only stock; category cardinality | OPEN DECISION | Catalog schema |
| BR-GEO-03 area/neighborhood level | OPEN DECISION | Address schema |
| BR-PERM-07 admin permission catalog | OPEN DECISION | Phase 2 completeness |
| BR-PERM-09 Super Admin representation | OPEN DECISION | Phase 2 |
| BR-COM-08 percentage vs fixed fee | OPEN DECISION | Commission settings schema |
| BR-PAY-04 payment status lifecycle | OPEN DECISION | Payments table |
| BR-APP-07/10 re-apply policy; application fields | OPEN DECISION | Application table |
| BR-STR-04 store statuses | OPEN DECISION | Store enum |
| Soft deletes set; email verification; UI kit | OPEN DECISION | Architecture-only today |

---

## 6. Database Design Blockers

Cannot finalize a stable ERD until these are answered:

| Blocker | Type | Why blocking | Related |
|---------|------|--------------|---------|
| User identity columns (email/phone/both) | OPEN DECISION | Primary unique keys for `users` | OPEN-016 |
| Role model for dual Customer/Vendor | OPEN DECISION | `roles` / capability tables | OPEN-002 |
| Super Admin storage model | OPEN DECISION | flag vs role vs both | BR-PERM-09 |
| Vendor–Store cardinality | OPEN DECISION | `stores.vendor_id` unique or not | OPEN-001 |
| Suspension fields location | ERROR / CONTRADICTION + OPEN | Which tables get `suspended` | C-01, OPEN-003 |
| Application status enum | ERROR / CONTRADICTION | Include `suspended` or not | FR-VND-02 vs OPEN-003 |
| Sellable unit model (variant-only vs product-or-variant) | MISSING REQUIREMENT / OPEN | FKs on cart/order_items/inventory | M-03, M-24, BR-PRD-07 |
| Product–category cardinality | OPEN DECISION | pivot vs single FK | BR-PRD-09 |
| Brand `vendor_id` nullability | OPEN DECISION | ownership | OPEN-015 |
| SKU unique scope | OPEN DECISION | unique index definition | OPEN-019 |
| Cart persistence | OPEN DECISION | presence of `carts` tables | BR-CART-04 |
| Address levels | OPEN DECISION | geo tables | BR-GEO-03 |
| Payment FK target | OPEN DECISION | `payments.order_id` shape | OPEN-011 |
| Money/FX column layout | OPEN DECISION | single currency vs dual amounts per line | OPEN-005 |
| Shipping rule tables | OPEN DECISION | flat vs city rates schema | OPEN-012 |
| Commission settings shape | OPEN DECISION | % only vs %+fixed; recognition fields | OPEN-006, BR-COM-08 |
| Review unique constraint | OPEN DECISION | index columns | OPEN-009 |
| Wishlist target FK | OPEN DECISION | product vs variant | OPEN-018 |
| Coupon stacking/redemption shape | OPEN DECISION | how many redemptions per order | OPEN-007 |
| Order/payment/store/application status enums | OPEN DECISION / CONTRADICTION | enum columns | C-03, BR-VO-04, BR-STR-04, BR-PAY-04 |
| Tax columns | ERROR / CONTRADICTION | include or omit | C-07 |
| Translation entity list completeness | MISSING REQUIREMENT | which entities get `*_translations` | architecture examples only |
| Attribute/option tables | MISSING REQUIREMENT | variant structure | M-11 |
| Audit/status history tables | MISSING REQUIREMENT | NFR-AUD-01 vague | M-22 related |

**OK for ERD sketching (non-blocking concepts):** Parent Order, Vendor Order, Order Item hierarchy; variant inventory direction; translation-table pattern; payment interface (not table FK); commission snapshot **concept**; Redis/MySQL/Docker infra.

---

## 7. Authorization & Security Gaps

| ID | Type | Gap | Risk |
|----|------|-----|------|
| A-01 | OPEN DECISION | Dual-role account (OPEN-002) undefined | Wrong middleware/panel access model |
| A-02 | OPEN DECISION | Admin permission catalog missing (BR-PERM-07) | Phase 2 can only stub gates |
| A-03 | OPEN DECISION | Who may suspend vendors (BR-VND-04) | Privilege escalation ambiguity |
| A-04 | MISSING REQUIREMENT | Suspended vendor panel capabilities (read-only orders vs full lockout) | Over/under-blocking vendor access |
| A-05 | MISSING REQUIREMENT | Who marks COD collected/failed | Payment integrity |
| A-06 | MISSING REQUIREMENT | Admin create/edit vendor-owned catalog boundaries | Ownership violations or blocked ops |
| A-07 | MISSING REQUIREMENT | Customer review edit/delete rights | Data integrity / abuse |
| A-08 | MISSING REQUIREMENT | Vendor coupon admin oversight (can admin CRUD vendor coupons?) | FR-ADM-02 “coupons” vague on scope |
| A-09 | OPEN DECISION | Concurrent application review conflict handling (UC-A01) | Race on approve/reject |
| A-10 | MISSING REQUIREMENT | Whether Vendor role alone implies storefront Customer privileges | Related to OPEN-002 |
| A-11 | OK | Vendor isolation principle stated consistently | FR-RBAC-03, BR-VND-02, policies |
| A-12 | OK | Customer ownership principle stated consistently | FR-RBAC-04, BR-CUS-06 |
| A-13 | OK | Policies/Gates required server-side | FR-RBAC-05, ADR-008 |
| A-14 | MISSING REQUIREMENT | Rate-limit specifics (thresholds) beyond architecture note | Abuse resistance measurable only later |
| A-15 | OPEN DECISION | Email verification | Account takeover / fake vendors surface |

**RECOMMENDATION:** Before Phase 2, publish a permission matrix: rows = actions, columns = Guest / Customer / Vendor / Admin / Super Admin, with ownership predicates.

---

## 8. Checkout & Order Lifecycle Gaps

| ID | Type | Edge case / gap | Notes |
|----|------|-----------------|-------|
| O-01 | OPEN DECISION | Mixed SYP/USD cart policy | OPEN-005 |
| O-02 | OPEN DECISION | Charge/display currency for COD | BR-CUR-08 |
| O-03 | OPEN DECISION | One shipping address vs per Vendor Order | BR-CHK-06 |
| O-04 | OPEN DECISION | Shipping fee algorithm + unsailable destination for one vendor | OPEN-012 + M-16 |
| O-05 | OPEN DECISION | Payment record granularity | OPEN-011 |
| O-06 | OPEN DECISION | Payment status lifecycle | BR-PAY-04 |
| O-07 | OPEN DECISION | Commission base & recognition timing | OPEN-006 |
| O-08 | OPEN DECISION | Coupon stacking & per-checkout caps | OPEN-007 |
| O-09 | MISSING REQUIREMENT | Platform coupon split across vendors | M-06 |
| O-10 | MISSING REQUIREMENT | Min-order basis for coupons | M-07 |
| O-11 | MISSING REQUIREMENT | Rounding of FX and discounts | M-18 |
| O-12 | MISSING REQUIREMENT | Idempotent checkout / double submit | M-17 |
| O-13 | OPEN DECISION | Reserve vs decrement stock | BR-INV-03 (conflicts with BR-INV-02 wording) |
| O-14 | OK | Atomic failure on critical validation | BR-CHK-04 |
| O-15 | OK | Revalidate price at checkout | BR-CART-06 |
| O-16 | MISSING REQUIREMENT | Price change UX (warn vs silent adjust vs block) | Only “revalidate” stated |
| O-17 | RULE + OPEN | Cancel restores inventory; when cancel is allowed still OPEN | BR-CAN-04 vs OPEN-010 |
| O-18 | OPEN DECISION | Partial Vendor Order cancel effects on parent totals/commission/shipping | BR-CAN-06 |
| O-19 | OPEN DECISION | COD cancel-before-delivery “refund” meaning | BR-REF-02 |
| O-20 | ERROR / CONTRADICTION | Parent/Vendor status model claimed defined but not | C-03 |
| O-21 | MISSING REQUIREMENT | Legal transitions after `shipped` / `delivered` (e.g., cancel forbidden?) | implied only |
| O-22 | OPEN DECISION | Vendor suspend during in-flight fulfillment | OPEN-017 |
| O-23 | MISSING REQUIREMENT | Checkout when store/product becomes inactive after cart add | BR-PRD-10 partial; needs explicit fail behavior |
| O-24 | MISSING REQUIREMENT | Commission snapshot on fully cancelled Vendor Order (keep vs zero) | reports integrity |
| O-25 | Phase issue | UC-C04 includes coupons; Phase 7 checkout precedes Phase 8 coupons | C-06 |
| O-26 | OPEN DECISION | Review gate relative to `delivered` | OPEN-008 |
| O-27 | MISSING REQUIREMENT | Whether shipping currency equals item currency | multi-currency + shipping |

---

## 9. Phase Dependency Issues

| ID | Type | Issue | Recommendation |
|----|------|-------|----------------|
| P-01 | ERROR / CONTRADICTION | Phase 7 snapshots commissions before Phase 8 commission config | Move minimal global commission settings into Phase 7 (or earlier Phase 3/4 settings), keep admin UX polish in Phase 8; **or** Phase 7 snapshots a temporary default only with explicit note |
| P-02 | ERROR / CONTRADICTION | UC-C04 checkout applies coupons; coupons implemented Phase 8 | Narrow Phase 7 checkout to “no coupons” **or** pull basic coupon redeem into Phase 7 |
| P-03 | OPEN DECISION / plan gap | Phase 4 uses notifications before OPEN-013 resolved | Require OPEN-013 in Phase 0 subset for Phase 4 |
| P-04 | OPEN DECISION / plan gap | Phase 2 builds roles before OPEN-002 / BR-PERM-09 resolved | Gate Phase 2 on OPEN-002 + Super Admin model |
| P-05 | OPEN DECISION / plan gap | Phase 5 depends on publishing OPEN-004 but not OPEN-015 brands or OPEN-019 SKU | Add OPEN-015, OPEN-019 (and BR-PRD-07/09) to Phase 5 dependencies |
| P-06 | OPEN DECISION / plan gap | Phase 6 depends on guest cart but not OPEN-018 wishlist target / BR-CART-04 persistence | Add those dependencies |
| P-07 | ERROR / CONTRADICTION | Phase 0 lists cancellation/review/coupon/commission as coding gate, while Phase 1 only needs identity | Split Phase 0 into **P0a (scaffold gates)** and **P0b (checkout gates)** to avoid false “all or nothing” |
| P-08 | MISSING REQUIREMENT | Phase 7 lists status transitions but OPEN-010 cancellation matrix deferred to Phase 9 | Define which transitions are in Phase 7 vs 9 explicitly |
| P-09 | OK | Phase 3 geo/FX before checkout | Sound |
| P-10 | OK | Vendor onboarding before catalog | Sound |
| P-11 | Low | Use-case P1 for UC-A08 vs Phase 3 need | Adjust use-case priority or add “Phase 3 admin seeds” note |
| P-12 | Low | Stream C starts after Phase 5 — good — but shared order money schema needs OPEN-005 before Stream C | Call out in plan |

---

## 10. Recommended Resolution Order

Resolve in this sequence to unblock design without inventing product answers here:

1. **Reconcile contradictions C-01, C-02, C-03, C-04, C-05** (status models, store cardinality, inventory wording) in `requirements.md` / `business-rules.md` / `decisions.md`.
2. **Answer scaffold/authz OPENs:** OPEN-016, OPEN-002, OPEN-014 (checkout login part), BR-PERM-09, BR-PERM-07 (at least V1 permission list draft).
3. **Answer onboarding/catalog OPENs:** OPEN-001, OPEN-003, OPEN-004, OPEN-015, OPEN-019, BR-PRD-07, BR-PRD-09, BR-STR-04, BR-APP-10.
4. **Answer commerce OPENs:** OPEN-005, OPEN-011, OPEN-012, BR-CHK-06, BR-PAY-04, OPEN-006, BR-COM-08, OPEN-007, BR-CART-04, BR-INV-03.
5. **Fix development plan phasing** for commissions/coupons vs checkout (C-06 / P-01 / P-02).
6. **Answer post-purchase OPENs:** OPEN-010, OPEN-008, OPEN-009, OPEN-017, BR-REF-02.
7. **Fill MISSING REQUIREMENT stubs** (password policy, locale persistence, no-variant products, coupon allocation, rounding, idempotent checkout, tax yes/no).
8. **Then** produce ERD / schema doc (still documentation), then Phase 1 coding.

---

## 11. Final Documentation Readiness Checklist

| # | Checkpoint | Status |
|---|------------|--------|
| 1 | FR/NFR/scope/out-of-scope exist | OK |
| 2 | Accepted ADRs cover core architecture themes | OK |
| 3 | OPEN decisions collected in `decisions.md` | OK (partial — some architecture OPENs not numbered) |
| 4 | No major silent product inventions in ADRs | OK |
| 5 | Requirements wording matches OPEN status (no false “defined”) | ERROR / CONTRADICTION — fail (C-02, C-03, C-01) |
| 6 | Business rule types consistent (RULE vs OPEN) | ERROR / CONTRADICTION — fail (C-04, C-05) |
| 7 | Use cases reference real BR IDs | OK |
| 8 | Use cases implementable without open blockers | Fail — many UC depend on OPEN |
| 9 | Phase plan dependencies match decision timing | Fail — C-06, P-01..P-07 |
| 10 | ERD can be drawn without guessing cardinality/enums/FKs | Fail — §6 blockers |
| 11 | Authz matrix complete for four roles | Fail — §7 |
| 12 | Checkout edge cases explicitly ruled | Fail — §8 |
| 13 | Ready for Phase 1 Laravel scaffolding | **Not ready** until P0 list below resolved |
| 14 | Ready for Phase 7 checkout implementation | **Not ready** until P0+P1 commerce items resolved |

---

## Prioritized List

### P0 — Must resolve before Phase 1

| ID | Type | Item |
|----|------|------|
| P0-1 | ERROR / CONTRADICTION | Fix C-01 application `suspended` vs vendor/store suspension; update FR-VND-02 / BR-APP-02 or accept OPEN-003 and rewrite requirements |
| P0-2 | ERROR / CONTRADICTION | Fix C-02: either accept one-store-per-vendor as ADR or remove “one store” from in-scope table until OPEN-001 decided |
| P0-3 | OPEN DECISION | OPEN-016 identity (email/phone/both) |
| P0-4 | OPEN DECISION | OPEN-002 Customer+Vendor same account |
| P0-5 | OPEN DECISION | BR-PERM-09 Super Admin representation |
| P0-6 | OPEN DECISION | OPEN-014 at least for **checkout auth requirement** (guest browse/cart may wait to Phase 6, but registration/auth screens need clarity) |
| P0-7 | MISSING REQUIREMENT | Password policy + whether email verification is required (M-01, M-19) |
| P0-8 | MISSING REQUIREMENT | Locale persistence approach (M-02) |
| P0-9 | RECOMMENDATION | Split Phase 0 into scaffold gates vs commerce gates (P-07) so Phase 1 is not falsely blocked by Phase 7 decisions |

### P1 — Must resolve before affected implementation phase

| ID | Type | Item | Before phase |
|----|------|------|--------------|
| P1-1 | OPEN DECISION | OPEN-001 store cardinality; OPEN-003 suspension model; BR-APP-10 fields; BR-STR-04 statuses | Phase 4 |
| P1-2 | OPEN DECISION | OPEN-013 notification channels | Phase 4 |
| P1-3 | OPEN DECISION | BR-PERM-07 admin permission catalog (usable V1 list) | Phase 2 (complete) / Phase 10 |
| P1-4 | OPEN DECISION | OPEN-004 publishing; OPEN-015 brands; OPEN-019 SKU; BR-PRD-07; BR-PRD-09 | Phase 5 | **CLOSED 2026-08-12** → ADR-022…036 |
| P1-5 | MISSING REQUIREMENT | No-variant product inventory/SKU/price rule (M-03); attribute model (M-11) | Phase 5 | **CLOSED 2026-08-12** → ADR-029/030 |
| P1-6 | OPEN DECISION | BR-CART-04/05; OPEN-018 wishlist | Phase 6 |
| P1-7 | OPEN DECISION | OPEN-014 guest cart behavior (if guest cart in V1) | Phase 6 | Guest policy closed in P0/ADR-019; persistence still OPEN |
| P1-8 | OPEN DECISION | OPEN-005, OPEN-011, OPEN-012, BR-CHK-06, BR-PAY-04, BR-INV-03 / OPEN-021 | Phase 7 |
| P1-9 | ERROR / CONTRADICTION | Resolve C-03/C-06: define Parent+Vendor statuses for Phase 7; fix commission/coupon phase ownership | Phase 7 |
| P1-10 | OPEN DECISION | OPEN-006, BR-COM-08, OPEN-007; missing coupon allocation/min-order basis (M-06, M-07) | Phase 8 (or Phase 7 if coupons moved earlier) |
| P1-11 | OPEN DECISION | OPEN-010, OPEN-008, OPEN-009, OPEN-017; BR-REF-02 | Phase 9 |
| P1-12 | ERROR / CONTRADICTION | C-04/C-05 inventory RULE vs OPEN wording cleanup | Phase 5–7 | **C-04 closed;** **C-05 → OPEN-021 Checkout** |
| P1-13 | ERROR / CONTRADICTION | C-07 tax field yes/no | Phase 7 schema |
| P1-14 | MISSING REQUIREMENT | Checkout idempotency, price-change UX, rounding (M-16–M-18, O-16) | Phase 7 |
| P1-15 | MISSING REQUIREMENT | Authz gaps A-04..A-08 payment/review/admin catalog boundaries | Phases 2/7/9 |

### P2 — Can be decided during implementation without blocking architecture

| ID | Type | Item |
|----|------|------|
| P2-1 | OPEN DECISION | OPEN-020 admin KPI/export details |
| P2-2 | OPEN DECISION | BR-CHK-07 order numbering scheme |
| P2-3 | OPEN DECISION | BR-SRH-03 LIKE vs FULLTEXT |
| P2-4 | OPEN DECISION | BR-RPT-02 export formats |
| P2-5 | OPEN DECISION | BR-REV-06 vendor review responses |
| P2-6 | CLOSED | BR-MED-03 exact image limits → ADR-038 |
| P2-7 | OPEN DECISION | BR-GEO-03 area/neighborhood (can ship with governorate+city only) |
| P2-8 | OPEN DECISION | BR-SHP-06 operational who-delivers (document assumption; software can stay fee-only) |
| P2-9 | OPEN DECISION | BR-PAY-05 who physically collects COD cash (ops) |
| P2-10 | OPEN DECISION | BR-TR-04 fallback locale (default English acceptable) |
| P2-11 | OPEN DECISION | Soft-delete set finalization |
| P2-12 | OPEN DECISION | UI kit (Tailwind vs Bootstrap) |
| P2-13 | MISSING REQUIREMENT | Auto-login after register (M-20) — UX preference |
| P2-14 | MISSING REQUIREMENT | Exact rate-limit numbers (A-14) |
| P2-15 | RECOMMENDATION | Sync use-case priority list with phase dependencies (C-09) |

---

## Cross-Document Requirement Drift (Quick Index)

| Topic | requirements | business-rules | use-cases | decisions | development-plan | Audit |
|-------|--------------|----------------|-----------|-----------|------------------|-------|
| One store/vendor | RULE (P0-2) | RULE | Assumes store exists | ADR-015 | Phase 4 done | **RESOLVED** (P0) |
| Application `suspended` | Not an app status | RULE apps pending/approved/rejected | UC-A02 vendor/store suspend | ADR-014 | Phase 4 | **RESOLVED** (P0) |
| Order statuses | “Defined” | OPEN | Uses confirm/ship/deliver | Not accepted ADR | Phase 7/9 | CONTRADICTION (checkout) |
| Negative stock | RULE forbid | RULE forbid | — | ADR-032 | Phase 5 tests | **RESOLVED** (Catalog) |
| Sellable unit | always variant | always variant | variant_id cart | ADR-029 | Phase 5–6 | **RESOLVED** (Catalog) |
| Inventory decrement timing | OPEN at checkout | BR-INV-03 OPEN | Checkout flow | OPEN-021 | Phase 7 | **OPEN** (Checkout) |
| Commission in checkout | Snapshot required | Snapshot RULE; base OPEN | Snapshots in UC-C04 | ADR-006 | Phase 7+8 split | Phase inconsistency |
| Coupons in checkout | In scope | Stacking OPEN | In UC-C04 main flow | OPEN-007 | Phase 8 | Phase inconsistency |
| Guest checkout | Auth required at checkout | RULE | Auth checkout | ADR-019 | Phase 6 | **RESOLVED** (P0) |
| Dual role | Same account allowed | RULE | Vendor from customer | ADR-017 | Phase 2 | **RESOLVED** (P0) |
| Mixed-currency checkout | OPEN | OPEN | — | OPEN-005 | Phase 7 | **OPEN** |
| Tax | Out of scope engine | — | — | — | — | Watch at checkout schema |
| Email verification | FR-AUTH-07 | BR-CUS-08 | — | ADR-020 | Phase 1 | **RESOLVED** (P0) |

---

## Audit Conclusion

**OK** to keep ADRs 001–013 (architecture), 014–021 (P0), and **022–036 (Catalog)**.  
**OK** for Catalog **implementation** to begin when explicitly requested — Catalog documentation gate is closed as of 2026-08-12.  
**Not OK** to start Checkout until OPEN-005, OPEN-021, and related commerce OPENs are resolved.

Historical note: the original 2026-08-11 conclusion blocked Phase 1 until P0 sync; that P0 gate was completed separately. This file retains historical findings with sync annotations.

**This audit created only:** `docs/documentation-audit.md` (Catalog sync annotations added 2026-08-12).
