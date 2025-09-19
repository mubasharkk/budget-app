# Build Brief for AI Coding Agent (Laravel)

## Goal

Add **invoice/receipt ingestion** to an existing Laravel app:

* Mobile-first upload (direct camera capture), plus photo/PDF file upload.
* Send to external OCR if needed.
* Send OCR text to GPT for **category + subcategory** classification and **line-item extraction**.
* If GPT returns a **new category/subcategory**, create it automatically.
* Seed baseline **Groceries** and **Building** categories (with common subcategories).

---

## Environment & Services

* Laravel (current project).
* Storage: `storage/app/public/receipts` (public symlink required).
* Queue: use Laravel queues for OCR/GPT jobs (e.g., `database` driver).
* External OCR service (provided):

    * Base: `OCR_NEXT_SERVER=http://ocr-next-api`
    * Auth:

        * `OCR_NEXT_API_KEY=fb808fa73b2040fa92498ffe181c8348`
        * `OCR_NEXT_API_TOKEN=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhcGlfa2V5IjoiZmI4MDhmYTczYjIwNDBmYTkyNDk4ZmZlMTgxYzgzNDgifQ.nRZW8kCVaHQHLh9jgbpnBfGl22HxKe3sO8k4CkTky8E`
    * Endpoints:

        * Image OCR: `POST /v1/extract/image/text`
        * PDF OCR: `POST /v1/extract/pdf/text`
* LLM: OpenAI (or equivalent) for classification + parsing (pass OCR text only).

---

## UX Scope (Inertia/React or Blade)

* **Upload screen (mobile-first):**

    * Buttons: “Scan with Camera”, “Upload Photo/PDF”.
    * Camera capture for mobile (`<input type="file" accept="image/*" capture="environment">` or getUserMedia).
    * Accept: `.jpg,.jpeg,.png,.heic,.webp,.pdf`.
    * Show selected file preview (image thumbnail or PDF icon).
    * Submit triggers background processing + shows **processing state** and later a **result detail** view.
* **Result Detail view:**

    * Original file preview.
    * OCR text (collapsible).
    * Category + Subcategory (editable selects).
    * Items table (name, qty, unit price, total; editable).
    * Save/Update buttons.
* **List view:** Paginated receipts with status badges: `Pending`, `Processed`, `Failed`.

---

## Routes (names only, no code)

* `POST /receipts` — upload endpoint (sync validation; async processing).
* `GET /receipts/{id}` — show details.
* `PATCH /receipts/{id}` — update category/items after user edits.
* `GET /categories` — list for selects.
* `POST /webhooks/ocr` *(optional if OCR supports callbacks; otherwise poll/inline)*.

---

## Data Model (describe only)

* **Category**

    * Fields: `id`, `name` (string), `parent_id` (nullable), timestamps.
    * Unique constraint: (`parent_id`,`name`).
* **Receipt**

    * Fields: `id`, `original_path` (string), `mime` (string), `ocr_text` (long text, nullable),
      `category_id` (nullable), `subcategory_id` (nullable), `vendor` (nullable),
      `currency` (3-char, nullable), `total_amount` (decimal 12,2 nullable),
      `status` (enum: pending, processed, failed), timestamps.
* **ReceiptItem**

    * Fields: `id`, `receipt_id` (fk), `name` (string),
      `quantity` (decimal 10,3), `unit_price` (decimal 12,4), `total` (decimal 12,2), timestamps.

---

## Processing Flow

1. **Upload** (sync):

    * Validate file size (≤ 15 MB), allowed MIME (images/PDF).
    * Store file under `public/receipts/{yyyy}/{mm}/{uuid}.{ext}`.
    * Create `Receipt{ status=pending }`.
    * Dispatch job: `ProcessReceipt`.
2. **OCR** (in job):

    * If MIME is PDF → call `/v1/extract/pdf/text`.
    * Else → call `/v1/extract/image/text`.
    * Auth headers: `x-api-key`, `Authorization: Bearer <OCR_NEXT_API_TOKEN>`.
    * If OCR empty/low-confidence → still pass what’s available; mark note.
    * Save `ocr_text` on Receipt.
3. **LLM Parsing**:

    * Send prompt (below) with `ocr_text`.
    * Expect strict JSON (schema below); retry once on invalid JSON.
4. **Persist**:

    * Upsert **Category** and **Subcategory** if not existing (auto-create).
    * Update Receipt: `category_id`, `subcategory_id`, `vendor`, `currency`, `total_amount`, `status=processed`.
    * Upsert **ReceiptItems** from parsed payload.
5. **Error handling**:

    * Any failure → `status=failed` and store `error_message` (separate log column or metadata table).
    * User sees actionable error & can retry.

---

## External API Contracts (expected)

### OCR Request

* `Content-Type: multipart/form-data` with `file` field.
* Headers:

    * `x-api-key: {OCR_NEXT_API_KEY}`
    * `Authorization: Bearer {OCR_NEXT_API_TOKEN}`

### OCR Response (assumed)

* `{ "text": "<extracted string>", "confidence": <0-1> }`
* If differing response, normalize to `text` string.

---

## LLM Prompt (classification + parsing)

**System message summary**
“You extract structured purchase data (category, subcategory, vendor, currency, total, and line items) from raw OCR text of receipts/invoices. You return strict JSON only.”

**User content template (variables in <>):**

* OCR text (verbatim).
* Locale hint: `"Country: DE (EUR default), language may vary."`
* Rules:

    * Classify into: `category` and `subcategory`. Prefer existing categories list (provided below). If none fits, propose a **new** category and/or subcategory.
    * Extract: `vendor`, `currency` (ISO 4217), `total_amount`.
    * Extract line items: `name`, `quantity` (default 1 if missing), `unit_price`, `total` (unit\_price × quantity).
    * Numbers in dot decimal; no currency symbols.
    * If something is unknown, set `null`.

**JSON output (strict)**

```json
{
  "category": "Groceries",
  "subcategory": "Dairy",
  "vendor": "REWE",
  "currency": "EUR",
  "total_amount": 23.45,
  "items": [
    {"name": "Milk 1L", "quantity": 2, "unit_price": 1.19, "total": 2.38},
    {"name": "Butter 250g", "quantity": 1, "unit_price": 2.29, "total": 2.29}
  ],
  "notes": null
}
```

**Known categories to bias (send list):**

* **Groceries**

    * Fruits, Vegetables, Dairy, Bakery, Beverages, Snacks, Meat, Frozen, Household, Personal Care
* **Building**

    * Tools, Hardware, Plumbing, Electrical, Paint, Lumber, Fasteners, Adhesives/Sealants, Safety

*(If GPT outputs a category/subcategory not in this list, treat it as “new” and create it.)*

---

## Auto-Add Category/Subcategory Logic

* Try to match case-insensitive against existing names under same parent.
* If not found:

    * Create **Category** if parent missing.
    * Create **Subcategory** under that category.
* Prevent duplicates via unique (`parent_id`,`name`).

---

## Security & Compliance

* Limit upload size & MIME type.
* Virus scan step (optional) before OCR.
* Store secrets in `.env`, never in code.
* Timeouts & retries for OCR/LLM with exponential backoff.
* Rate limit upload endpoint (e.g., 20/min per user).
* Log request IDs for traceability; do **not** persist raw API tokens in DB.

---

## Validation & Calculations

* Recompute each item `total = quantity * unit_price` when saving.
* If item totals sum differs from `total_amount` by small epsilon (e.g., 0.02), keep both but flag `notes`.
* Currency default to `EUR` if absent and vendor appears EU/DE; otherwise null.

---

## Seeder (description only)

* Create parent categories:

    * **Groceries** with subcategories: Fruits, Vegetables, Dairy, Bakery, Beverages, Snacks, Meat, Frozen, Household, Personal Care
    * **Building** with subcategories: Tools, Hardware, Plumbing, Electrical, Paint, Lumber, Fasteners, Adhesives/Sealants, Safety
* Idempotent: calling multiple times must not duplicate.
* Constraint: unique per (`parent_id`,`name`).

---

## Acceptance Criteria

* Upload works on mobile camera and file picker (image/PDF).
* A new upload creates a `Receipt(pending)` → after background jobs, receipt is `processed` with:

    * Stored file path, OCR text saved.
    * Category + Subcategory set (auto-created if new).
    * Vendor, currency, total set.
    * Items persisted with correct math.
* Users can edit category/subcategory/items and save.
* Failed jobs surface clear error with retry option.
* Seeder creates the baseline taxonomy exactly once.
* All sensitive keys live in `.env`.
* Jobs are queued and non-blocking to the user.

---

## QA Checklist

* Test with: crisp image, blurry image (forces OCR), multi-page PDF, non-grocery “Building” invoice.
* Test new subcategory creation (“Garden Supplies” under Building).
* Test concurrency: multiple uploads in parallel.
* Test large receipt (many items).
* Test invalid file (blocked), oversize file (blocked).
* Verify EU decimal handling and rounding consistency.

---

## Deliverables

* Routes, requests, jobs, services, and models per above spec.
* Seeder for categories as described.
* Minimal UI for upload + results with edit/save.
* README snippet documenting env keys and flow.
