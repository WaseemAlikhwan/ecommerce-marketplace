# Cart C1 — Guest/Auth Cart Foundation

**Status:** READY — C1-A…C1-D3 complete (2026-08-23)  
**Authority:** ADR-019, ADR-029, ADR-032, ADR-033, **ADR-041**; BR-CART-01…08  
**Baseline:** Storefront S8C complete (Docker PHPUnit 327 / 2351); Cart C1 final gate **369 / 2791**

Implement only the named slice when asked. Do not start Checkout, Wishlist, inventory decrement/reservation, Reviews, ratings, coupons, shipping quotes, Redis carts, or FX conversion.

## Approved decisions (do not reopen in code)

| Topic | Decision |
|-------|----------|
| Guest cart | **Session** |
| Authenticated cart | **Database** |
| Login/register | Merge **by `variant_id`**, sum qty, **cap to current stock**, report **adjusted** and **unavailable** lines, then clear session cart |
| Mixed currencies | **Allowed**; **separate subtotals per currency**; **no conversion** |
| Line identity | `variant_id` only (ADR-029) |
| Out of scope | Checkout / orders, inventory decrement or reserve, Wishlist |

Cart prices remain informational (BR-CART-06). Checkout charge/settlement currency stays OPEN (OPEN-005 / BR-CUR-04 / BR-CUR-08).

## Slice map

| Slice | Primary outcome | Depends on |
|-------|-----------------|------------|
| **C1-A** | Session + DB persistence and add/update/remove by variant | — |
| **C1-B** | Login/register merge + merge report | C1-A |
| **C1-C** | Query-free cart read model with per-currency totals | C1-A |
| **C1-D1** | Storefront HTTP mutation contract (add/update/remove) | C1-A |
| **C1-D2** | Cart Blade UI + merge flash + PDP/card wiring | C1-B, C1-C, C1-D1 |
| **C1-D3** | Final acceptance gate (suite, smoke, docs) | C1-D1, C1-D2 |

---

## C1-A — Persistence and line mutations

**Status:** DONE (2026-08-23) — focused gate **8 tests / 66 assertions**.

**Goal:** Guests and authenticated users can hold a multi-vendor cart keyed by `variant_id`, with quantity capped to current on-hand stock on every write.

**In scope**

1. Session cart store for guests (serializable line list: `variant_id`, `quantity`).
2. DB schema for authenticated carts/items (`user_id`, `variant_id`, `quantity`, unique line per user+variant).
3. Application service(s) for resolve-active-cart, add, update quantity, remove.
4. Purchasability guard on mutate: storefront-visible product, live variant, stock &gt; 0 (or allow qty only up to stock); never expose public SKU or exact stock beyond cap behavior needed for the line.
5. Authenticated writes run in a DB transaction with `lockForUpdate` on the cart row (and line/variant) for concurrency safety.
6. Focused automated tests for guest session cart, auth DB cart, multi-vendor lines, stock cap on add/update, and isolation between users.

**Out of scope for C1-A:** login merge, Blade/HTTP cart page, currency subtotal presenter, Checkout, Wishlist, inventory writes.

**Done when**

- Focused tests for C1-A green.
- No Checkout/Wishlist/inventory-decrement code paths introduced.
- AR/EN keys added for any new user-facing mutation messages keep parity (if any strings land in A; prefer machine codes until C1-D if possible).

### C1-A resolution notes

- Guest: `App\Cart\SessionCartStore` (session key `cart.lines`).
- Auth: `carts` + `cart_items` with unique `(user_id)` and `(cart_id, variant_id)`.
- `CartService` add/update/remove; unavailable variants throw `CartException` with machine code `variant_unavailable` (no SKU in messages).
- Auth mutations: `DB::transaction` + `insertOrIgnore` cart row + `lockForUpdate` on cart/item/variant.
- Focused result: **8 tests / 66 assertions**.

**Stop after C1-A.** Do not start C1-B.

---

## C1-B — Login / register merge

**Status:** DONE (2026-08-23) — hardened focused gate with C1-A: **18 tests / 160 assertions** (C1-B alone: **10 / 94**).

**Goal:** Authenticating with a guest session cart merges into the user’s DB cart safely and reportably.

**In scope**

1. Hook merge into login and register success paths (single shared merge service).
2. Merge algorithm: match by `variant_id`; sum quantities; cap to current `product_variants` stock; drop missing/unpurchasable/zero-stock residuals as unavailable.
3. Structured merge result: kept lines, **adjusted** lines (old qty → new qty), **unavailable** lines (reason code).
4. Clear guest session cart only after a successful merge attempt completes.
5. Persist a JSON-safe merge-result flash payload (`cart.merge`) from login and registration for C1-D.
6. Focused tests: empty session, empty DB, overlap with cap, unavailable variant, multi-currency lines preserved without conversion, idempotent second login with empty session, login/register HTTP merge + flash assertions, real in-transaction exception proving DB rollback and guest-session retention.

**Out of scope for C1-B:** cart page UI (flash consumption waits for C1-D), Checkout, Wishlist, inventory decrement.

**Done when**

- Focused merge tests green.
- Guest session is empty after successful merge.
- Merge never writes stock or orders.

### C1-B resolution notes

- `CartService::mergeGuestCart()` — lock order cart → variant (sorted ids) → item; session cleared only after commit.
- DTOs: `CartMergeResult` / `CartMergeAdjustment` / `CartMergeUnavailable` with `toFlashPayload()` (JSON-safe ints/strings only).
- Login + register flash `cart.merge` for C1-D.
- `CartMergeTransactionHook` test seam: forced exception inside transaction rolls back DB and retains guest session.
- Focused C1-A+B: **18 tests / 160 assertions**.

**Stop after C1-B.** Do not start C1-C unless requested.

---

## C1-C — Cart read model and mixed-currency totals

**Status:** DONE (2026-08-23) — hardened focused gate **10 tests / 126 assertions**.

**Goal:** A query-free presentation state for the active cart with correct money shaping and per-currency subtotals.

**In scope**

1. Load/present cart lines with product/variant display fields already loaded by the service (no Blade queries).
2. Money as integer minor units internally; public money integers serialized as decimal **strings**.
3. **Separate subtotals per currency**; refuse a single converted grand total; no exchange-rate reads.
4. Informational unit/line prices from current catalog data; surface stock-cap or availability issues without exposing exact inventory quantity or public SKU.
5. Focused tests: single-currency subtotal; mixed SYP+USD separate totals; empty cart; adjusted/unavailable flags when revalidation drops or caps lines on read (if read-time revalidation is included—keep consistent with ADR-041).

**Out of scope for C1-C:** HTTP routes/Blade (wire in C1-D), Checkout, FX tables, Wishlist.

**Done when**

- Presenter/state tests green.
- Mixed-currency fixture proves two subtotals and zero conversion calls.

### C1-C resolution notes

- `CartViewService` loads variant graphs (incl. Variable selection + primary image) + `storefrontVisible` product ids; never mutates session/DB cart on read.
- `CartViewPresenter` is zero-query; marks unavailable / adjusted lines in the view only; unavailable lines stay stored and are excluded from subtotals; stock-short lines use `effectiveQuantity = min(requested, stock)`.
- **Hardening:** non-storefront-visible lines are generic placeholders (no product/store/image/price/selection); visible out-of-stock lines keep public details; Variable `selection` labels localized without SKU; image `width`/`height` included.
- Money via `CheckedInteger` mul/add; public payload `{ currency_code, exponent, amount_minor: string }`; per-currency `CartCurrencySubtotal` only (no grand/FX total).
- No SKU, exact stock, or seller/vendor ids in `CartView` / `toArray()`.
- Focused result: **10 tests / 126 assertions** (`CartC1CTest`), including explicit zero-query presenter proof.

**Stop after C1-C.** Do not start C1-D unless requested.

---

## C1-D — Storefront HTTP/UI and final gate (split)

C1-D is executed as **C1-D1 → C1-D2 → C1-D3**. Do not collapse slices.

---

## C1-D1 — HTTP mutation contract

**Status:** DONE (2026-08-23) — focused gate **7 tests / 86 assertions**.

**Goal:** Guests and authenticated customers can add/update/remove cart lines over standard HTML form verbs with localized domain errors—without Blade cart UI.

**In scope**

1. Thin Storefront controller(s) + Form Requests for add, update quantity, remove by `variant_id`.
2. Routes: `POST` add, `PATCH` update, `DELETE` remove (standard form method spoofing).
3. Localized mapping of `CartException` / validation failures (AR/EN key parity).
4. Focused HTTP tests: guest + auth add/update/remove, stock-cap flash/status when adjusted, unavailable variant errors without SKU/stock leakage, user isolation.

**Out of scope for C1-D1:** Cart Blade page, PDP/card “add” buttons, merge flash UI, Checkout controls, inventory writes.

**Done when**

- Focused C1-D1 HTTP tests green with counts recorded here.

### C1-D1 resolution notes

- `Storefront\CartItemController` + `StoreCartItemRequest` / `UpdateCartItemRequest`.
- Routes: `POST /cart/items`, `PATCH|DELETE /cart/items/{variant}` (named `cart.items.*`).
- `CartExceptionTranslator` maps machine codes to AR/EN JSON strings; errors flash on `cart` without SKU/stock.
- Stock-cap mutations flash adjusted status; qty `0` via PATCH removes the line.
- Guest session vs auth DB isolation preserved; users cannot mutate each other’s carts.
- Focused result: **7 tests / 86 assertions** (`CartC1D1Test`).

**Stop after C1-D1.** Do not start C1-D2 unless requested.

---

## C1-D2 — Cart Blade UI and storefront wiring

**Status:** DONE (2026-08-23) — focused gate **7 tests / 65 assertions** (`CartC1D2Test`). Full Cart C1 focused suite with D2: **42 / 437**.

**Goal:** Customers can view and manage the cart in AR/EN, see merge feedback after login, and use PDP/card add controls wired to C1-D1 routes.

**In scope**

1. Cart show page consuming `CartViewService` (query-free presenter).
2. Blade: line list, per-currency subtotals, adjusted/unavailable messaging (read-time + merge flash), RTL/LTR.
3. Wire PDP/card forms to C1-D1 mutation routes.
4. Optional “continue to checkout” control may require auth (ADR-019) but must **not** create orders or call a Checkout service (placeholder/disabled/redirect-only is fine until Checkout phase).
5. Translation-key parity AR/EN for UI strings introduced here.

**Out of scope for C1-D2:** Final full-suite gate (C1-D3), Checkout implementation, Wishlist, inventory decrement/reserve.

**Done when**

- Focused UI/HTTP show tests green (exact counts recorded here).

### C1-D2 resolution notes

- `GET /cart` → `CartController@show` via `CartViewService::view()` only (no Blade queries).
- Blade: lines + Variable `selection`, per-currency subtotals, adjusted/unavailable read messaging; consumes `cart.merge` flash once after login/register (redirect to cart when merge non-empty).
- PDP + simple product-card forms POST to `cart.items.*`; variable cards link to PDP (“Choose options”).
- Nav/footer cart affordance; auth checkout CTA disabled placeholder; guests see login CTA — no orders/Checkout/Wishlist.
- Focused result: **7 tests / 65 assertions** (`CartC1D2Test`).

**Stop after C1-D2.** Do not start C1-D3 unless requested.

---

## C1-D3 — Final acceptance gate

**Status:** DONE (2026-08-23)

**Goal:** Cart C1 passes the project gate without implementing Checkout.

**In scope**

1. Final gate (run once at end of C1-D3):
   - focused Cart tests
   - full Docker PHPUnit
   - Pint
   - `view:cache`
   - `npm run build`
   - AR/EN parity
   - forbidden-reference check (no Wishlist persistence, no inventory decrement on cart routes, no Checkout order placement)
   - brief browser smoke: guest add → login merge → mixed-currency subtotals visible; cleanup any smoke data
2. Reconcile ADR-041 / development-plan Phase 6 cart row if needed.

**Out of scope for C1-D3:** Checkout, Wishlist, new cart features beyond gate fixes.

**Done when**

- All C1-D3 gate checks green with exact counts recorded in this file.
- No leftover smoke users/products from Cart smoke.

### C1-D3 gate results (2026-08-23)

| Check | Result |
|-------|--------|
| Focused Cart (`--filter=CartC1`) | **42 tests / 437 assertions** |
| Full Docker PHPUnit | **369 tests / 2791 assertions** |
| Pint (`--test`, Cart + gate-fix paths) | PASS (31 files). Project-wide `--test` still reports pre-existing CRLF `line_ending` noise outside Cart. |
| `php artisan view:cache` | PASS |
| `npm run build` | PASS |
| AR/EN parity | **EN=908 AR=908**, `PARITY_OK` |
| Forbidden refs | PASS — no Wishlist persistence/migrations; no inventory decrement/reserve on cart services/controllers; no CheckoutService / placeOrder / ParentOrder |
| HTTP smoke | PASS — guest add → register merge → mixed SYP+USD subtotals; leftovers **0** |
| Live migrate | Applied pending `2026_08_23_010000_create_cart_tables` on local Docker DB before smoke |

**Gate fixes included in C1-D3**

- Variable PDP Alpine disable binding: Blade `:disabled` → `x-bind:disabled` (was evaluating PHP constant `selectedVariant`).
- Design-system / product-card defensive `is_simple` / `default_variant_id` keys.
- Catalog/localization/schema tests updated for Cart UI (nav Cart, Add to cart, `carts`/`cart_items` schema presence).

**Stop after C1-D3.** Do not start Checkout or Wishlist.

---

## Hard boundaries (every slice)

- No Cart work beyond the requested slice.
- No Checkout, Wishlist, inventory decrement/reservation, Reviews, ratings, conversion, Redis, FULLTEXT, or JSON-LD.
- No public SKU or exact inventory quantity in storefront payloads/UI.
- No new dependency unless a newly discovered blocker gets an explicit decision.
- No commit/push unless the user asks.

## Prompts

Use only one at a time:

```text
Implement only C1-A from @docs/tasks/cart-c1.md. Stop after its focused tests.
```

```text
Implement only C1-B from @docs/tasks/cart-c1.md. Stop after its focused merge tests.
```

```text
Implement only C1-C from @docs/tasks/cart-c1.md. Stop after its presenter tests.
```

```text
Implement only C1-D1 from @docs/tasks/cart-c1.md. Stop after focused HTTP tests.
```

```text
Implement only C1-D2 from @docs/tasks/cart-c1.md. Stop after focused cart UI tests.
```

```text
Implement only C1-D3 from @docs/tasks/cart-c1.md. Stop after the final acceptance report.
```
