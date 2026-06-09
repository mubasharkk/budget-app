# Budget — Logo & Design Template Guide

Brand and UI reference for the AI budgeting assistant: receipt scanning, spending insights, budgets, and proactive recommendations.

---

## 1. Brand positioning

| Attribute | Direction |
|-----------|-----------|
| **What it is** | Your personal AI money copilot — scans receipts, tracks spend, suggests savings |
| **Tone** | Calm, intelligent, trustworthy — not a bank, not a game |
| **Personality** | Helpful advisor · precise · modern · mobile-first |
| **Avoid** | Piggy banks, dollar signs, robot mascots, neon “crypto” aesthetics |

**One-line positioning:**

> Budget is your AI assistant for everyday spending — snap a receipt, understand your money.

---

## 2. Logo system

### 2.1 Concept (AI assistant)

Primary metaphor: **receipt + insight spark**

- **Receipt/document** → capture, OCR, real purchases
- **Spark / orb / soft glow** → AI insights, digests, recommendations
- Optional **scan corner** → camera upload

The mark should read as *“smart document assistant”*, not *“bank app”*.

### 2.2 Logo variants

| Variant | Use |
|---------|-----|
| **App icon** | Symbol only, square, no text |
| **Nav mark** | Symbol only, 32–36px height |
| **Lockup** | Symbol + wordmark “Budget” (marketing, login, App Store) |
| **Monochrome** | White on indigo, indigo on white, single-color gray |

### 2.3 Construction rules

- **Max 3 visual elements** (e.g. receipt shape + spark + scan corner)
- **Stroke weight:** ≥ 2px at 48px size; no hairlines
- **Corners:** rounded (8–12% of icon size)
- **Clear space:** padding = 15% of icon width on all sides
- **No:** gradients on small icons, drop shadows, 3D, text inside the icon

### 2.4 Color versions

```
Primary icon on brand background:
  Background: #4F46E5 (brand-primary)
  Symbol:     #FFFFFF

Inverse:
  Background: #FFFFFF
  Symbol:     #4F46E5

Insight accent (optional, ≤15% of mark):
  Spark/glow: #818CF8 (brand-mid) or #10B981 (success)
```

### 2.5 Export sizes

| Asset | Size | Format |
|-------|------|--------|
| App Store / Play Store | 1024×1024 | PNG |
| PWA | 512×512, 192×192 | PNG |
| Favicon | 48×48, 32×32 | PNG / ICO |
| Nav / header | SVG | SVG |
| Splash / OG image | 1200×630 | PNG |

### 2.6 Logo generation prompt (AI assistant)

```
Minimal flat vector app logo for "Budget" — AI personal budgeting assistant.
Stylized receipt/document with a single soft insight spark/orb (intelligence, recommendations).
Optional subtle scan-frame corner. Indigo #4F46E5 and white. Geometric, calm, copilot aesthetic.
No text, no robot face, no dollar signs. App icon, transparent background, favicon-safe at 48px.
--no piggy bank wallet bank vault cyberpunk neon 3d robot mascot
```

---

## 3. Color palette

Use Tailwind `brand-*` tokens (defined in `tailwind.config.js`) or the mapped defaults below.

### 3.1 Brand

| Token | Hex | Tailwind alias | Usage |
|-------|-----|----------------|--------|
| `brand-primary` | `#4F46E5` | `indigo-600` | FAB, mobile CTAs, links, focus rings, PWA theme |
| `brand-light` | `#EEF2FF` | `indigo-50` | Insight cards, income panels |
| `brand-mid` | `#818CF8` | `indigo-400` | AI spark accent in logo/marketing |
| `brand-dark` | `#3730A3` | `indigo-800` | Hover on brand buttons |

### 3.2 Neutrals

| Token | Hex | Tailwind | Usage |
|-------|-----|----------|--------|
| Page background | `#F3F4F6` | `gray-100` | App shell |
| Surface / card | `#FFFFFF` | `white` | Cards, modals |
| Border | `#E5E7EB` | `gray-200` | Dividers, upload zones |
| Text primary | `#111827` | `gray-900` | Headings, amounts |
| Text secondary | `#6B7280` | `gray-500` | Labels, captions |
| Text muted | `#9CA3AF` | `gray-400` | Placeholders, empty states |

### 3.3 Semantic (spend & status)

| Meaning | Hex | Tailwind | Usage |
|---------|-----|----------|--------|
| On track / success | `#10B981` | `emerald-500` | Budget OK, processed receipt |
| Warning / near limit | `#F59E0B` | `amber-500` | 80–100% budget |
| Over / error | `#EF4444` | `red-500` | Over budget, failed OCR |
| Pending | `#EAB308` | `yellow-500` | Processing queue |

### 3.4 Data visualization

| Series | Hex | Usage |
|--------|-----|--------|
| Fixed costs | `#6366F1` | Donut/bar — contracts |
| Variable costs | `#10B981` | Donut/bar — receipts |
| Income | `#4F46E5` | Income vs spend panels |
| Budget allocation | `#6366F1` | Progress bars |

### 3.5 Accessibility

- Body text on white: use `gray-900` or `gray-700` (≥ 4.5:1 contrast)
- `brand-primary` on white for links: OK for large text; use `brand-dark` for small link text if needed
- Status: never rely on color alone — always include a text label

---

## 4. Typography

**Primary font:** [Figtree](https://fonts.bunny.net/css?family=figtree) (loaded in `resources/views/app.blade.php`)

| Role | Size | Weight | Tailwind |
|------|------|--------|----------|
| Page title | 24px | 700 | `text-2xl font-bold` |
| Section title | 18px | 500 | `text-lg font-medium` |
| Card value | 20–24px | 600 | `text-xl font-semibold` |
| Body | 14px | 400 | `text-sm` |
| Caption / meta | 12px | 400–500 | `text-xs text-gray-500` |
| Button (legacy) | 12px | 600 uppercase | `text-xs font-semibold uppercase tracking-widest` |
| Button (modern/mobile) | 14px | 600 | `text-sm font-semibold` |

**Money amounts:** same font, `font-semibold` or `font-bold`; always format with `formatCurrency()` from `resources/js/utils/money.js`.

**AI / assistant copy:** sentence case, short sentences, no ALL CAPS except tiny badges.

---

## 5. Iconography

**Library:** Heroicons 24 outline (preferred), Font Awesome where legacy remains.

| Context | Icon |
|---------|------|
| Scan / camera | `CameraIcon` |
| Upload | `ArrowUpTrayIcon` / `PlusIcon` |
| Receipt | `DocumentIcon` |
| Insights | `ChartBarIcon` |
| Savings | Sparkles or trend-down |
| Assistant | `ChatBubbleLeftRightIcon` or spark motif |
| Budget | Chart / gauge |

**Rules:** 20–24px inline, 28–32px in FABs, stroke 1.5–2, color matches text or brand.

---

## 6. UI component templates

### 6.1 Page shell

```
Background:     bg-gray-100 min-h-screen
Content max:    max-w-7xl mx-auto (dashboard) / max-w-lg (scan)
Card:           bg-white shadow-sm sm:rounded-lg overflow-hidden
Section gap:    space-y-6 (use gap utilities, not margin stacks)
Page padding:   py-12 desktop / py-6 mobile, px-4 sm:px-6
```

### 6.2 Primary CTA (mobile / scan)

```html
<button class="rounded-lg bg-brand-primary px-4 py-3 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
  Take photo
</button>
```

Floating action (mobile):

```html
<a class="fixed bottom-6 right-6 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-brand-primary text-white shadow-lg hover:bg-brand-dark sm:hidden">
  <!-- CameraIcon h-7 w-7 -->
</a>
```

### 6.3 Cards

**Standard**

```
rounded-lg border border-gray-100 bg-white p-4 shadow-sm
hover:border-brand-mid/40 hover:shadow-md   (tappable snapshot cards)
```

**Insight / AI**

```
rounded-lg bg-brand-light p-4
Label: text-sm text-brand-primary
Value: text-xl font-semibold text-gray-900
```

**Stat**

```
rounded-lg bg-gray-50 p-4
Label: text-sm text-gray-500
Value: text-xl font-semibold text-gray-900
```

### 6.4 Upload zone

```
Grid 2×2 mobile, 4 columns desktop:
  border-2 border-dashed border-gray-200 rounded-xl bg-gray-50 p-6
  hover:border-brand-mid hover:bg-brand-light
  Icon: h-8 w-8 text-brand-primary
  Label: text-sm font-semibold text-gray-900
```

### 6.5 Period switcher

```
inline-flex rounded-md border border-gray-200 bg-white p-0.5
Active:   bg-gray-800 text-white rounded px-3 py-1 text-sm font-medium
Inactive: text-gray-600 hover:text-gray-900
```

### 6.6 Progress bars

| State | Bar class |
|-------|-----------|
| On track | `bg-emerald-500` |
| Warning | `bg-amber-500` |
| Over | `bg-red-500` |
| Budget vs income | `bg-brand-primary` |
| Track | `bg-gray-100 h-2 rounded-full` |

### 6.7 Status badges

```
pending:    bg-yellow-100 text-yellow-800
processed:  bg-green-100 text-green-800
failed:     bg-red-100 text-red-800
on_track:   text-emerald-600
warning:    text-amber-600
over:       text-red-600
```

### 6.8 Empty states

```
border border-dashed border-gray-200 rounded-lg py-10 text-center
Message: text-sm text-gray-500
Action:  text-sm font-medium text-brand-primary hover:text-brand-dark
```

### 6.9 AI assistant panel

```
Header: text-lg font-medium text-gray-900
Subcopy: text-sm text-gray-500
Item: rounded-lg border border-gray-100 p-4
High priority: border-l-4 border-l-amber-500
Digest: bg-gray-50 rounded-lg p-4
```

---

## 7. Layout & spacing

| Token | Value | Use |
|-------|-------|-----|
| `gap-3` | 12px | Tile grids |
| `gap-4` | 16px | Stat card grids |
| `gap-6` | 24px | Section separation |
| `p-4` / `p-6` | 16 / 24px | Card padding |
| `rounded-lg` | 8px | Cards, buttons |
| `rounded-xl` | 12px | Upload tiles |
| `rounded-2xl` | 16px | Scan page hero |

**Mobile-first:** design scan/upload at `max-w-lg` first; expand for dashboards.

---

## 8. Motion & feedback

| Action | Feedback |
|--------|----------|
| Upload start | `bg-brand-light text-brand-primary` banner |
| Upload success | `bg-green-50 text-green-700` banner |
| Processing | Yellow “Pending” badge |
| AI loading | `animate-pulse bg-gray-200` skeleton |
| Transitions | `transition duration-150 ease-in-out` |

---

## 9. Voice & copy (AI assistant)

| Do | Don't |
|----|-------|
| “You spent 23% of income on groceries.” | “ALERT: GROCERIES CRITICAL” |
| “You could save €4.20 at Lidl vs your last purchase.” | “Optimize your consumption matrix” |
| “3 contracts renew in the next 14 days.” | “Warning: billing events detected” |

**Button labels:** verb-first, ≤3 words — “Take photo”, “Scan receipt”, “Ask assistant”.

---

## 10. PWA & mobile install

`public/manifest.json`:

```json
{
  "name": "Budget App",
  "short_name": "Budget",
  "theme_color": "#4f46e5",
  "background_color": "#ffffff",
  "start_url": "/receipts/scan"
}
```

**Splash:** white background, centered logo on indigo tile or indigo background with white mark.

---

## 11. New screen checklist

- [ ] Page title: `text-2xl font-bold text-gray-900`
- [ ] `AuthenticatedLayout` + `bg-gray-100` shell
- [ ] Cards: white, `shadow-sm`, `rounded-lg`
- [ ] Primary action: `bg-brand-primary` on mobile-first flows
- [ ] Loading: skeleton, not spinner-only
- [ ] Empty state with dashed border + action link
- [ ] Money via `formatCurrency()`
- [ ] Status colors from semantic palette
- [ ] Icons from Heroicons outline set

---

## 12. Asset file placement

| Asset | Path |
|-------|------|
| App icon PNGs | `public/icons/icon-192.png`, `icon-512.png` |
| Favicon | `public/favicon.ico` |
| Logo SVG | `public/logo.svg` |
| Nav component | `resources/js/Components/ApplicationLogo.jsx` |
| Manifest | `public/manifest.json` |
| OG image | `public/og-image.png` |

---

## 13. Quick reference

```
Brand:     Budget — AI budgeting assistant
Logo:      Receipt + insight spark, #4F46E5
Font:      Figtree
Page bg:   gray-100
Card:      white, shadow-sm, rounded-lg
CTA:       brand-primary (mobile), gray-800 (legacy forms)
Success:   emerald-500
Warning:   amber-500
Error:     red-500
Charts:    fixed indigo-500 · variable emerald-500
Mobile:    FAB bottom-right, /receipts/scan hero flow
```
