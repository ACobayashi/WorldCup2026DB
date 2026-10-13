# Repository Guidelines

## Project Structure and Module Organization

This repository contains a four-person university coursework project. The current implementation is a buildless team-homepage prototype, not a completed PHP application. `public/index.html` is the entry point. Styles, browser modules, and generated character artwork live in `public/assets/css/`, `public/assets/js/`, and `public/assets/images/`.

`app/team/` reserves controllers, models, and views for the later MVC implementation. `app/common/` is for shared functionality. Leave `app/worldcup/` unimplemented until requested; the current UI only provides a forthcoming-project entrance. Final coursework belongs in `data/team/` and `data/personal/`. Generate `data/install.sql` only when a real schema exists. Do not edit or commit unrelated local reference material in `IGNORE/`.

## Development and Validation Commands

- `python3 -m http.server 5173 --bind 127.0.0.1 --directory public`: serve the prototype at `http://127.0.0.1:5173`.
- `node scripts/check-prototype.mjs`: validate member records, message normalization, and required assets.
- `node --check public/assets/js/app.mjs`: check browser-module syntax.

No package installation, production build, PHP framework, database, or formatter is configured yet. Do not document nonexistent commands as working features.

## Coding Style and Naming Conventions

Use two-space indentation for HTML, CSS, and JavaScript. Use kebab-case for asset filenames and CSS classes, camelCase for JavaScript identifiers, and named exports for reusable functions. Keep member data separate from DOM behavior. Render visitor text with `textContent`, never HTML interpolation. Respect reduced-motion preferences and keyboard navigation. Later PHP files must include the course-required comment header.

## Testing Guidelines

Run the validation script after changes. Manually verify all four member selections, mobile layout, missing GitHub links, the World Cup placeholder, and message submission/reload. No coverage threshold or test framework has been adopted. Browser-local messages must remain explicitly labeled as a demo, not server-delivered messages.

## Commit and Pull Request Guidelines

Existing commits use short imperative subjects, such as `Initialize coursework project skeleton`. Follow that pattern. Keep changes focused; describe behavior, checks performed, and limitations. Include screenshots for visual changes and link an issue when one exists. Never commit secrets or overwrite unrelated work. Do not push, deploy, or add implementation planning documents without a user request.
