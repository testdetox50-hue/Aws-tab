# Aws-tab reviewer notes

## Architecture

Daylist is a small server-rendered PHP task manager with no framework, database, Composer dependencies, or JavaScript. `app.php` handles sessions, CSRF validation, task validation, JSON persistence, and POST actions; `public/index.php` renders the UI and `public/style.css` supplies presentation. Task data lives in `data/tasks.json`, outside the `public` document root, while `tools/chaos.py` deliberately injects malformed records for storage-validation testing.

## Conventions

- Keep the web entry points under `public/`; `public/index.php` requires `../app.php`, while `public/about.php` is a standalone static page and `public/health.php` is a JSON health endpoint.
- All mutations use POST actions named `add`, `edit`, `toggle`, or `delete`, followed by a `303` redirect in `app.php`. Unknown actions must be rejected rather than silently ignored.
- Every POST form includes the shared CSRF/session fields through `formFields()` in `app.php`; new forms should preserve this pattern.
- Escape all task-derived or session-derived HTML with `escape()` (`htmlspecialchars` using UTF-8 and `ENT_SUBSTITUTE`). `public/index.php` uses it for titles, IDs, labels, errors, and flash messages.
- Treat JSON storage as untrusted. `taskStore()` filters records to non-empty string IDs, non-blank titles of at most 200 bytes, and boolean `done` values before filtering, counting, rendering, or mutation.
- Read/modify/write operations must remain inside `taskStore()`’s exclusive `flock()` lock. The file is rewritten with JSON pretty-printing and truncated after writes.
- Use the existing task shape: `['id' => string, 'title' => string, 'done' => bool]`. IDs are generated with `bin2hex(random_bytes(12))`.
- Preserve the no-JavaScript interaction model: editing and deletion use native `<details>` elements, and filtering is done with `?filter=all|active|done` links.

## Intentional non-standard choices

- Local JSON-file persistence is intentional; this is a single-user/local learning application, not a multi-user database-backed service (`README.md`).
- The app has no accounts: all visitors to the same running instance share `data/tasks.json` (`README.md`).
- Validation limits titles by bytes via `strlen`, matching the explicit “200 bytes” error in `app.php`; do not replace it with an implicit character-count policy without updating behavior and documentation.
- `HelloWorld.java` is an unrelated minimal Java example in the project tree; the application itself is PHP.

## Watch out for

- Never expose `app.php` or `data/` through the web root; deployment must point the document root at `public/` (`README.md`).
- Do not bypass `taskStore()` for reads or writes, since that loses malformed-record filtering and the exclusive lock.
- Do not render task fields, IDs, errors, or flash messages without `escape()`.
- New state-changing actions need CSRF validation, strict action allowlisting, input validation, and redirect-after-success.
- Preserve atomic recovery behavior in `tools/chaos.py`: backup creation uses exclusive mode and replacement uses `os.replace`; do not make injection overwrite an existing backup or leave partial task files.