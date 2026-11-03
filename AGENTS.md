# Repository Guidelines

## Project Structure and Module Organization

This four-person coursework project has a buildless frontend and a PHP/PDO guestbook slice. `public/index.html` serves the static team demo; `public/index.php`, `api.php` and `admin.php` serve the database-backed homepage and workspace. `public/worldcup/index.html` serves the match-center demo. Shared UI lives in `public/assets/css/common.css` and `public/assets/js/common.mjs`; module assets live under `public/assets/`.

`app/team/` contains the guestbook/motto MVC implementation. `app/common/` provides PDO, sessions, account roles, CSRF and quotas. `app/worldcup/` contains a read-only MVC foundation and transactional CLI import service, served by `public/worldcup/index.php` and `api.php`; `public/worldcup/index.html` remains the separate browser-local demo. Admin editing, support writes and standings are not implemented. Preserve existing backend behavior when migrating toward the README's Yii2 target. Final coursework belongs in `data/team/` and `data/personal/`. `data/install.sql` defines 18 tables (four team/common and fourteen World Cup). Import contracts and instructions live in `data/worldcup/`. Never edit or commit unrelated material in `IGNORE/`.

## Development and Validation Commands

- `python3 scripts/serve-prototype.py`: serve the static demo at `http://127.0.0.1:5173` with a deterministic .mjs MIME type.
- `node scripts/check-prototype.mjs`: validate member records, message normalization, and required assets.
- `node --check public/assets/js/app.mjs`: check browser-module syntax.
- `node --check public/assets/js/worldcup.mjs`: check match-center browser syntax.
- `php scripts/install.php`: idempotently create missing tables, without altering existing schemas or clearing data.
- `php scripts/check-worldcup.php`: transactional database/API-controller checks; fixtures are always rolled back.
- `python scripts/worldcup_data.py`: rebuild the small World Cup staging sample offline with Python 3.10+ standard library; does not import data.
- `python -m unittest discover -s tests -p test_worldcup_data.py`: validate the bundled snapshots, identity mappings and corrupted-source rejection.
- `php scripts/import-worldcup.php data/worldcup/pilot.json [--dry-run]`: import or transactionally validate the pilot; identical successful content is skipped.
- `php scripts/check-worldcup-import.php`: verify import guards and rollback without retaining test writes.
- `python scripts/worldcup_full.py --details`: rebuild full-edition core and detail import packages from the committed source snapshots offline.
- `python -m unittest discover -s tests -p 'test_worldcup*.py'`: validate pilot and full-edition datasets (47 checks).
- `php scripts/import-worldcup.php data/worldcup/full/complete.json [--dry-run]`: import all 104 fixtures and selected core detail fields.
- `php scripts/check-worldcup-full.php`: verify the actual full snapshot in the local DB (28 checks, including idempotency).

The slice requires PHP 8.2+, PDO MySQL, mbstring and a MySQL-compatible database. It uses small PHP MVC classes without a third-party framework. See README for isolated XAMPP startup and `php scripts/check-guestbook.php` for database integration checks. No production deployment or Docker configuration has been verified. Do not document nonexistent commands as working features.

## Coding Style and Naming Conventions

Use two-space indentation for HTML, CSS, and JavaScript. Use kebab-case for asset filenames and CSS classes, camelCase for JavaScript identifiers, and named exports for reusable functions. Keep member data separate from DOM behavior. Render visitor text with `textContent`, never HTML interpolation. Respect reduced-motion preferences and keyboard navigation. Later PHP files must include the course-required comment header.

## Testing Guidelines

Run prototype checks after changes. Verify member selections, mobile layout, GitHub links, cross-module navigation, message submission/reload, match filters, details and support choice/reload. For backend changes, lint PHP and run database checks against a development DB: moderation, CSRF, permissions and persistence. Preserve the server/demo guestbook distinction; server failures must not fall back to local storage. Static demo and integration-test fixtures are synthetic; the DB import uses audited 2026 snapshots documented in `data/worldcup/README.md`, with missing values and scoped corrections preserved. Preserve demo labels. No coverage threshold has been adopted.

## Commit and Pull Request Guidelines

Existing commits use short imperative subjects, such as `Initialize coursework project skeleton`. Follow that pattern. Keep changes focused; describe behavior, checks performed, and limitations. Include screenshots for visual changes and link an issue when one exists. Never commit secrets or overwrite unrelated work. Do not push, deploy, or add implementation planning documents without a user request.
