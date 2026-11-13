# Repository Guidelines

## Project Structure and Module Organization

This four-person coursework project has a buildless frontend and a PHP/PDO guestbook slice. `public/index.html` serves the static team demo; `public/index.php`, `api.php` and `admin.php` serve the database-backed homepage and workspace. `public/worldcup/index.html` serves the read-only World Cup snapshot; `demo.html` retains the synthetic match-center demo. Shared UI lives in `public/assets/css/common.css` and `public/assets/js/common.mjs`; module assets live under `public/assets/`.

`app/team/` contains guestbook/motto, account, profile and coursework MVC implementations. `public/account.php` provides shared registration/login; `workspace.php` provides member/admin profile editing, coursework draft/publication controls and ECharts statistics; `coursework.php` lists published work, while `download.php` requires login. Public registration always creates a `user`, never a team member. `app/common/` provides PDO, sessions, account roles, CSRF and quotas. `app/worldcup/` contains read-only MVC pages (match details, national teams, calculated standings and knockout graph) and a transactional CLI import service, served by `public/worldcup/index.php` and `api.php`. Static Pages exports only bundled World Cup snapshots, never accounts, uploaded coursework, guestbook or support data. World Cup admin editing and support writes are not implemented. Preserve existing backend behavior when migrating toward the README's Yii2 target. Final coursework belongs in `data/team/` and `data/personal/`; runtime uploads there are ignored by Git. `data/install.sql` defines 20 tables (six team/common and fourteen World Cup). Import contracts and instructions live in `data/worldcup/`, page behavior in `docs/worldcup-pages.md`. Never edit or commit unrelated material in `IGNORE/`.

## Development and Validation Commands

- `python3 scripts/serve-prototype.py`: serve the static demo at `http://127.0.0.1:5173` with a deterministic .mjs MIME type.
- `node scripts/check-prototype.mjs`: validate member records, message normalization, and required assets.
- `node --check public/assets/js/app.mjs`: check browser-module syntax.
- `node --check public/assets/js/worldcup.mjs`: check match-center browser syntax.
- `node scripts/build-worldcup-snapshot.mjs`: build ignored static JSON from committed sources, without a database or network.
- `node scripts/check-worldcup-snapshot.mjs`: verify snapshot export, source-matched standings, squads, formations, filters and rankings.
- `php scripts/install.php`: idempotently create missing tables and apply known additive compatibility migrations without clearing data.
- `php scripts/migrate-team-workspace.php`: add profile/coursework tables and migrate shared account roles without clearing existing data.
- `php scripts/setup-local-unix.php /absolute/path/to/mysql.sock`: explicitly initialize the local project DB using an existing Unix-socket admin connection; refuses to overwrite local config or reset existing dedicated credentials. Generated credentials remain private and Git ignored.
- `php scripts/check-team-workspace.php`: transactional account/profile/file-model checks; fixtures roll back and temporary files are removed.
- `php scripts/check-team-profile-migration.php /absolute/path/to/mysql.sock`: use an authorized local Unix-socket admin connection to verify both old profile schemas, interrupted migration recovery and a fresh install in a random isolated database, then remove that database.
- `php scripts/check-team-http.php http://127.0.0.1:8088`: real local HTTP registration, multipart upload, publication and login-gated download checks. Uses temporary accounts/files and restores affected quota buckets; run only on an idle local development instance.
- `node scripts/check-team-navigation.mjs`: validate shared four-column navigation, three member coursework links, random/reduced-motion border signals, submission folders and PHP comment headers. File and external-link drafts share `coursework_files`; migration `005` adds source fields without adding tables. External download targets are not fetched or bundled by the server.
- `php scripts/check-worldcup.php`: transactional database/API-controller checks; fixtures are always rolled back.
- `python scripts/worldcup_data.py`: rebuild the small World Cup staging sample offline with Python 3.10+ standard library; does not import data.
- `python -m unittest discover -s tests -p test_worldcup_data.py`: validate the bundled snapshots, identity mappings and corrupted-source rejection.
- `php scripts/import-worldcup.php data/worldcup/pilot.json [--dry-run]`: import or transactionally validate the pilot; identical successful content is skipped.
- `php scripts/check-worldcup-import.php`: verify import guards and rollback without retaining test writes.
- `python scripts/worldcup_full.py --details`: rebuild full-edition core and detail import packages from the committed source snapshots offline.
- `python -m unittest discover -s tests -p 'test_worldcup*.py'`: validate pilot/full datasets and the related extensions (63 checks); photo-related tests require Pillow.
- `php scripts/import-worldcup.php data/worldcup/full/complete.json [--dry-run]`: import all 104 fixtures and selected core detail fields.
- `php scripts/check-worldcup-full.php`: verify the actual full snapshot in the local DB (28 checks, including idempotency).
- `php scripts/check-worldcup-pages.php`: verify real-snapshot MVC page rendering and all 48 full squads (327 checks).
- `php scripts/check-worldcup-standings.php`: verify WC26 ranking rules, independent standings reference and knockout links (491 checks).
- `python scripts/worldcup_player_metrics.py` then `php scripts/import-player-metrics.php`: rebuild and import the optional 17 player-match extension metrics after the complete core dataset; additive migration, no new tables.
- `php scripts/check-worldcup-players.php`: verify tournament/single-match player profiles, positional radar and player rankings. Details: `docs/worldcup-player-board.md`.
- `python scripts/worldcup_photos.py`: restore ignored local player photos from the committed identity catalog (Python + Pillow); `php scripts/check-worldcup-photos.php` verifies links and fallbacks.
- `php scripts/check-worldcup-formations.php`: verify all 104 source formations against DB starters and reject stale lineups; fixtures roll back. Details: `docs/worldcup-formations.md`.

The slice requires PHP 8.2+, PDO MySQL, mbstring, fileinfo, ZipArchive and a MySQL-compatible database. It uses small PHP MVC classes without a third-party framework. Coursework supports PDF/ZIP/DOCX/PPTX/MP4 up to 50 MiB, stored outside `public/`. PHP upload/post limits must also permit that size. See README for isolated XAMPP startup and `php scripts/check-guestbook.php` for database integration checks. No production deployment or Docker configuration has been verified. Do not document nonexistent commands as working features.

## Coding Style and Naming Conventions

Use two-space indentation for HTML, CSS, and JavaScript. Use kebab-case for asset filenames and CSS classes, camelCase for JavaScript identifiers, and named exports for reusable functions. Keep member data separate from DOM behavior. Render visitor text with `textContent`, never HTML interpolation. Respect reduced-motion preferences and keyboard navigation. Later PHP files must include the course-required comment header. Account/profile/coursework PHP authored by 林子媛 uses her name and student ID 2410936, without team name or date; preserve other authors' headers when adding maintenance notes.

## Testing Guidelines

Run prototype checks after changes. Verify member selections, mobile layout, GitHub links, cross-module navigation, message submission/reload, match filters, details and support choice/reload. For backend changes, lint PHP and run database checks against a development DB: moderation, CSRF, permissions and persistence. Preserve the server/demo guestbook distinction; server failures must not fall back to local storage. Static demo and integration-test fixtures are synthetic; the DB import uses audited 2026 snapshots documented in `data/worldcup/README.md`, with missing values and scoped corrections preserved. Preserve demo labels. No coverage threshold has been adopted.

## Commit and Pull Request Guidelines

Existing commits use short imperative subjects, such as `Initialize coursework project skeleton`. Follow that pattern. Keep changes focused; describe behavior, checks performed, and limitations. Include screenshots for visual changes and link an issue when one exists. Never commit secrets or overwrite unrelated work. Do not push, deploy, or add implementation planning documents without a user request.
