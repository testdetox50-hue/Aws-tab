# Aws-tab reviewer notes

## Architecture

Daylist is a small server-rendered PHP task manager with no framework, JavaScript, database, or Composer dependencies. `public/index.php` renders the UI and includes `app.php`, which handles sessions, CSRF validation, task actions, JSON-file persistence, filtering, and error handling. Persistent data lives in `data/tasks.json`; `tools/chaos.py` is an explicit malformed-data test utility.

## Conventions

- Keep the web root limited to `public/`; `app.php` and `data/` are intended to remain outside it (`README.md`).
- Use strict PHP typing and explicit error handling (`app.php` begins with `declare(strict_types=1)` and wraps request/storage handling in `try/catch`).
- All task persistence goes through `taskStore()` in `app.php`. It creates `data/`, opens `tasks.json` with `c+`, takes an exclusive `flock`, validates records, and rewrites under the same lock.
- Treat JSON storage as untrusted. Valid tasks must have a non-empty string `id`, a non-blank string `title` of at most 200 bytes, and a boolean `done` (`app.php`).
- Mutations use POST actions named `add`, `edit`, `toggle`, and `delete`; successful mutations use a 303 redirect to prevent resubmission (`app.php`).
- Every POST form must include the session CSRF token through `formFields()`; user-controlled output is escaped with the shared `escape()` helper before HTML rendering (`app.php`, `public/index.php`).
- Task IDs are generated with `bin2hex(random_bytes(...))`, and task ordering puts new tasks first with `array_unshift()` (`app.php`).
- Filtering is query-string based and deliberately limited to `all`, `active`, and `done`; invalid values fall back to `all` (`app.php`).
- UI behavior is implemented with semantic HTML forms and `<details>` controls rather than JavaScript (`public/index.php`). CSS belongs in `public/style.css`.
- Health responses are JSON, UTF-8, and non-cacheable (`public/health.php`).

## Intentional non-standard choices

- JSON file storage and a single shared task list are intentional local-learning-project constraints, not missing database/account architecture (`README.md`).
- The application intentionally has no client-side JavaScript; add/edit/toggle/delete are separate ordinary POST forms.
- `tools/chaos.py` directly injects malformed JSON records to verify that `taskStore()` filters invalid persisted data. PHP must continue rendering valid tasks rather than trusting the file.
- `HelloWorld.java` is unrelated to the PHP application and should not drive architectural changes.

## Watch out for

- Never render task titles, IDs, messages, or errors without `escape()`; titles are explicitly tested with HTML payloads (`README.md`, `public/index.php`).
- Do not bypass `taskStore()` or write `tasks.json` without locking, truncating, and flushing; partial writes or stale concurrent updates violate the storage model.
- Preserve CSRF checks and strict action allowlisting for every state-changing POST.
- Keep validation consistent between request input and persisted-record filtering, including the 200-byte limit and boolean `done` requirement.
- Do not expose `data/tasks.json` through the web server; deployment must keep it outside `public/` as documented.