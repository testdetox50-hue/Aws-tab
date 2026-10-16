# Aws-tab reviewer notes

## Architecture

Daylist is a small, server-rendered PHP 8+ task manager with no framework, JavaScript, database, or Composer dependencies (`php-task-manager/README.md`). `app.php` owns sessions, CSRF validation, request handling, task validation, and JSON-file persistence; `public/index.php` renders the HTML UI. Static/public entry points live under `public/`, while application code and `data/` remain outside the web root.

## Conventions

- Store all task mutations through `taskStore()` in `php-task-manager/app.php`; it creates `data/`, takes an exclusive `flock`, validates persisted records, and writes while the lock is held.
- Treat JSON storage as untrusted. Only records with non-empty string `id`, non-blank string `title` of at most 200 bytes, and boolean `done` survive the filter in `app.php`.
- Use strict typing and explicit validation: `declare(strict_types=1)`, strict `in_array(..., true)`, `is_string`, `is_bool`, and `JSON_THROW_ON_ERROR` are established patterns.
- All user-controlled text rendered in HTML must pass through `escape()` in `app.php`; examples include task titles, IDs, error messages, and flash messages in `public/index.php`.
- Mutating operations are POST-only and must include the session CSRF token generated in `app.php`; successful mutations use a 303 redirect to `./` (PRG pattern).
- Supported task actions are deliberately whitelisted as `add`, `edit`, `toggle`, and `delete`; unknown actions fail rather than being ignored (`app.php`).
- Task IDs are generated with `bin2hex(random_bytes(12))`; task additions are prepended with `array_unshift`, preserving newest-first display order (`app.php`).
- Filtering is query-string based and constrained to `all`, `active`, or `done`; invalid values fall back to `all` (`app.php`).
- Native HTML features are preferred over client-side behavior: `<details>` handles edit/delete disclosure, and forms submit directly to `./` (`public/index.php`).
- Public files use simple, direct entry points: `about.php` is static HTML, while `health.php` returns JSON with `no-store` caching and does not bootstrap the task application.

## Intentional non-standard choices

- Persistent storage is a local JSON file rather than a database, and all visitors to one running instance share the same task list (`php-task-manager/README.md`).
- Malformed records are silently discarded during reads so valid tasks continue rendering; `tools/chaos.py` intentionally injects scalars and incorrectly typed records to verify this behavior.
- The application is intentionally JavaScript-free and uses server-rendered forms, flash messages, and redirects for all interactions.

## Watch out for

- Do not move `data/` or `app.php` into the web root; the documented deployment boundary keeps them inaccessible (`php-task-manager/README.md`).
- Do not read or write `tasks.json` outside `taskStore()` or release the lock before serialization and truncation; overlapping writers can corrupt the file.
- Preserve validation before typed operations and templates; malformed persisted data must never reach `array_filter`, counts, mutations, or HTML.
- Preserve output escaping, CSRF checks, strict action whitelisting, and the 200-byte title limit when adding fields or actions.
- Changes to storage or chaos testing should account for the documented requirement to stop PHP before injecting/restoring files.