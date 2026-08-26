# Admin Ops ADM — Phase 10 / OPEN-020 V1 Admin Dashboard & Operational Tools

**Status:** DONE (2026-08-26) — ADM-A…D accepted; focused **12 / 146**; AR/EN **1125 / 1125**; smoke leftovers **0**  
**Authority:** ADR-001, ADR-002, ADR-012, ADR-040, ADR-042; FR-ADM-01…03; BR-RPT-01…03; BR-PERM-01…06, BR-PERM-09; UC-A09; Phase 10  
**Baseline:** Checkout CHK DONE; VOL DONE; CAN DONE; Wishlist WSH DONE; Reviews REV DONE; Coupons CPN DONE; demo seeder available; admin already has vendors/apps, catalog taxonomy, coupons CRUD, review moderation  
**Related OPEN:** **OPEN-020** admin KPI/report set — **V1 closed by ADM**

Implement only the named slice when asked. Do **not** start card charge, settlement ledger, store rating, vendor review replies, post-ship returns, Redis, vendor self-serve coupons, admin cancel, COD collected mutation, exports, Super Admin permission UI, or Phase 11 polish beyond the ADM gate unless a later approved slice says so.

## Planning freeze (APPROVED for ADM V1)

| Topic | Decision for ADM V1 | Notes |
|-------|---------------------|--------|
| OPEN-020 KPIs | Counts (and money totals where noted) only — **no CSV/PDF export** | Closes **OPEN-020** / BR-RPT-01 for V1. BR-RPT-02 export formats stay **out of ADM V1**. |
| KPI set | Pending vendor applications; pending product reviews; Parent orders with status `placed`; VO counts by status (`pending` / `confirmed` / `shipped` / `delivered` / `cancelled`); COD Payment counts by status (`pending` / `collected` / `cancelled`); published products; approved vendors; **recognized commission amount sum per `currency_code`** (VOs with `commission_recognized_at` set — snapshot money, BR-RPT-03) | Integer minor units in domain; present as decimal strings in UI. |
| Authz | Staff-only (`staff` middleware + Policies `isStaff()`), fail-closed | Guests → login; non-staff → 403. No new granular permission catalog (**BR-PERM-07** remains OPEN / out of ADM V1). |
| Orders | **Read-only** Parent index/show (nested VOs + line summaries + payments); VO index/show | **No** admin cancel, status advance, or Parent derivation. |
| Payments | **Read-only** Payment index/show (COD status visible) | **No** mark-collected / settlement UI. |
| Users / vendors | Thin read-only users index (name/email/roles); vendors/apps stay existing screens | Reuse `admin.vendors` / applications; no rewrite. |
| Commissions | Read-only display of global commission setting(s) | No vendor-override CRUD in ADM V1. |
| Coupons / reviews / catalog | Already exist — **fill gaps only** (nav, dashboard deep-links) | No feature rewrites. |
| Money / i18n | Integer minor units in domain; public/admin money as decimal strings; AR/EN key parity | Arabic default RTL; English LTR complete. No public SKU / exact inventory on admin order/product summaries. |
| Storage | MySQL authoritative | No Redis. |

### Source rules

> 1. Admin dashboard shows the frozen V1 KPI set (OPEN-020 / FR-ADM-01 / BR-RPT-01).  
> 2. Historical financial figures use snapshotted commission amounts on Vendor Orders (BR-RPT-03).  
> 3. Staff may view Parent / VO / Payment operational lists and shows (FR-ADM-02 gap fill); mutations that change fulfillment, cancel, or COD collection are **out of ADM V1**.  
> 4. Authorization is staff fail-closed via middleware + Policies (BR-PERM-06 / BR-PERM-09); no new permission catalog in this task.  
> 5. Reuse existing admin modules for vendors, catalog, coupons, and reviews — fill gaps only.

### Hard out of scope (every slice)

- Card charge; settlement / refund ledger  
- Store rating; vendor review replies; post-ship returns  
- Redis; vendor self-serve coupons  
- Admin cancel; COD collected mutation; Parent derivation / status engines  
- CSV/PDF exports (BR-RPT-02)  
- Super Admin granular permission UI (BR-PERM-07)  
- Demo seeder changes; Phase 11 polish beyond the ADM gate  

## Slice map

| Slice | Primary outcome | Depends on | Status |
|-------|-----------------|------------|--------|
| **ADM-A** | Thin admin KPI / stats query service (+ staff Policy helpers as needed); focused domain tests; no Blade required yet | This freeze | **DONE** (2026-08-26) — focused **5 / 40** |
| **ADM-B** | Dashboard UI: replace placeholder tiles with KPI set + deep-links to existing vendors/reviews/coupons/catalog; AR/EN; focused HTTP tests | ADM-A | **DONE** (2026-08-26) — focused **3 / 25** |
| **ADM-C** | Read-only Parent/VO/Payment index+show; thin users index + commission setting show; replace `admin.orders` placeholder; fail-closed staff authz; AR/EN; focused HTTP tests | ADM-A | **DONE** (2026-08-26) — focused **3 / 65** |
| **ADM-D** | Acceptance gate + mark DONE; sync OPEN-020 closed + Phase 10 note + BR-RPT-01/02 wording | ADM-A, ADM-B, ADM-C | **DONE** (2026-08-26) |

---

## ADM-A — KPI / stats domain

**Status:** DONE (2026-08-26) — focused **5 / 40**  

**Goal:** Authoritative, query-only admin KPI aggregation matching the freeze (no Blade).

**In scope**

1. Thin service (name flexible, e.g. `AdminDashboardStatsService`): return the frozen KPI set from MySQL (counts + recognized commission sums per currency).  
2. Staff-only authorization helpers / Policy as needed for later HTTP (fail-closed `isStaff()`).  
3. Focused domain tests: KPI correctness against seeded fixtures; commission sums only from recognized VOs; no SKU / exact inventory in DTO/array payloads.

**Out of scope:** Dashboard Blade; order/payment screens; exports; mutations.

**Done when:** Focused admin KPI domain tests green; no admin UI required yet.

**Shipped:** `AdminDashboardStats` DTO; `AdminDashboardStatsService::snapshot()`; `AdminDashboardPolicy` (Gate-registered); `AdminOpsAdmATest`.

**Stop after ADM-A.**

---

## ADM-B — Dashboard UI

**Status:** DONE (2026-08-26) — focused **3 / 25**  

**Goal:** Staff overview shows the frozen KPIs and links into existing admin modules.

**In scope**

1. Wire `DashboardController` (or successor) to ADM-A service.  
2. Replace placeholder tiles in `admin.dashboard` with KPI set + deep-links (vendors/apps, reviews, coupons, catalog, and later order/payment routes when ADM-C lands — use routes that exist at B time).  
3. Localized flash/copy; AR/EN key parity.  
4. Focused HTTP tests: staff 200 + sees key KPIs; guest → login; non-staff 403.

**Out of scope:** Building order/payment CRUD; exports; ADM-C screens (may link to placeholders until C).

**Done when:** Focused dashboard HTTP tests green.

**Shipped:** `DashboardController` → `AdminDashboardStatsService`; KPI tiles + module deep-links; commission decimal labels; AR/EN **1102 / 1102**; `AdminOpsAdmBTest`.

**Stop after ADM-B.**

---

## ADM-C — Order / payment ops screens (+ thin users & commission read)

**Status:** DONE (2026-08-26) — focused **3 / 65**  

**Goal:** Staff can operate day-to-day without DB tools for orders and payments (read-only), plus thin users/commission visibility.

**In scope**

1. Replace `admin.orders` placeholder with Parent index/show (nested VOs, line summaries, related payments).  
2. Vendor Order index/show (read-only).  
3. Payment index/show (COD status visible; no collect action).  
4. Thin users index (name/email/roles).  
5. Thin read-only global commission setting show (link from settings or dashboard).  
6. Form Requests / Policies fail-closed; AR/EN; no public SKU / exact qty on summaries.  
7. Focused HTTP tests: staff success; guest redirect; non-staff 403; shows omit forbidden fields.

**Out of scope:** Admin cancel; VO status advance; COD collected mutation; settlement; commission override CRUD; user role assignment UI; exports.

**Done when:** Focused admin order/payment (and thin users/commission) HTTP tests green.

**Shipped:** Admin Parent/VO/Payment index+show; users index; settings commission show; `OrderViewService` admin payloads; `UserPolicy` / `CommissionSettingPolicy`; nav + dashboard deep-links; AR/EN **1125 / 1125**; `AdminOpsAdmCTest`.

**Stop after ADM-C.**

---

## ADM-D — Acceptance gate

**Status:** DONE (2026-08-26)  

**Goal:** ADM passes a small gate; OPEN-020 V1 recorded as closed by this task.

**In scope**

1. Gate: focused ADM tests (A+B+C); Pint (ADM-scoped); `view:cache`; AR/EN parity; forbidden-ref (no card charge, settlement ledger, store rating, Redis, vendor self-serve coupons, admin cancel, COD collect, exports; no public SKU/exact qty regression); brief smoke (staff dashboard KPIs → Parent show → Payment show; leftovers 0).  
2. Mark this task DONE with exact counts; sync `docs/decisions.md` OPEN-020 **V1 closed by ADM**; Phase 10 note in `docs/development-plan.md`; BR-RPT-01 RULE wording to match KPI set; BR-RPT-02 **out of ADM V1**.

**Out of scope:** Full Docker suite unless project gate requires it — prefer focused ADM + smoke (match CPN-C / REV-D lightly).

**Done when:** Gate table recorded; smoke leftovers 0; task DONE.

### Gate (ADM-D / ADM final)

| Check | Result |
|-------|--------|
| Focused ADM (`AdminOpsAdmATest` + `AdminOpsAdmBTest` + `AdminOpsAdmCTest` + `AdminOpsAdmDSmokeTest`) | **12 / 146** (A **5 / 40** + B **3 / 25** + C **3 / 65** + smoke **1 / 16**) |
| Pint (ADM-scoped PHP) | **PASS** (18 files) |
| `view:cache` | **PASS** |
| AR/EN key parity | **1125 / 1125** (missing 0) |
| Forbidden-ref (card charge; settlement ledger; store rating; Redis; vendor self-serve coupons; admin cancel; COD collect mutation; exports; no public SKU / exact inventory regression on admin surfaces) | **PASS** |
| Smoke: staff dashboard KPIs → Parent show → Payment show | **PASS** — KPIs + recognized commission visible; Parent/Payment shows omit SKU; no Mark collected; leftovers **0** |

**Verification (ADM-D):** Task **DONE**. OPEN-020 V1 closed by ADM; Phase 10 note synced; BR-RPT-01 KPI RULE + BR-RPT-02 out-of-ADM-V1 wording synced. No Phase 11, card charge, settlement ledger, admin cancel, or COD collect.

**Stop after ADM-D.** (Completed.) Do not start Phase 11, card charge, or settlement ledger.

---

## Hard boundaries (every slice)

- Staff-only fail-closed; no new permission catalog.  
- Read-only orders/payments in V1 — no admin cancel / collect / fulfillment mutations.  
- KPI set exactly as frozen; no exports.  
- Reuse vendors, catalog, coupons, reviews — fill gaps only.  
- No public SKU; no exact inventory quantity.  
- No Redis; no card charge; no settlement ledger.  
- No commit/push unless the user asks.

## Prompts

```text
Implement only ADM-A from @docs/tasks/admin-ops-adm.md. Stop after focused admin KPI domain tests.
```

```text
Implement only ADM-B from @docs/tasks/admin-ops-adm.md. Stop after focused admin dashboard HTTP tests.
```

```text
Implement only ADM-C from @docs/tasks/admin-ops-adm.md. Stop after focused admin order/payment HTTP tests.
```

```text
Implement only ADM-D from @docs/tasks/admin-ops-adm.md. Stop after the final acceptance report.
```
