# Budget App Roadmap — Personalized Budgeting Agent

This roadmap evolves the current receipt manager into a complete personal budgeting
agent: it accumulates **all** living expenses (variable spend from receipts + fixed
recurring spend from contracts), gives weekly/monthly overviews, surfaces what you
consume most, and recommends **where you can save**.

## Decisions (locked)

- **Contracts & providers UI:** built into the Inertia + React user app (alongside receipts/dashboard). Backpack admin is kept for back-office/data fixes only.
- **Scope:** multi-user capable — everything stays scoped by `user_id`.
- **Price intelligence:** full **AI product catalog** from the start. OCR line items are matched to a canonical `Product` so prices can be compared across vendors and over time.
- **Stack (unchanged):** Laravel 12, Inertia v2, React 18, Tailwind v3, queue jobs, service classes, OpenAI (`LlmService`), Backpack admin.

## Guiding architecture

- **Two expense sources, one ledger.** Variable spend = `Receipt` + `ReceiptItem`. Fixed spend = `Contract` (recurring). A unified `ExpenseService` normalizes both to a common timeframe (weekly/monthly) for overviews and budgets — no shared table needed; union in the service.
- **Normalization factors** (recurring → monthly): weekly ×4.33, biweekly ×2.166, monthly ×1, quarterly ÷3, yearly ÷12.
- **Catalog matching is async.** Item → `Product` matching runs as a queued job after the `ProcessReceipt` vision-parsing job, reusing that job/queue pattern.
- **Money handling:** store amounts as integer minor units (cents) going forward, or keep `decimal` consistently — pick one in Phase 0 and apply everywhere. Currency is per-record (`EUR` default today).
- **All new user actions enforce ownership** (`user_id === Auth::id()`), matching `ReceiptController`. Add Policies in Phase 0 to replace ad-hoc `abort(403)` checks.

---

## Phase 0 — Foundation & cleanup (prerequisite) ✅ DONE

Small, high-leverage fixes that everything else depends on.

- [x] Fix `DashboardService::getSpendingByCategory()` — selected `receipt_items.total_price`; column is `total`. Fixed + regression test (`tests/Feature/Dashboard/DashboardServiceTest.php`).
- [x] Lock down Backpack admin: `CheckIfAdmin::checkIfUserIsAdmin()` now gates on `hasRole('admin')` (spatie). Covered by `tests/Feature/AdminAccessTest.php`.
- [x] Introduced `ReceiptPolicy` (auto-discovered); base `Controller` now uses `AuthorizesRequests`; inline `abort(403)` checks replaced with `$this->authorize(...)`. Covered by `tests/Feature/ReceiptAuthorizationTest.php`.
- [x] Standardized money on Eloquent `decimal` casts (no minor-unit columns); documented in `CLAUDE.md`.
- [x] Added currency-aware frontend helper `resources/js/utils/money.js`; refactored `Receipts/Index.jsx` and `Receipts/Show.jsx` to use it.
- [x] Added `App\Enums\BillingCycle` (`Weekly`, `Biweekly`, `Monthly`, `Quarterly`, `Yearly`) with `toMonthlyFactor()` / `toMonthlyAmount()`. Unit-tested.
- [x] Added `HasFactory` + factories for `Category`, `Receipt`, `ReceiptItem` (needed for tests and Phase 1+).

**Acceptance:** met — dashboard spend is correct, admin requires the `admin` role, ownership enforced via policy, 12 tests green.

**Note:** `formatCurrency` now renders `0` as `€0,00` instead of `N/A` (the old `!amount` check treated zero as missing) — intentional improvement for a budgeting app.

---

## Phase 1 — Providers & monthly contracts (fixed recurring expenses) ✅ DONE

Add recurring expenses (rent, insurance, internet, phone, streaming, gym, utilities)
with the company/provider behind each.

**Built:** `providers` + `contracts` tables/models (CrudTrait, HasFactory, relations, casts); `BillingCycle` gained `nextDate()`, new `ContractStatus` enum. `Contract::projectedMonthlyAmount()`. Inertia `ProviderController`/`ContractController` (ownership via `ProviderPolicy`/`ContractPolicy`) + `ProviderRequest`/`ContractRequest`; resource routes. Backpack CRUD for both (back-office). React pages: `Contracts/Index|Create|Edit|Show` (+ shared `ContractForm`) and `Providers/Index|Create|Edit` (+ `ProviderForm`); contract list grouped by category with monthly-normalized totals + next-due badges; nav links added; Inertia flash now shared. `contracts:roll-billing-dates` command scheduled daily. Tests cover cycle/date math, contract create/ownership/scoping, monthly-total exclusion of cancelled, and the roll command. **Note:** provider `slug`/`logo` and a provider-level category were dropped as unnecessary; no seeders added (factories only).

### Data model
- `providers` — `id, user_id, name, slug, category_id?, website, contact_email, contact_phone, logo?, notes, timestamps`
- `contracts` — `id, user_id, provider_id?, category_id?, name, description, amount, currency, billing_cycle (enum), billing_day (1–31 / weekday), start_date, end_date?, next_billing_date, status (active|paused|cancelled), auto_renew (bool), notes, timestamps`
- Models use `CrudTrait`; relationships: `Contract belongsTo Provider/Category/User`, `Provider hasMany Contract`.
- Factories + seeders for both. Backpack CRUD controllers (back-office).

### Backend
- `ContractController` + `ProviderController` (Inertia, resource routes, ownership-scoped).
- Form Requests (`ContractRequest`, `ProviderRequest`) with rules + messages.
- `Contract::projectedMonthlyAmount()` using `BillingCycle::toMonthlyFactor()`.
- A scheduled command (`contracts:roll-billing-dates`) to advance `next_billing_date` and flag due/overdue contracts.

### Frontend (React)
- `Pages/Contracts/Index|Create|Edit|Show` and `Pages/Providers/Index|Create|Edit`.
- Contract list grouped by category with monthly-normalized totals and "next due" badges.
- Reuse existing form components (`TextInput`, `InputError`, `<Form>`).

**Acceptance:** user can add a provider and a recurring contract; contract list shows correct monthly-equivalent cost and next billing date; everything user-scoped; feature tests cover create/update/ownership/cycle math.

---

## Phase 2 — Unified expense ledger & weekly/monthly overview ✅ DONE

Combine variable (receipts) + fixed (contracts) into one picture.

**Built:** `App\Services\ExpenseService` — `overview(userId, start, end, period)` (fixed from active contracts normalized monthly/weekly + variable from receipt totals, merged `by_category`), `variableTotal`, `variableByCategory`, `fixedByCategory`, and `trend(...)` buckets. `BillingCycle` gained `toWeeklyFactor()`/`toWeeklyAmount()`. `DashboardController` got `overview` + `trend` JSON endpoints (`/dashboard/overview`, `/dashboard/trend`) with month/week periods and previous-period delta. New `Components/ExpenseOverview.jsx` on the Dashboard: month/week switcher, total/fixed/variable cards with delta, fixed-vs-variable donut, category breakdown bars, stacked trend chart, loading skeletons. Tests cover overview totals, category merge, week-vs-month normalization, and trend buckets. **Note:** followed the existing axios+JSON dashboard convention rather than Inertia deferred props; fixed cost is a normalized projection (constant per period), so the month-over-month delta is driven by variable spend; single-currency aggregation assumed.

### Backend
- `ExpenseService` with:
  - `monthlyOverview(userId, month)` → totals split by `fixed` (contracts) vs `variable` (receipts), by category, plus net total.
  - `weeklyOverview(userId, weekStart)` → same shape for a week.
  - `trend(userId, range, granularity)` → time series for charts.
- New `DashboardController` endpoints: `/dashboard/overview/monthly`, `/dashboard/overview/weekly`, `/dashboard/trend`.

### Frontend
- Dashboard "Overview" section: total spend this month/week, fixed vs variable split (donut), category breakdown, month-over-month delta.
- Period switcher (week / month) and date-range picker; deep-link via query params.
- Skeleton/empty states per Inertia v2 deferred-prop guidance.

**Acceptance:** dashboard shows a unified weekly and monthly expense total that includes both receipts and active contracts, broken down by category, with a trend chart.

---

## Phase 3 — Receipt consumption analytics ✅ DONE

Make the receipt data answer "what am I buying most?"

**Built:** `App\Services\ConsumptionService` — `topItems(metric: quantity|spend, …)` (grouped per item, with quantity, spend, and purchase count; date-range + category filter where a parent includes its subcategories) and `vendorLeaderboard(…)` (receipt count + total spend per vendor). `DashboardController::consumption` JSON endpoint (`/dashboard/consumption`) returns top-by-quantity, top-by-spend, and vendors. New `/insights` Inertia page (`Pages/Insights.jsx`) with date-range + category filters, a most-consumed-by-quantity bar chart, a top-spend-by-item list, and a vendor leaderboard table; "Insights" nav link added. Filters use the existing `/dashboard/categories` endpoint. Tests cover quantity-vs-spend ranking, vendor leaderboard totals + scoping, and date filtering. **Note:** filters on `receipt_date` (purchase date, consistent with Phase 2), unlike the legacy `MostBoughtItemsChart` which uses `created_at`; treemap was rendered as ranked bars/list.

### Backend
- Extend `DashboardService`/new `ConsumptionService`:
  - Most-purchased items by quantity and by spend (you have a first version — generalize and fix joins).
  - Category/subcategory drill-down over time.
  - Vendor frequency and spend-per-vendor.
  - Largest/most-frequent line items.

### Frontend
- "Consumption" dashboard tab: top items (quantity & spend), category treemap/bar, vendor leaderboard, filters by date range and category (extend `MostBoughtItemsChart`).

**Acceptance:** user sees ranked most-consumed items and spend-by-category/vendor over any date range.

---

## Phase 4 — AI product catalog & price intelligence ("where can I save")

The differentiator. Resolve messy OCR item names to canonical products so prices can be
compared across vendors and time.

### Data model
- `products` — canonical catalog: `id, user_id?, name, normalized_name, brand?, unit?, size?, category_id?, attributes(json), timestamps`. (User-scoped or shared catalog — start user-scoped, allow a global catalog later.)
- `receipt_items.product_id` (nullable FK) — link each line item to a product.
- `price_observations` (optional, or derive on the fly from `receipt_items`): `product_id, vendor, unit_price, currency, observed_at, receipt_item_id`.

### Backend (AI)
- `ProductMatchingService` + `MatchReceiptItems` queued job (dispatched after `ProcessReceipt`):
  - For each line item, find-or-create a canonical `Product` via the LLM (reuse `LlmService`/OpenAI), passing existing products to bias matching (same pattern as the category prompt in `resources/views/prompts/`).
  - Persist `product_id` and a `price_observation`.
- `PriceIntelligenceService`:
  - Per-product price history & trend (rising/falling).
  - Cheapest vendor per product; current-vs-best-price gap.
  - **Savings opportunities:** items where you consistently pay above your observed minimum, or where price rose recently.

### Frontend
- "Savings" dashboard tab: price-per-product over time, cheapest-vendor table, and a ranked "potential monthly savings" list ("You paid €X for Y at Z; cheapest seen was €W at V").
- Product detail page: price chart across vendors + purchase history.

**Acceptance:** receipt items are auto-linked to canonical products; user sees price trends per product, cheapest vendor, and a concrete ranked savings list.

---

## Phase 5 — Budgets

Turn insight into limits.

### Data model
- `budgets` — `id, user_id, category_id?, period (monthly|weekly), amount, currency, starts_on, timestamps`.

### Backend
- `BudgetService`: budget-vs-actual per category/period (actual = `ExpenseService` output), projected end-of-period spend, over/under flags.
- Endpoints + Form Requests; optional threshold notifications (e.g. 80% / 100%).

### Frontend
- Budgets page: set per-category budgets; progress bars (green/amber/red); overview card on dashboard.

**Acceptance:** user sets category budgets and sees real-time budget-vs-actual including both fixed and variable spend.

---

## Phase 6 — Budgeting agent (AI assistant)

Tie it together into the "personalized budgeting agent."

- **Monthly digest:** queued job + `LlmService` summarizes the month (spend, vs budget, notable changes, upcoming contract renewals) → email/in-app.
- **Recommendations:** combine Phase 4 savings + Phase 5 budgets into prioritized, actionable advice.
- **Anomaly detection:** flag unusual spend, duplicate charges, contracts that increased.
- **Natural-language queries:** "How much did I spend on groceries last month?" — an endpoint that turns questions into safe, scoped queries over the ledger.
- **Renewal & due reminders:** from `contracts.next_billing_date`.

**Acceptance:** user receives a monthly digest with savings recommendations and renewal reminders, and can ask natural-language questions about their spending.

---

## Cross-cutting requirements (every phase)

- **Tests first/with code:** feature tests for controllers/services, unit tests for cycle math, normalization, and matching; use factories. Run `php artisan test --filter=...`.
- **Style:** `vendor/bin/pint --dirty` before finalizing.
- **Queue:** new background work uses `ShouldQueue` and the existing job conventions (`tries`, `timeout`, status + `error_message` on failure).
- **Dark mode + Tailwind v3** for all new React UI; reuse existing components.
- **Config over env:** external keys via `config/services.php`.
- **Frontend build:** `npm run dev` / `npm run build` after UI changes.

## Suggested delivery order

`Phase 0 → 1 → 2` gives the biggest immediate value (contracts + a true unified
weekly/monthly overview). `Phase 3` is mostly generalizing existing dashboard code.
`Phase 4` (AI catalog) is the largest investment and the key differentiator. `Phase 5–6`
layer budgets and the agent on top.

## Open questions / assumptions

- Multi-currency: assumed single primary currency per user for overviews/budgets; mixed-currency aggregation (FX) is out of scope unless needed.
- Product catalog scope: starts user-scoped; a shared/global catalog can be added later for cross-user price benchmarking.
- Notifications channel (email vs in-app vs both) for digests/reminders — to be decided in Phase 5/6.
