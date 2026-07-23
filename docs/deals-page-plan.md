# Deals Page — Implementation Plan

> Status: **planned, not started.** Approved 2026-07-23. Gated on a data spike (see §7).
> Retailers in scope: **Lidl, Penny, Edeka, Netto, Aldi, Kaufland.**

A page that surfaces current weekly discounter offers, matched against what the
user actually buys. The value is not a generic deals list — it is the tie-in
with the existing consumption analytics and AI assistant ("eggs you buy are
€0.99 at Aldi this week").

## 1. Locked-in decisions

| Decision | Choice | Rationale |
| --- | --- | --- |
| Data source | **Aggregator first** (`MarktguruSource`) behind a `DealSource` interface | One integration covers all six retailers; direct-retailer adapters added later without rework. |
| Regionality | **Region-aware from day one** | Edeka/Netto pricing varies by store; a postal code is required per user. |
| Integration | **Deep** — consumption + assistant | Reuses `ConsumptionService` and the chat pipeline; this is the differentiator. |

## 2. The core constraint

**None of the six retailers publish an official public offers API.** Sourcing
options, ranked:

1. **Aggregator (chosen).**
   - **marktguru** (`marktguru.de`) — aggregates all six, region-aware by postal
     code. Unofficial JSON backend (consumed by its mobile app). Richest single
     source; Phase-1 adapter.
   - **Bonial** (kaufDA / MeinProspekt) — leaflet aggregator with a **B2B partner
     API**. The most defensible long-term path if this ever goes public-facing.
2. **Direct retailer app APIs** — each retailer's private app endpoint. Richer
   per retailer but six brittle, reverse-engineered integrations to maintain.
   (Lidl + Kaufland = Schwarz Group; Penny = REWE Group; Netto = Edeka Group;
   Aldi Süd/Nord separate.)
3. **HTML / leaflet-PDF scraping** — most brittle, highest ToS/copyright
   exposure. Last resort per retailer.

### Legal / operational guardrails
- Sources are **unofficial**: respect `robots.txt` + ToS, rate-limit hard, cache
  aggressively. Offers change **weekly**, so a **daily** sync is ample.
- **Copyright**: prices are facts (not protected); leaflet *images* and layouts
  are. Store text/price data, attribute the source, avoid caching leaflet
  images. Prefer the Bonial partner API if this goes beyond personal use.
- Regional pricing requires a **postal code / store** per user.

## 3. Architecture

Adapter pattern, mirroring how `LlmService` is injected and mockable.

```
App\Services\Deals\DealSource (interface)   → fetch(string $region): array<NormalizedDeal>
  ├─ MarktguruSource                          (Phase 1)
  ├─ LidlSource, KauflandSource, …            (Phase 3, optional)
App\Services\Deals\DealSyncService          → run sources → normalize → map category → upsert → prune
```

## 4. Data model

New migrations. Per the project rule, keep any destructive schema change in a
separate release from the backfill that depends on it.

**`retailers`** — `name`, `slug`, `logo`, `group`.

**`deals`**
| Column | Notes |
| --- | --- |
| `retailer_id` | FK → `retailers` |
| `external_id` | id from the source |
| `title`, `brand`, `product_name` | |
| `price` | `decimal:2` (matches app money convention) |
| `old_price`, `discount_pct` | nullable |
| `unit` | e.g. "500g", "per kg" |
| `valid_from`, `valid_to` | date |
| `category_id` | FK → existing `Category` taxonomy |
| `image_url` | external, not cached |
| `source`, `region` | provenance + postal/area key |

Unique index on **`(source, external_id, region)`** for idempotent upserts.

**`users`** — add `postal_code` (+ optional `preferred_retailers`).

## 5. Ingestion

- `App\Services\Deals\DealSyncService`: run each `DealSource`, normalize →
  map to the app's `Category` taxonomy → upsert → prune expired.
- `deals:sync` console command, scheduled **daily** in `routes/console.php`
  (alongside `contracts:roll-billing-dates`).
- Queued **per distinct region in use**, so cost scales with regions, not users.

## 6. Deep integration (the differentiator)

- **"Deals on things you buy"** — match incoming deals to each user's frequent
  items via `ConsumptionService::searchItems` / `topItems`; map deal
  `category_id` to tracked categories. Surface on the deals page + dashboard.
- **Savings estimate** — compare deal `price` against the user's historical
  `unit_price` for that item.
- **Assistant `deals_search` intent** — add to `SpendingQueryExecutor` + the
  `spending-question` / `spending-answer` prompts so the chat answers
  "any deals on coffee near me?". A `@Retailer` mention drops in as a 4th
  `<Mention>` source in `AssistantChat`, reusing the existing mention pipeline.

## 7. Phasing

0. **Spike (do first, 1–2 days) — the gate.** Confirm one marktguru region call
   returns usable JSON with the fields in §4. Needs a live outbound call run
   locally. **Everything downstream depends on the actual payload** — validate
   before building the model.
1. **Phase 1:** `retailers` / `deals` schema, `MarktguruSource` +
   `DealSyncService` + `deals:sync`, per-user postal code, basic
   `Pages/Deals/Index.jsx` (retailer chips, category filter, search,
   valid-until badges).
2. **Phase 2:** category mapping + "deals on your items" + savings estimate.
3. **Phase 3:** `deals_search` assistant intent + `@Retailer` mention; optional
   direct-retailer adapters.

## 8. Frontend

`Pages/Deals/Index.jsx` in the existing design system: retailer chips, category
dropdown (reused), free-text search, valid-until badges, "matches your buys"
sorted first. Postal-code selector in settings. Deferred / `WhenVisible` grid
loading (Inertia v2).

## 9. Open risks

- **Spike outcome** — thin/unstable aggregator data would force a model rethink
  or a pivot to direct-retailer adapters or Bonial.
- **ToS stability** — unofficial endpoints can change without notice; isolate
  each behind its adapter so a break is contained.
- **Region coverage** — verify the aggregator returns data for the postal codes
  the actual user base uses.
