# Frontend Phase 2 — Light theme with color variables (revised)

Repo: `xsn0wy1/project-root`. Start from the latest commit; the error-page work must be committed first.
Phases 0–1 are done, plus later feature work: server-rendered theme, language and preferences, error pages, viewer dialogs, ambulance quick status, and transfer validation.

## Goal

`css/theme.css` (192 KB) re-declares light-mode colors per page with `html[data-theme="light"][data-theme-styles~="<page>"] …`. There are about 1,300 such selectors.

Replace this with **color variables**:
- Page sheets use `var(--…)` for colors.
- The light theme only changes the variable values.
- Delete each light override once its page no longer needs it.

The result must look identical in both themes.

## Rules

- **No visual change.** Same colors, same layout, both themes.
- **No framework or build step.** Plain CSS custom properties only.
- **Variables live in `base.css`.** It is already loaded first on every page (staff, auth, survey-user and the error page):
  - dark values in `:root`
  - light values in `html[data-theme="light"]`
- **Few, readable names.** Start with these 10 and add a new one only if it recurs on at least 3 pages:
  `--surface`, `--surface-raised`, `--text`, `--text-muted`, `--line`, `--accent`, `--accent-image` (the `special-bg.png` button background), `--icon-filter`, `--danger`, `--success`.
- **Never merge near-equal colors on your own** (e.g. `0.375` vs `0.38` opacity). List them for the owner instead.
- **Leave alone:** blur, shadows-only effects, animations, layout properties, and any override that is unique to one element. Unique overrides stay in `theme.css`.

## Steps

**Step 1 — Inventory (no code changes).** For `main-menu` and `settings`, list every light override as: selector → property → dark value → light value. Group identical value pairs; a pair that shows up on both pages becomes a variable. Send me the table and the proposed variable list before editing.

**Step 2 — Pilot: `main-menu` + `settings`.**
1. Add the variables to `base.css`.
2. In `main-menu.css` and `settings.css`, replace a hardcoded color with a variable only where its dark value matches exactly.
3. Delete the `theme.css` overrides that are now covered.

Report the `theme.css` bytes saved. **Stop condition:** if the pilot removes less than about 25% of those two pages' overrides, or can't reach zero visual difference, stop and report.

**Step 3 — Rollout, one group per commit,** largest first:
1. Management pages: patients and ambulances (near-identical sheets), then doctors, routes, personnel, destinations and accounts.
2. Transfers: trace, trace-map, trace-view, trace-new.
3. Documents: documents, upload, viewer.
4. Surveys: survey, survey-new, survey-view, survey-user.
5. Auth pages and the error page.

**Step 4 — Owner list.** List near-equal colors that could share a variable if I approve.

## Verification (lighter than before)

Per commit, check only what the commit touches:
- **Computed styles** for the touched pages: both themes, desktop 1440 and phone 390, resting state plus their open dialogs/menus. Custom properties (`--*`) may differ; every other property must be identical.
- **Pixels** for the same set: zero difference.
- **Page script syntax** still parses, and the console is free of errors on touched pages.

Once at the end of Phase 2 (not per commit):
- the full 636-state style/pixel matrix
- the existing browser suites: theme, i18n, dropdowns
- the metrics script

Skip per-commit SQL/API suites, installation checks and translation coverage: Phase 2 changes only CSS.

## Report format (keep it short)

Per commit, at most about 10 lines:
- what changed
- `theme.css` bytes before → after
- total CSS bytes
- hardcoded colors vs `var()` count
- verification result (pass/fail plus any differences)
- anything needing my decision

No screenshot galleries unless something differs.

## Owner approval — 2026-10-24

- The renamed variable proposal is approved; remove superseded inventory rows R036, R092, R103 and R104 in the pilot.
- Use `--tint` (`255, 255, 255` → `89, 110, 167`), `--panel` (`16, 23, 50` → `245, 248, 255`) and `--shade` (`8, 13, 32` → `245, 248, 255`) as `rgba(var(--tint), 0.06)` with each original alpha unchanged. These replace the proposed per-opacity `--fill-*` variables. Add another channel only when it covers at least three pages.
- Keep `--light-*` references during rollout. At Phase 2 completion, fold them into the new variables to leave one palette. Retain overrides still required for exact cascade/reset behavior.
