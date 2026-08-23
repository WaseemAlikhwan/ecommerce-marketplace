# P0 Decisions — Phase 1 Gate

**Project:** Syrian Multi-Vendor E-Commerce Marketplace  
**Purpose:** Resolve only the P0 blockers identified in `docs/documentation-audit.md` before Laravel Phase 1.  
**Status:** All P0 decisions approved (2026-08-11)  
**Constraint:** Final decisions below are stakeholder-approved. Do not reopen without a new decision pass.

This document does **not** reopen accepted ADRs (ADR-001 … ADR-013) unless a listed contradiction requires a documentation correction after approval.

Related baseline: `docs/documentation-audit.md` § Prioritized List / P0.

---

## How to use this document

For each item:

1. Review the recommended option and consequences.
2. Approve the recommendation, or select another listed option.
3. After approval, update the documents listed under **Documents to update**.
4. Do not start Phase 1 until the Phase 1 Gate checklist at the end is complete.

Legend for `Final decision`:

- `PENDING` — not approved
- `APPROVED: <option id>` — stakeholder accepted

---

## P0-1 — Application suspension vs vendor/store suspension

### Decision ID

`P0-1`

### Question

Where does `suspended` live in the V1 state model?

- On **vendor applications** (`pending` / `approved` / `rejected` / `suspended`)
- On the **vendor account** and/or **store** after approval
- On both, with an explicit relationship

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-VND-02 | Applications support at least `pending`, `approved`, `rejected`, `suspended` |
| `business-rules.md` BR-APP-02 | Same list as a **RULE** |
| `decisions.md` OPEN-003 | Recommends application statuses `pending` / `approved` / `rejected` only; model `suspended` on vendor/store |
| `development-plan.md` Phase 4 | Implements approve/reject/**suspend** “per approved state model” |

Audit IDs: **C-01**, **P0-1**.

This blocks Phase 1 only indirectly, but it is a P0 documentation contradiction: role/status vocabulary must be consistent before authz and later vendor-panel middleware. If `suspended` stays on applications, Phase 4 enums and notifications are designed one way; if it moves, FR-VND-02/BR-APP-02 must be corrected so implementers do not build the wrong state machine.

### Available options

| Option | Description |
|--------|-------------|
| **A** | Keep `suspended` as a vendor **application** status, in addition to `pending` / `approved` / `rejected`. |
| **B** | Application statuses: `pending`, `approved`, `rejected` only (terminal after decision). Model `suspended` on **vendor account** and **store** after approval. |
| **C** | Independent suspension flags on application, vendor, and store (three places). |

### Recommended option

**B**

### Consequences of the recommendation

- Matches real operations: you suspend a seller who was already approved, not an in-progress application.
- Application history stays an onboarding record; approved/rejected remain terminal.
- Vendor panel access and “cannot accept new orders” (BR-VND-05) attach to vendor/store status, not to rewriting application history.
- FR-VND-02 and BR-APP-02 must be **corrected** after approval (this is a documentation contradiction fix, not a new ADR replacing ADR-001–013).
- OPEN-003 can be closed as accepted in the B direction.
- In-flight orders when a vendor is suspended remain **OPEN-017** (P1 / Phase 4–7), not decided here.

Option A keeps the original requirement text but mixes onboarding with post-approval operations. Option C is harder to reason about and is unnecessary for V1.

### Documents to update after approval

- `docs/requirements.md` (FR-VND-02, FR-VND-03, ambiguity log)
- `docs/business-rules.md` (BR-APP-02, BR-APP-08, BR-VND-05 notes)
- `docs/decisions.md` (close OPEN-003)
- `docs/use-cases.md` (UC-V02 alternative; UC-A02)
- `docs/architecture.md` (VendorApplicationStatus vs vendor/store status)
- `docs/development-plan.md` (Phase 4 wording)
- `docs/documentation-audit.md` (optional: mark C-01 resolved after sync)

### Final decision

`APPROVED: B`

---

## P0-2 — Store cardinality

### Decision ID

`P0-2`

### Question

After approval, does each Vendor own **exactly one Store**, or may a Vendor own **multiple Stores**?

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` §2.1 | “One store per approved vendor (see OPEN DECISION…)” — reads as decided |
| `requirements.md` FR-STR-01 | “An approved vendor can own a store” (singular, not exclusive) |
| `business-rules.md` BR-STR-02 | **OPEN DECISION**: one vs many |
| `decisions.md` OPEN-001 | Open; recommends one store for V1 |
| `architecture.md` §5 | “Store (V1 recommendation: 1)” |

Audit IDs: **C-02**, **OPEN-001**.

This is a database cardinality decision. Phase 1 user/role scaffolding should not assume a store table yet, but the Vendor capability created in Phase 2–4 depends on whether `stores.vendor_id` is unique. Documenting it now prevents a 1:n rewrite.

### Available options

| Option | Description |
|--------|-------------|
| **A** | Exactly **one store per vendor** in V1 (`vendor_id` unique on `stores`). |
| **B** | A vendor may own **multiple stores** in V1. |

### Recommended option

**A**

### Consequences of the recommendation

**Business**

- One storefront identity per seller: simpler vendor panel, ratings, shipping, and commission override (override stays vendor-scoped, which matches ADR-006).
- Matches the original product wording (“a store”).
- Multi-store remains a later schema relaxation (drop unique constraint; add store switcher).

**Database / authorization**

- `stores.vendor_id` unique (1:1).
- Product ownership can be `store_id` (and thus vendor via store) without a store-picker in every vendor flow.
- Vendor isolation policies stay `vendor_id` / owned store — no “which store is active?” session concept in V1.

**If B were chosen**

- Vendor panel needs store context on products, coupons, orders, and shipping.
- Vendor Orders still belong to one vendor, but products/shipping may need `store_id` everywhere.
- Higher implementation cost for a university V1 with little product justification.

OPEN-001 should be closed as **one store per vendor**. Requirements §2.1 can then state it as a decided V1 rule, not an OPEN aside.

### Documents to update after approval

- `docs/requirements.md` (§2.1 Stores row; FR-STR-01)
- `docs/business-rules.md` (BR-STR-01, BR-STR-02 → RULE)
- `docs/decisions.md` (close OPEN-001 as ADR or accepted OPEN)
- `docs/architecture.md` (§5 VendorProfile → Store)
- `docs/use-cases.md` (UC-V03 wording if needed)
- `docs/documentation-audit.md` (optional: mark C-02 resolved)

### Final decision

`APPROVED: A`

---

## P0-3 — User identity (email / phone)

### Decision ID

`P0-3`

### Question

What identifier(s) does V1 authentication use?

- Email only
- Phone only
- Email + phone

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-AUTH-01 | Email and/or phone — OPEN |
| `business-rules.md` BR-CUS-03 | OPEN: email, phone, or both |
| `business-rules.md` BR-CUS-04 | OPEN: phone verification required? |
| `decisions.md` OPEN-016 | Registration/login identifier(s) |
| `development-plan.md` Phase 1 | Explicitly depends on identity decision |

Audit IDs: **OPEN-016**, **P0-3**.

Phase 1 creates `users` unique keys, registration/login/reset screens, and notification routing. This cannot wait.

### Available options

| Option | Description |
|--------|-------------|
| **A** | **Email only** (unique). Phone optional later on profile/address. |
| **B** | **Phone only** (unique). Login and password reset via phone. |
| **C** | **Email + phone both required**, both unique. Login with **email**. Phone used for COD/contact (verification of phone not required in V1 unless SMS exists). |
| **D** | Email + phone both required; login with **either** identifier. |

### Recommended option

**C**

### Consequences of the recommendation

**Syrian marketplace**

- Phone is the practical COD/contact channel.
- Email remains the standard for Laravel password reset and optional mail notifications without building SMS OTP in V1.

**Uniqueness / verification / reset / notifications**

- Unique `email`, unique `phone`.
- Password reset: email (Laravel default). No SMS reset in V1 (SMS is future; OPEN-013).
- Phone verification (BR-CUS-04): **not mandatory in V1** under this option, because V1 has no committed SMS channel. Format validation only (E.164 or a documented Syrian format).
- Email verification: see **P0-7** (separate decision).
- Future: can add login-by-phone or SMS OTP without changing the requirement that both fields exist.

**Rejected for V1**

- **B** forces SMS (or insecure phone-based reset) too early.
- **A** under-serves COD contact and Syrian UX.
- **D** is nicer UX but more auth surface (two lookup paths) than needed for Phase 1.

OPEN-016 closes as: both required; login = email. BR-CUS-04 remains “no phone OTP in V1” if C is approved (document explicitly).

### Documents to update after approval

- `docs/requirements.md` (FR-AUTH-01, ambiguity log)
- `docs/business-rules.md` (BR-CUS-03 → RULE; BR-CUS-04 aligned)
- `docs/decisions.md` (close OPEN-016)
- `docs/use-cases.md` (UC-C01 preconditions)
- `docs/development-plan.md` (Phase 1 identity dependency = resolved)
- `docs/architecture.md` (authentication notes)

### Final decision

`APPROVED: C`

---

## P0-4 — Customer + Vendor on the same user account

### Decision ID

`P0-4`

### Question

After vendor approval, can the same `User` retain **Customer** buying capability and also have **Vendor** selling capability?

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-RBAC-01 | Four conceptual roles listed; dual-role not stated |
| `business-rules.md` BR-VND-03 | OPEN; leans yes |
| `decisions.md` OPEN-002 | Open; recommends yes |
| `use-cases.md` UC-V01 | Actor is Customer (applicant) |
| `development-plan.md` Phase 2 | Roles foundation |

Audit IDs: **OPEN-002**, **P0-4**.

Phase 2 middleware (`/`, `/vendor`, `/admin`) and role assignment at registration/approval depend on this. Separate accounts would change registration and linking; same account changes Gates (a user may pass both customer and vendor policies).

### Available options

| Option | Description |
|--------|-------------|
| **A** | **Same account**: a user may hold Customer and Vendor capabilities simultaneously. Admin/Super Admin are staff accounts and are **not** used as storefront buyers in V1 (unless explicitly tested as separate users). |
| **B** | **Separate accounts**: becoming a vendor requires a distinct user (or converts/removes Customer). |

### Recommended option

**A**

### Consequences of the recommendation

- Applicant flow stays: register as Customer → apply → approve → add Vendor capability; user can still buy.
- Authorization is **capability + ownership**, not “one role excludes the other.”
- Vendor panel (`/vendor`) requires Vendor capability **and** approved/non-suspended vendor status.
- Storefront customer resources remain scoped to `user_id` even if the user is also a vendor.
- A vendor buying from their **own** store is a later edge case (not decided here; default allow unless a P1 rule forbids it).
- Option B doubles accounts, complicates COD contact, and is worse marketplace UX.

Staff (Admin / Super Admin) should remain separate users from marketplace buyers/sellers in V1 to keep privilege boundaries simple. That is a usage convention, not a second login system.

OPEN-002 closes as accepted A.

### Documents to update after approval

- `docs/requirements.md` (FR-RBAC-01 note on capabilities)
- `docs/business-rules.md` (BR-VND-03 → RULE)
- `docs/decisions.md` (close OPEN-002)
- `docs/architecture.md` (§7 isolation table)
- `docs/use-cases.md` (UC-V02 result text)
- `docs/development-plan.md` (Phase 2 features)

### Final decision

`APPROVED: A`

---

## P0-5 — Super Admin representation

### Decision ID

`P0-5`

### Question

How is Super Admin represented so it is clearly distinct from Admin?

- Separate role
- Admin role + special flag
- Another model

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-RBAC-01 | Conceptual roles include Super Admin and Admin |
| `business-rules.md` BR-PERM-02 | Super Admin has full access |
| `business-rules.md` BR-PERM-09 | OPEN: flag/role separate from Admin permission set? |
| `use-cases.md` UC-A10 | Only Super Admin assigns admin permissions |
| `architecture.md` §7 | Roles on users; Super Admin = all |
| `development-plan.md` Phase 2 | Seed Super Admin; permissions schema |

Audit IDs: **BR-PERM-09**, **P0-5**.

Phase 1–2 seeding and middleware need a single representation. Granular Admin permissions (BR-PERM-07 catalog) remain P1, but the Super Admin **mechanism** must be chosen now.

### Available options

| Option | Description |
|--------|-------------|
| **A** | **Separate role** `super_admin`. Bypasses granular permission checks. Admins use role `admin` + permission assignments. |
| **B** | Role `admin` for all staff, plus `is_super_admin` boolean that bypasses permission checks. |
| **C** | No distinct Super Admin; a full permission set assigned to one admin (indistinguishable in code). |

### Recommended option

**A**

### Consequences of the recommendation

- Super Admin and Admin are distinguishable in code, seeds, and UI (`role = super_admin` vs `role = admin`).
- Gates: `if super_admin → allow`; else check Admin permissions.
- UC-A10: only Super Admin assigns Admin role and permissions.
- Prevents an Admin from promoting themselves to Super Admin.
- Seed exactly one (or a small fixed set of) Super Admin(s) in Phase 1/2.
- Option B works technically but blurs “role” vs “flag” and is easier to get wrong in policies.
- Option C fails the requirement that Super Admin is a distinct conceptual role.

BR-PERM-07 (exact permission list) stays P1; Phase 2 can start with role checks plus a short permission table stub.

### Documents to update after approval

- `docs/requirements.md` (FR-RBAC-01/02 clarification)
- `docs/business-rules.md` (BR-PERM-09 → RULE)
- `docs/decisions.md` (new accepted decision or close BR-PERM-09 equivalent; add OPEN if not numbered)
- `docs/architecture.md` (§7.1)
- `docs/use-cases.md` (UC-A10)
- `docs/development-plan.md` (Phase 2 seed description)

### Final decision

`APPROVED: A`

---

## P0-6 — Guest browsing, cart, and checkout

### Decision ID

`P0-6`

### Question

What may an unauthenticated Guest do in V1?

- Authenticated-only for cart and checkout
- Full guest checkout (order without account)
- Guest browse + guest cart, **authentication required at checkout**

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` actors | Guest cart behavior OPEN |
| `business-rules.md` BR-CUS-05 | Guest cart and guest checkout OPEN |
| `decisions.md` OPEN-014 | Recommends guest cart; login at checkout |
| `use-cases.md` UC-C03 / UC-C04 | Guest cart OPEN; checkout authenticated unless approved |
| `development-plan.md` | Phase 1 auth screens; Phase 6 guest cart decision |

Audit IDs: **OPEN-014**, **P0-6**.

Phase 1 does not implement cart, but registration/login UX and whether checkout can create users implicitly depend on this. Cart persistence (BR-CART-04/05) remains P1 for Phase 6; this decision only sets **whether guest checkout exists** and **whether guests may hold a cart**.

### Available options

| Option | Description |
|--------|-------------|
| **A** | **Authenticated only**: Guest may browse/search; must log in to use cart and checkout. |
| **B** | **Full guest checkout**: Guest may browse, cart, and place COD orders without registering. |
| **C** | **Guest browse + guest cart**; **login/register required to checkout**. Merge guest cart on login (merge algorithm is P1 / BR-CART-05). |

### Recommended option

**C**

### Consequences of the recommendation

| Area | Effect |
|------|--------|
| Browse/search | Available to Guest (already in UC-C02). |
| Cart | Guest cart allowed (session/cookie). Persistence mechanism still P1. |
| Checkout | Requires authenticated Customer. No guest `orders.customer_id` null path in V1. |
| Addresses | Saved on the Customer account; no guest address book. |
| Orders | Always owned by a user. Authorization stays `customer user_id`. |
| Authentication | Checkout redirects to login/register; then resume checkout. |
| Identity (P0-3) | Guest never places an order; COD phone/email always come from registered user. |

Option B needs guest order identity, passwordless follow-up, and weaker ownership rules — too much for V1. Option A is simpler but worse storefront UX.

OPEN-014 closes as C. BR-CART-04 (session vs DB cart) and BR-CART-05 (merge rules) stay P1.

### Documents to update after approval

- `docs/requirements.md` (actors Guest row; ambiguity log)
- `docs/business-rules.md` (BR-CUS-05 → RULE)
- `docs/decisions.md` (close OPEN-014)
- `docs/use-cases.md` (UC-C03 actor; UC-C04 preconditions)
- `docs/development-plan.md` (Phase 1 note; Phase 6 guest cart still implements C)
- `docs/architecture.md` (storefront auth notes)

### Final decision

`APPROVED: C`

---

## P0-7 — Password policy and email verification

### Decision ID

`P0-7`

### Question

What is the minimum V1 authentication policy for Phase 1?

- Minimum password length and basic validation
- Whether email verification is mandatory
- Behavior if verification is incomplete

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-AUTH-01..04 | Register/login/reset; no password/verification rules |
| `use-cases.md` UC-C01 | “password rules” — not defined (audit M-01) |
| `architecture.md` §6 | Email verification listed as OPEN; not numbered in `decisions.md` |
| Audit | M-01, M-19, P0-7 |

Phase 1 implements register/login/reset. Password rules and verification gates must be specified or Laravel defaults will be invented silently.

### Available options

**Password**

| Option | Description |
|--------|-------------|
| **P-A** | Minimum **8** characters; must be confirmed; no complexity class required (no mandatory uppercase/symbol rules). |
| **P-B** | Minimum 8 characters plus complexity (upper, lower, digit/symbol). |
| **P-C** | Minimum 10+ with breach/HIBP checks. |

**Email verification**

| Option | Description |
|--------|-------------|
| **V-A** | **Mandatory** before login, cart, and checkout. |
| **V-B** | Verification email sent; **not** required for customer login/checkout. **Required before submitting a vendor application**. Unverified users can use the storefront as a Customer. |
| **V-C** | No verification flow in V1. |

### Recommended option

**Password: P-A** combined with **Verification: V-B**

### Consequences of the recommendation

**Password (P-A)**

- Matches Laravel-friendly, university-scale policy.
- Store hashed with Laravel defaults (NFR-SEC-01).
- Reset via email (depends on P0-3 option C).
- Avoids enterprise complexity (P-C) and arbitrary complexity rules that do not improve hashing security much (P-B).

**Email verification (V-B)**

- Phase 1 includes Laravel email verification infrastructure (Mailpit in Docker is enough for demo).
- Incomplete verification:
  - User **can** log in, browse, use cart, and checkout.
  - User **cannot** submit a vendor application until the email is verified.
  - UI may show a reminder banner; not a hard logout.
- If P0-3 requires email, this still proves mailbox ownership before a seller is onboarded, without blocking COD customers who may confirm email later.
- V-A blocks the whole marketplace on mail delivery — fragile for local/demo.
- V-C skips a cheap Laravel feature that vendor onboarding should have.

Phone OTP remains out of V1 (see P0-3). Auto-login after register (audit M-20) is P2: recommendation **log in immediately after register** for UX, still `PENDING` unless approved here as a side note — **not required for the Phase 1 gate** beyond: registration may either sign the user in or require a separate login; implementers should pick one and document it when coding. Preferred with V-B: **sign in after register**.

### Documents to update after approval

- `docs/requirements.md` (new FR-AUTH password/verification bullets)
- `docs/business-rules.md` (new RULE rows under customer registration)
- `docs/decisions.md` (record password + verification decision; close architecture email-verification OPEN)
- `docs/use-cases.md` (UC-C01; UC-V01 preconditions: verified email)
- `docs/architecture.md` (§6: verification no longer OPEN)
- `docs/development-plan.md` (Phase 1 testing: password validation; Phase 4: application requires verified email)

### Final decision

`APPROVED: P-A + V-B`

---

## P0-8 — Locale persistence

### Decision ID

`P0-8`

### Question

How is the selected UI language (`ar` / `en`) persisted for Guests and authenticated users?

Evaluate: user profile, cookie, session, `Accept-Language`, or hybrid.

### Current documentation conflict / why it blocks Phase 1

| Source | Statement |
|--------|-----------|
| `requirements.md` FR-I18N-03 | Locale “persists for the user/session per documented approach” — approach missing |
| `business-rules.md` BR-TR-01 / BR-TR-06 | Languages + RTL; no persistence rule |
| `architecture.md` §4.1 | Locale middleware; no storage |
| Audit | M-02, P0-8 |
| `development-plan.md` Phase 1 | Base Blade layouts with LTR/RTL hook; Phase 3 locale switcher |

Phase 1 introduces layouts and (at least) a language switcher hook. Without a persistence rule, implementers will invent cookie vs session vs user column inconsistently.

### Available options

| Option | Description |
|--------|-------------|
| **A** | **Session only**. Lost when the session expires; Guest and user share no durable preference. |
| **B** | **Cookie only**. Works for Guest and authenticated; not stored on the user row. |
| **C** | **User profile only**. Guests always hit default/`Accept-Language`. |
| **D** | **`Accept-Language` only**. No user control persistence. |
| **E** | **Hybrid**: first visit may use `Accept-Language` if it is `ar` or `en`, else default `ar` (market default). Explicit selection stored in a **cookie**. When authenticated, also stored on **user preferred locale** and cookie is kept in sync on login/update. |

### Recommended option

**E**

### Consequences of the recommendation

| Actor | Behavior |
|-------|----------|
| Guest, first visit | `Accept-Language` if `ar` or `en`; otherwise default **Arabic** (Syrian market). |
| Guest, after switch | Cookie persists across sessions (reasonable expiry, e.g. 1 year). |
| User logs in | Cookie locale written to user profile if the user has no preference yet; if profile already has a preference, profile wins and cookie is updated. |
| User switches language | Cookie + profile updated together. |
| RTL | `ar` → RTL layout; `en` → LTR (already required). |

- Works for Guests (cookie) and authenticated users (profile + cookie).
- Session may cache the resolved locale for the request lifecycle but is not the source of truth.
- `Accept-Language` is only a **first-visit hint**, never overrides an explicit cookie/profile choice.
- Fallback locale for **missing translations** remains a separate P2 item (BR-TR-04), not this decision.
- Display currency (BR-CUR-08) is **not** decided here.

Default language **Arabic** is a product recommendation for a Syria-only marketplace; English remains fully supported.

### Documents to update after approval

- `docs/requirements.md` (FR-I18N-03 — replace “per documented approach”)
- `docs/business-rules.md` (new RULE under translations)
- `docs/decisions.md` (record locale persistence)
- `docs/architecture.md` (locale middleware behavior)
- `docs/use-cases.md` (UC-C09)
- `docs/development-plan.md` (Phase 1 layout + Phase 3 switcher)

### Final decision

`APPROVED: E`

---

## Phase 1 Gate

All P0 decisions below are approved. Synchronize the planning docs listed under each decision, then Phase 1 Laravel scaffolding may begin. Do **not** start coding until that sync is complete for this approval pass.

| Gate item | Decision | Required approval |
|-----------|----------|-------------------|
| G1 | P0-1 Application vs vendor/store suspension | `APPROVED: B` |
| G2 | P0-2 One store per vendor vs many | `APPROVED: A` |
| G3 | P0-3 Email / phone identity | `APPROVED: C` |
| G4 | P0-4 Customer + Vendor same account | `APPROVED: A` |
| G5 | P0-5 Super Admin representation | `APPROVED: A` |
| G6 | P0-6 Guest cart / checkout policy | `APPROVED: C` |
| G7 | P0-7 Password + email verification | `APPROVED: P-A + V-B` |
| G8 | P0-8 Locale persistence | `APPROVED: E` |

**After this approval pass:**

1. Patch `requirements.md`, `business-rules.md`, `decisions.md`, `use-cases.md`, `architecture.md`, `development-plan.md` so they no longer contradict these P0 answers.
2. Keep remaining P1 items (currency checkout, payment FK, shipping algorithm, etc.) open; they do **not** gate Phase 1 scaffolding.
3. Only then begin Phase 1 (Laravel app, Docker, auth screens).

**Explicitly out of this gate (P1+, do not block Phase 1):**

- OPEN-005 multi-currency checkout
- OPEN-011 payment granularity
- OPEN-012 shipping algorithm
- Coupon stacking, cancellation matrix, review gate
- Admin permission catalog details (BR-PERM-07)
- Cart persistence implementation (BR-CART-04) — policy in P0-6 is enough
- Phone OTP / SMS

---

## Recommended choices (summary for approval)

| ID | Recommendation |
|----|----------------|
| P0-1 | **B** — Application: `pending` / `approved` / `rejected`. `suspended` on vendor and store after approval. |
| P0-2 | **A** — Exactly one store per vendor in V1. |
| P0-3 | **C** — Email + phone both required and unique; login with email; no phone OTP in V1. |
| P0-4 | **A** — Same user may be Customer and Vendor. |
| P0-5 | **A** — Distinct `super_admin` role; Admins use granular permissions. |
| P0-6 | **C** — Guest browse + guest cart; authentication required at checkout. |
| P0-7 | **P-A + V-B** — Password min 8, confirmed; email verification required for vendor application only, not for customer checkout. |
| P0-8 | **E** — Hybrid: `Accept-Language` first visit (else default `ar`), cookie for guests, user profile + cookie when authenticated. |

No decision in this document remains pending for the Phase 1 gate. All eight P0 recommendations were approved 2026-08-11.
