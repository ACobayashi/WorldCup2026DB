# Repository Guidelines

## Project Structure and Module Organization

This repository contains a four-person university coursework project. The buildless team homepage supports a static demo and a working PHP/PDO guestbook slice. `public/index.html` serves the static demo; `public/index.php`, `api.php` and `admin.php` serve the database-backed homepage and workspace. Styles, browser modules, and generated character artwork live in `public/assets/css/`, `public/assets/js/`, and `public/assets/images/`.

`app/team/` contains the guestbook/motto controllers, models and management view. `app/common/` provides shared PDO, sessions, account roles, CSRF and quotas. Leave `app/worldcup/` unimplemented until requested; the current UI only provides a forthcoming-project entrance. Final coursework belongs in `data/team/` and `data/personal/`. `data/install.sql` currently defines this slice's four real tables, not the final >= 11-table schema. Do not edit or commit unrelated local reference material in `IGNORE/`.

## Development and Validation Commands

- `python3 scripts/serve-prototype.py`: serve the static demo at `http://127.0.0.1:5173` with a deterministic .mjs MIME type.
- `node scripts/check-prototype.mjs`: validate member records, message normalization, and required assets.
- `node --check public/assets/js/app.mjs`: check browser-module syntax.

The slice requires PHP 8.2+, PDO MySQL, mbstring and a MySQL-compatible database. It uses small PHP MVC classes without a third-party framework. See README for isolated XAMPP startup and `php scripts/check-guestbook.php` for database integration checks. No production deployment or Docker configuration has been verified. Do not document nonexistent commands as working features.

## Coding Style and Naming Conventions

Use two-space indentation for HTML, CSS, and JavaScript. Use kebab-case for asset filenames and CSS classes, camelCase for JavaScript identifiers, and named exports for reusable functions. Keep member data separate from DOM behavior. Render visitor text with `textContent`, never HTML interpolation. Respect reduced-motion preferences and keyboard navigation. Later PHP files must include the course-required comment header.

## Testing Guidelines

Run the prototype validation script after changes. For backend changes, lint PHP and run database checks against a development DB. Manually verify all four member selections, mobile layout, missing GitHub links, the World Cup placeholder, and message submission/reload. Check moderation visibility, CSRF, member/admin permissions and restart persistence. Browser-local messages must remain explicitly labeled as a demo, not server-delivered messages.

## Commit and Pull Request Guidelines

Existing commits use short imperative subjects, such as `Initialize coursework project skeleton`. Follow that pattern. Keep changes focused; describe behavior, checks performed, and limitations. Include screenshots for visual changes and link an issue when one exists. Never commit secrets or overwrite unrelated work. Do not push, deploy, or add implementation planning documents without a user request.
