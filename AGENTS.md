# Agent / LLM Guide — Bibleasy Backend

This document helps automated assistants and contributors understand **how this Laravel API is structured** so new work stays consistent with existing patterns.

## What this project is

- **REST API only** — No Blade views; JSON responses for a Bible reading app ([Bibleasy](https://bibleasy.com)).
- **Domain**: biblical versions, books, chapters, verses, cross-references; optional multi-version chapter comparison; admin-managed version import (USFM / JSON); chapter content from **PostgreSQL** or **Api.Bible** depending on version configuration.
- **Stack**: Laravel 12, PHP ^8.2, PostgreSQL, Redis (cache / rate limiting), Laravel Sanctum, Socialite for OAuth flows.

## Running commands

The project is intended to run in Docker. Prefer executing Artisan and Composer **inside the API container** (container name per team conventions: `bible_api`), for example:

```bash
docker compose exec bible_api php artisan test
docker compose exec bible_api php artisan migrate
```

Adjust if your compose service name differs.

---

## Directory map (mental model)

| Area | Role |
|------|------|
| `app/Actions/` | Thin **use-case** classes with `execute(...)`. Controllers delegate here for orchestration (e.g. chapter fetch, book list, comparison). |
| `app/Http/Controllers/` | HTTP layer: validate via Form Requests where needed, call Actions or Services, return API Resources or JSON. |
| `app/Http/Requests/` | Validation + typed input; prefer `validated()` and small helpers like `toDTO()` when converting to DTOs. |
| `app/Http/Resources/` | JSON shape for API responses. **Global**: `JsonResource::withoutWrapping()` is enabled in `AppServiceProvider` — responses are **not** wrapped in `data`. |
| `app/Http/Middleware/` | e.g. real client IP behind proxies, chapter route rate limiting. Aliases registered in `bootstrap/app.php`. |
| `app/Models/` | Eloquent models (`Version`, `Book`, `Chapter`, `Verse`, `VerseReference`, `User`, `Admin`, …). Prefer Eloquent over raw SQL. |
| `app/Enums/` | Backed enums for fixed sets (book abbreviation, version language, text source, auth providers, support types, …). Some enums participate in **route model binding** (e.g. book abbreviation in URLs). |
| `app/Services/` | Domain services grouped by bounded context (`Chapter/`, `Version/`, `Support/`, `User/`, `Admin/`). |
| `app/Services/*/Adapters/` | **Strategy** implementations (e.g. chapter source: database vs Api.Bible; version import: USFM vs JSON adapters). |
| `app/Services/*/DTOs/` | Plain data carriers between layers (import pipeline, chapter responses, support payloads). |
| `app/Services/*/Factories/` | Choose concrete adapter/strategy (`ChapterSourceAdapterFactory`, `VersionAdapterFactory`, …). |
| `app/Services/*/Interfaces/` | Contracts for swappable implementations (e.g. support backend). Bind implementations in `AppServiceProvider`. |
| `app/Services/*/Validators/` | Complex validation beyond Form Requests (e.g. full imported Bible structure). |
| `app/Exceptions/` | Base `CustomException` renders JSON `{ error, message }` with HTTP status; domain subclasses live in subfolders (`Chapter/`, `Version/`, `Support/`). |
| `app/Support/` | Cross-cutting helpers (e.g. chapter rate limit registration). |
| `app/Utils/` | Small reusable utilities (e.g. JSON decoding). |
| `app/Console/Commands/` | Artisan commands (e.g. admin creation). |
| `routes/api.php` | REST API routes under `/api` (versions, books, chapters, admin CRUD, user profile, support). |
| `routes/web.php` | Root JSON ping + **OAuth redirect/callback** routes for admin and user (`auth/admin`, `auth/user`). |
| `database/migrations|seeders|factories/` | Standard Laravel layout. |
| `tests/Feature/` | HTTP and integration-style tests (Pest + `Tests\TestCase`). |
| `tests/Unit/` | Isolated unit tests. |

---

## Architectural conventions

### Controllers stay thin

- Resolve dependencies via **constructor injection** when the controller always needs them (e.g. `SupportController` + `SupportServiceInterface`).
- Use **`app(SomeAction::class)`** or **`app(SomeService::class)`** where the project already does so for Actions (`ChapterController`, `BookController`).
- Return **`JsonResource` / `Resource::collection`** or explicit `response()->json()` / `response()->noContent()` following REST semantics.

### Actions

- One primary method: **`execute(...)`** with named arguments.
- Actions call **factories**, **adapters**, or **services**; they should not duplicate large domain logic that belongs in `Services/`.

### Services, DTOs, factories, adapters

- **Factory + Strategy** is the dominant pattern for:
  - **Chapter content**: `ChapterSourceAdapterFactory::make($version)` → `ChapterSourceAdapterInterface` (`DatabaseChapterAdapter`, `ApiBibleChapterAdapter`).
  - **Version import**: `VersionAdapterFactory` + `VersionAdapterInterface` implementations (USFM, JSON variants); pipeline uses DTOs → `VersionValidator` → `VersionImporter` / `VersionImportService`.
- New external formats or sources should extend this pattern: **interface + adapter + factory registration**, not ad hoc conditionals scattered in controllers.

### HTTP validation and DTOs

- Use **Form Requests** for HTTP input (`VersionRequest`, `SendSupportRequest`).
- For support-style flows, pairing **`toDTO()`** on the request with a **DTO** in `Services/.../DTOs/` keeps controllers minimal.

### API Resources

- Shape all stable JSON output through **`Http/Resources`**.
- Remember **no root `data` wrapper** globally.

### Exceptions

- Domain failures that should map to HTTP responses extend **`App\Exceptions\CustomException`** (or follow the same JSON contract) so clients get consistent `{ error, message }` payloads.

### Authentication

- **`auth:users`** — Sanctum API guard for end users (`User` model).
- **`auth:admins`** — Sanctum API guard for admins (`Admin` model).
- OAuth **redirect/callback** lives on **web** routes; token/session handling follows Laravel + Sanctum setup configured in `config/auth.php`.

### Middleware

- Aliases are registered in **`bootstrap/app.php`** (e.g. `chapter.rate_limit`).
- Trusted proxies and **`CloudflareRealIp`** are prepended for correct client IP behavior behind CDN/proxies.

### Application bootstrap notes

- **`AppServiceProvider`**: interface bindings (`SupportServiceInterface`), lazy loading prevention in non-production, `ChapterRateLimit::register()`, JSON resource unwrapping.

---

## REST and routing habits

- Public read endpoints for versions, books, and chapters live under **`routes/api.php`** with nested prefixes such as `versions/{version}/books/...`.
- **Admin** mutating routes use prefix `admin` + `auth:admins` (e.g. `apiResource` for versions excluding public `index`/`show` handled separately).
- Chapter routes may combine **custom middleware** with Laravel **`throttle`** (see existing chapter show route).

When adding endpoints, **mirror existing naming**, nesting, and middleware choices unless there is a strong reason to diverge.

---

## Code style and quality (non-negotiables)

- **PSR-12**; run **`./vendor/bin/pint`** (or project CI equivalent) before submitting.
- **All code comments in English** — inline (`//`), block (`/* */`), and PHPDoc/docblocks on PHP files, tests, config snippets in repo, etc. User-facing API messages may stay as product dictates.
- **SOLID**, descriptive names, prefer **dependency injection** and small classes over duplication.
- Prefer **Eloquent** and query builder over raw SQL unless there is a measured need.
- **Do not add flaky tests** — tests should be deterministic.

---

## Testing

- Framework: **Pest** (`pestphp/pest`, `pest-plugin-laravel`).
- Feature tests extend **`Tests\TestCase`** via `tests/Pest.php` configuration.
- Organize tests by domain: `tests/Feature/Chapter`, `Version`, `Support`, `Admin`, … and `tests/Unit` for parsers, adapters, validators, etc.
- Use factories and seed data patterns already present in `database/factories` and seeders.

---

## Adding a feature (checklist for agents)

1. **Route** — `routes/api.php` and/or `routes/web.php` only as appropriate (OAuth stays on web).
2. **Controller** — Thin; delegate to Action or Service.
3. **Validation** — Form Request if HTTP input is non-trivial.
4. **Domain logic** — `Services/` (+ DTOs, optional Interface + binding).
5. **New interchangeable behavior** — Adapter + Factory + Interface registration (chapter source, import format, external integrations).
6. **Response shape** — API Resource classes.
7. **Persistence** — Migration + Model + relationships following existing naming.
8. **Tests** — Feature and/or Unit Pest tests alongside comparable existing tests.

---

## Related docs

- Human-oriented overview, endpoints, and import pipeline: **`README.md`**.

When in doubt, **find the closest existing feature** (chapter read, version import, support ticket) and **copy its layering** rather than introducing a new architectural style.
