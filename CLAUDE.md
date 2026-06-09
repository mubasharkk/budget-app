# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Budget App is a receipt manager. Users upload receipt images/PDFs; the app OCRs them, then uses an LLM to extract structured data (vendor, total, line items) and auto-categorize each item. A dashboard visualizes spending. Stack: Laravel 12 + Inertia v2 + React 18 + Tailwind v3, with a Backpack-powered admin panel.

## Common Commands

```bash
# Full local dev (server + queue worker + log tail + vite) — run via Sail or natively
composer run dev

# Frontend assets
npm run dev          # vite dev server
npm run build        # production build (run if UI changes don't show up)

# Tests
php artisan test                                   # all tests
php artisan test tests/Feature/Auth/LoginTest.php  # single file
php artisan test --filter=testName                 # single test

# Code style — always run before finalizing
vendor/bin/pint --dirty

# Queue worker is REQUIRED for receipt processing to do anything
php artisan queue:listen --tries=1
```

The app runs under Laravel Sail (Docker, MySQL 8). Prefix artisan/composer/npm with `./vendor/bin/sail` when using Sail. The queue connection must have a running worker or uploaded receipts stay `pending` forever.

## Receipt Processing Pipeline (core flow)

This is the heart of the app and spans multiple files:

1. **Upload** — `ReceiptController@store` accepts up to 5 files. Non-PDF images are converted to PNG via Intervention Image; PDFs stored as-is. Files go to `storage/app/public/receipts/{year}/{month}/`. A `Receipt` row is created with `status = 'pending'`, then `ProcessReceipt` is dispatched.
2. **Vision parse (OCR + extraction in one step)** — `App\Jobs\ProcessReceipt` resolves `App\Services\LlmService` (injected into `handle()` so it's mockable) and calls `parseReceiptFromFile($path, $mime)`. That sends the raw file to an OpenAI **vision** model (`config('services.openai.model')`, default `gpt-4o`) as a multimodal message — images via an `image_url` data URI, PDFs via a `file`/`file_data` part — using the prompt in `resources/views/prompts/receipt-parsing.blade.php` with `response_format: json_object`. There is **no separate OCR service**; the model reads the document directly. The model returns strict JSON including an `is_receipt` flag (stored in `ocr_data`). Non-receipts are marked `processed` with zeroed values. For receipts, it writes vendor/currency/total/date and creates `ReceiptItem` rows. Categories/subcategories are **find-or-created per item** (parent vs child via `parent_id`). On failure: `status = 'failed'` + `error_message`.
3. **Retry** — `ReceiptController@retry` re-dispatches `ProcessReceipt`.

The job has `tries = 3` and `timeout = 300`. Receipt status lifecycle: `pending → processed | failed`.

## Architecture Notes

- **Two distinct frontends.** End-user UI is Inertia + React (`resources/js/Pages`, `resources/js/Components`), routed in `routes/web.php` via `ReceiptController` and `Dashboard\DashboardController`. The `/admin` panel is **Backpack CRUD** (Blade-based, separate auth) — controllers in `app/Http/Controllers/Admin/`, routes in `routes/backpack/custom.php`. Models use Backpack's `CrudTrait`. Don't conflate the two; a model change can affect both.
- **Backpack admin auth**: `app/Http/Middleware/CheckIfAdmin::checkIfUserIsAdmin()` gates on `hasRole('admin')`. The `admin` role + an admin user are created by `AdminRoleSeeder`/`AdminUserSeeder`.
- **Roles/permissions** use `spatie/laravel-permission` (custom `Role`/`Permission` models). `User` has `HasRoles`.
- **Auth** is Laravel Breeze (Inertia/React) plus Google OAuth via Socialite (`Auth\GoogleController`, `services.google` config, `google_id` on users).
- **Dashboard** logic lives in `app/Services/Dashboard/DashboardService.php`; the controller exposes JSON endpoints (`/dashboard/stats`, `/dashboard/chart/data`, etc.) consumed by React via axios (the dashboard convention — not Inertia props). Note this service uses raw `DB::` query-builder joins for aggregation rather than Eloquent.
- **Expense overview (Phase 2):** `App\Services\ExpenseService` unifies fixed (active contracts, normalized monthly/weekly) + variable (receipts) spend; endpoints `/dashboard/overview` and `/dashboard/trend`; UI in `Components/ExpenseOverview.jsx`. Aggregations use `receipt_date` (purchase date), not `created_at`.
- **Consumption analytics (Phase 3):** `App\Services\ConsumptionService` (top items by quantity/spend, vendor leaderboard); endpoint `/dashboard/consumption`; the `/insights` page (`Pages/Insights.jsx`). Category filter treats a parent category as including its subcategories.
- **Categories are self-referential** (`Category.parent_id`): parents have `parent_id = null`, subcategories point to a parent. Use `isParent()`/`isSubcategory()`.
- **External service config** lives in `config/services.php` (`openai`, `google`). Access via `config()`, never `env()` outside config files.
- **Money:** stored as Eloquent `decimal` casts (`total_amount` `decimal:2`, `unit_price` `decimal:4`, `total` `decimal:2`) — this is the standard; do **not** introduce integer-minor-unit columns. Currency is per-record (`EUR` default). Format on the frontend with `formatCurrency` from `resources/js/utils/money.js` rather than inline `Intl.NumberFormat`.
- **Authorization:** receipt access goes through `ReceiptPolicy` via `$this->authorize(...)` in controllers (the base `Controller` uses `AuthorizesRequests`). Follow this for new owned resources instead of inline `user_id` checks.
- **Recurring/billing math:** use the `App\Enums\BillingCycle` enum (`toMonthlyFactor()` / `toMonthlyAmount()` / `nextDate()`) for any recurring-amount normalization.
- **Recurring expenses (Phase 1):** `Provider` (companies) and `Contract` (recurring charges) models, both `user_id`-scoped with `ProviderPolicy`/`ContractPolicy`. `Contract` casts `billing_cycle`→`BillingCycle`, `status`→`ContractStatus`, and appends `projected_monthly_amount`. Managed in the React UI (`Pages/Contracts/*`, `Pages/Providers/*`, routes `contracts.*`/`providers.*`) and mirrored in Backpack. `contracts:roll-billing-dates` (scheduled daily in `routes/console.php`) advances `next_billing_date` and cancels contracts past `end_date`.

## Design system

Brand colors, logo direction, and UI templates live in `DESIGN.md`. Tailwind tokens: `brand-primary`, `brand-light`, `brand-mid`, `brand-dark`, `chart-fixed`, `chart-variable`.

## Files & Storage

- Uploaded files live on the `public` disk; serve them via `ReceiptController@file` (ownership-checked) rather than direct URLs for private access. `storage:link` must be run for the public URL accessors.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.21
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/socialite (SOCIALITE) - v5
- tightenco/ziggy (ZIGGY) - v2
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v18
- tailwindcss (TAILWINDCSS) - v3


## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] <name>` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit <name>` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== inertia-react/core rules ===

## Inertia + React

- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="react">

import { Link } from '@inertiajs/react'
<Link href="/">Home</Link>

</code-snippet>


=== inertia-react/v2/forms rules ===

## Inertia + React Forms

<code-snippet name="`<Form>` Component Example" lang="react">

import { Form } from '@inertiajs/react'

export default () => (
    <Form action="/users" method="post">
        {({
            errors,
            hasErrors,
            processing,
            wasSuccessful,
            recentlySuccessful,
            clearErrors,
            resetAndClearErrors,
            defaults
        }) => (
        <>
        <input type="text" name="name" />

        {errors.name && <div>{errors.name}</div>}

        <button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
        </button>

        {wasSuccessful && <div>User created successfully!</div>}
        </>
    )}
    </Form>
)

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v3 rules ===

## Tailwind 3

- Always use Tailwind CSS v3 - verify you're using only classes supported by this version.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.
</laravel-boost-guidelines>