# Aws-tab reviewer notes

## Architecture
This is a small, single-user PHP task manager under `php-task-manager/`, with no framework, database, Composer dependencies, or JavaScript. `app.php` handles sessions, CSRF, validation, task actions, and JSON-file persistence; `public/index.php` renders the server-side HTML and `public/style.css` provides presentation. `tools/chaos.py` is an optional local test utility for injecting and restoring malformed storage records.

## Conventions
- Keep the web document root at `php-task-manager/public`; `app.php` and `data/` must remain outside it (`README.md`).
- Persist tasks only through `taskStore()` in `php-task-manager/app.php`. It creates `data/` with mode `0700`, opens `tasks.json` with `c+`, and holds an exclusive lock across read, validation, mutation, and write.
- Treat JSON storage as untrusted input. `taskStore()` retains only records with a non-empty string `id`, non-blank string `title` of at most 200 bytes, and boolean `done`.
- Use strict typing and strict comparisons: `declare(strict_types=1)`, `in_array(..., true)`, `hash_equals()`, and explicit `is_string`/`is_bool` checks are established patterns in `app.php`.
- All user- or storage-derived text rendered in HTML goes through `escape()`, using `htmlspecialchars` with `ENT_QUOTES | ENT_SUBSTITUTE` and UTF-8 (`app.php`, `public/index.php`).
- Mutations are POST-only, require the session CSRF token, validate a fixed action allowlist (`add`, `edit`, `toggle`, `delete`), then redirect with HTTP 303. Success text is carried through the session flash value (`app.php`).
- Task IDs are generated with `bin2hex(random_bytes(...))`; task additions are prepended with `array_unshift()` (`app.php`).
- The UI is server-rendered with accessible labels, status/alert roles, and `<details>` for edit/delete controls (`public/index.php`). Filters are the allowlisted `all`, `active`, and `done` query values.
- The chaos tool must be run with PHP stopped and restored afterward; it uses an exclusive backup creation (`"xb"`) and atomic `os.replace()` for task-file replacement (`tools/chaos.py`, `README.md`).

## Intentional non-standard choices
- A local JSON file is intentional instead of a database; all visitors to one running instance share the task list (`README.md`).
- The entire task file is locked with `LOCK_EX` even for reads, intentionally preventing overlapping reads/writes from racing (`app.php`).
- Invalid persisted records are silently filtered out rather than aborting the page render; this supports the malformed-record resilience test in `tools/chaos.py`.
- No JavaScript, framework, or package manager is used; interactions are ordinary forms, POST requests, redirects, and HTML `<details>` elements (`README.md`, `public/index.php`).

## Watch out for
- Never add a storage read or write that bypasses `taskStore()` or its lock; direct `tasks.json` access can lose concurrent updates.
- Do not render task fields, IDs, errors, or messages without `escape()`, including newly added attributes.
- Preserve CSRF validation for every state-changing POST and retain the POST/303 redirect flow to avoid duplicate submissions.
- Do not weaken persisted-record validation: filters and templates assume typed, complete task arrays.
- Avoid accepting arbitrary actions or filters; both are deliberately allowlisted.
- When changing task-file writes, preserve truncation and flushing after rewinding; otherwise stale trailing JSON can remain in the file (`app.php`).