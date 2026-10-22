# Frontend simplification plan (HTML, CSS, JS)

Repo: `xsn0wy1/project-root`, baseline commit `f0f1c7f`. Database, PHP domain logic and API contracts are frozen for this phase.

## Goal and rules

Make the frontend smaller and easier for a beginner or intermediate developer to read. Layout, appearance and behavior must stay the same.

- **No visual or behavioral change** in either theme, at desktop, 390px or 320px, in either language, or with menus and dialogs open.
- **Allowed changes:** class names used only internally, file layout, comments, `?v=` strings.
- **Not allowed:** a framework, a build step, bundlers or ES modules, Tailwind, renaming classes for style, or rounding near-equal colors (for example `0.375` vs `0.38`) without the owner's approval. List those as candidates instead.
- **Prefer plain code over clever code.** Use a helper only when its call sites become easier to read. Explicit HTML is easier for a beginner than a templating helper, so form fields stay written out.
- **One commit per work package.** Pause after each phase and report the metrics in section 6.

## 1. Baseline measurements (verify them first)

| Area | Measurement at `f0f1c7f` |
|---|---|
| CSS total | 696 KB across about 28 files |
| `css/theme.css` | 191 KB, 1,176 rules. Almost all are light-theme overrides scoped per page with `html[data-theme="light"][data-theme-styles~="<page>"]` |
| Colors | 2,991 hardcoded color values vs 1,556 `var(--…)` uses |
| Cross-sheet duplicates | 109 selector+declaration pairs repeated identically in 2+ page sheets (`[hidden]`, `.visually-hidden`, `button`/`input`/`select` resets, `.content-card`, `.save-button`, `.pagination button`, …) |
| `theme.css` internal duplicates | 127 rules identical after removing the page scope |
| JS | 14,311 non-blank lines in 35 files, already formatted (1.3% over 140 chars, 98 lines over 250) |
| JS constructs | 216 `get x()` getters (mostly lazy i18n labels), 81 `innerHTML`/`setHTML`, 9 nested ternaries |
| Look-alike JS helpers | `setBusy` ×10, `report` ×9, `icon` ×12, `toast` ×6, `escape`/`escapeHtml` ×12, `renderPagination` ×3, `upsert`/`fromApi` ×4. Bodies differ, so they are **not** copy-pastes |
| Views | 172 `<i18n-text style="display:contents">` wrappers; 5 near-identical archive/delete dialogs; 27 distinct cache-buster strings |

## 2. Phase 0: Gates (before any change)

- **WP-0.1 Computed-style snapshot.** For every page × theme × viewport, plus the existing dialog, menu and dropdown states from `tests/refactor_baseline.cjs`, record `getComputedStyle` for every element (all properties, ignoring transitions and animations) and hash it per element. A CSS refactor passes only with **zero differences**. Pixel comparison stays as a second gate, with its existing exclusions (map tiles, QR codes).
- **WP-0.2 JS equality gate.** Formatting or rename-only passes must keep a normalized AST equal (`tests/js_ast.cjs`). Behavior passes must pass the browser suites: `i18n_browser`, `theme_browser`, `dropdowns_browser`, `document_links_browser`, the ambulances, transfers and surveys journeys, and `password_recovery_browser`.
- **WP-0.3 Metrics script.** Measure everything in section 6 so each phase can report before and after.

## 3. Phase 1: Quick, low-risk wins

1. **`i18n-text`.** Add `i18n-text { display: contents; }` to a stylesheet that every page loads, including the auth and survey-user pages. Remove the 172 inline `style="display:contents"` attributes and the one emitted by `i18n.js` (`mark()`). Gate: computed styles.
2. **Shared base sheet.** Create `css/base.css`, loaded on every page **before** page sheets. Move the 109 identical rules there and delete them from the page sheets **only if** the computed-style gate stays at zero (the cascade order changes).
3. **Cache-busters.** Add one `asset_url($path)` helper in `shell.php` with a single version constant, replacing the 27 hand-typed `?v=` strings. Report this as an intentional URL change.
4. **Archive dialog component.** Add `archive_dialog($prefix, $keys)` in `backend/php/components/` for the 5 management delete dialogs. Do it only if the rendered HTML is byte-identical. Otherwise leave them alone.

## 4. Phase 2: Light theme with variables (largest saving, highest risk)

Today every page re-declares its light colors in `theme.css`. The goal is for each page to use color **tokens** and for the light theme to change only the token values.

1. **Inventory.** For each page sheet and its `theme.css` section, list each (dark value → light value) pair per property. Group pairs that repeat across pages.
2. **Tokens.** Define a small set of semantic tokens in `:root` (dark) and `html[data-theme="light"]`: surface, raised surface, text, muted text, line, accent, button image, icon filter, danger and success. Add tokens only for pairs that recur.
3. **Pilot on `main-menu` and `settings`.** Replace hardcoded colors with tokens where the dark value matches exactly, then delete the matching light overrides. Keep any override whose value is unique. Gate: computed styles at zero diff in **both** themes. Report the bytes saved before continuing.
4. **Roll out one page per commit:** management pages, documents, traces, surveys, survey-user.
5. **Owner decisions.** Produce a list of near-equal colors that could become one token if the owner approves. Do not merge them unasked.
6. **Out of scope:** blur, motion and layout properties.

## 5. Phases 3–4: CSS cleanup, then JS

**CSS (Phase 3)**
- Use Chromium CSS coverage across all pages, states and journeys to find unused selectors. Delete a selector only if all three are true: coverage reports it unused, it is not in any view, and its class name is not in any JS string or template.
- Deduplicate within `responsive.css` and the page sheets after Phase 2. Keep order, specificity and media conditions. Keep any rule you are unsure about.

**JS (Phase 4)**
1. **Shared helpers.** Merge a helper into `ui.js` only after diffing every copy and parameterizing the differences. Candidates: `escapeHtml` (one safe version; check that the largest copy has no extra behavior), `icon`, `toast`, `setBusy(controls)`, `renderPagination`. Add a small Node test that runs the old and new versions on the same inputs. Leave a helper alone if one parameter can't explain the difference.
2. **i18n getters.** Replace the `get label() { return t('key') }` getters with plain data (`labelKey: 'key'`), translated where the label is rendered. Language switching at runtime must keep working; `i18n_browser` is the gate.
3. **Nested ternaries.** Replace the 9 nested ternaries with `if/else` or named variables.
4. **Long functions.** Split functions over about 60 lines at natural seams (load → render → events). List them with `tests/readability_checks.cjs`. Do not split just to hit a number.
5. **File headers.** Add a 3–5 line comment at the top of each page script: what the page does, which API actions it calls, which DOM IDs it owns.
6. **Keep:** `innerHTML` templates with escaping, `async`/`await` and arrow functions. They are familiar to intermediate readers. Do not convert templates to `createElement` chains, because that adds code.

## 6. Acceptance per work package

- Computed styles: zero differences. Visual comparison: no worse than the current 0.041% maximum.
- i18n coverage and all browser suites pass. No new console errors. No missing assets.
- **Report:** CSS bytes and rule count, `theme.css` bytes, hardcoded colors vs `var()`, cross-sheet duplicate rules, JS lines, number of helper definitions, getters, nested ternaries, functions over 60 lines, and inline styles. Report formatting-driven line growth separately.
- List every intentional change (cache-buster URLs, added CSS files) and every color-merge candidate for the owner.

## 7. Order and stopping points

Run Phase 0, then 1, 2 (pilot), 2 (rollout), 3 and 4, pausing after each. If the Phase 2 pilot saves little or cannot reach zero computed-style differences, stop and report before rolling it out.
