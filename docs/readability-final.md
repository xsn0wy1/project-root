# Final readability pass — 2026-10-24

Phase 2 is stopped at the verified Home/Settings pilot. Do not roll out the palette or fold `--light-*`. CSS declarations, HTML structure, hooks, SQL, API payloads and translation keys are frozen.

Rewrite related JS batches first (page scripts, then shared API infrastructure), then private PHP. Prefer explicit branches, descriptive bindings, named pipeline steps and ordinary functions to lazy translation getters. Keep concise constructs when clearer or safer; do not add application helper layers. Each page script gets a 3–5 line purpose/API/DOM header, including the meaning of existing `$`/`t` helpers when present.

Each batch records exact intentional source hashes, runs the existing source-equivalence gate and relevant browser suites, and gets its own commit. At the end run one full style/pixel matrix across every page, both languages/themes, desktop/phone and existing open states, all browser suites, translation coverage and SQL/API suites. Fix only concrete regressions.

## Batch 1 — authentication scripts

- Files: login, registration and recovery scripts; requested comments in base.css/theme.css only.
- Expanded password visibility/strength choices and recovery redirect/guard blocks; added four-line page headers.
- Kept native form serialization, optional element guards, Unicode/UTF-8 password checks and simple callbacks because they are clearer and preserve edge behavior.
- PASS: 130 recovery browser checks, 61 theme/login browser checks, 58 recovery and 15 theme SQL/API checks; source equivalence: 91 PHP / 35 JS / five exact intentional files. CSS declarations are byte-identical after removing their replaced leading comments.
- No behavior, CSS declarations, HTML templates, API actions or translation keys changed. Full visual/translation/SQL regression remains due after all batches.

## Batch 2 — shared page controls and Settings

- Files: main-menu.js, ui.js, dropdowns.js and settings.js.
- Added purpose/API/DOM headers; expanded guards and multi-declarations, named the text-normalization stages, and replaced cryptic option/choice/event names. Navigation option defaults now use explicit statements with the same undefined-only fallback.
- Retained short class/attribute ternaries, simple callbacks, two-field destructuring and optional element access where clearer. No new application helpers or UI markup changes.
- PASS: 161 Settings/navigation UI checks, 136 dropdown checks across 52 variants/two persistence journeys, 98 existing boundary/lazy-read checks; source equivalence: 91 PHP / 35 JS / four exact JS files.

## Batch 3 — clinical directories

- Files: ambulances.js, patients.js and doctors.js; added purpose/API/DOM headers.
- Replaced private lazy translation getters with functions and kept translation at lookup time; expanded guards/declarations, named records and catalog/pagination stages, and removed an identity-only doctor code wrapper.
- Split long templates into concatenated literals; a development-only AST comparison proves identical raw/cooked template text and expression order. Native collection operations and short attribute choices remain where clearer.
- PASS: 384 UI checks / 12 desktop-phone/theme cases / 19 interaction groups; 43 fleet browser checks; 99 fleet API checks in its own fresh database; 98 existing boundary checks. Source equivalence: 91 PHP / 35 JS / three exact files.
- An initial fleet run used the shared visual fixture, whose extra administrator roles violate that suite's assumptions. The existing isolated module runner supplied the correct fixture and passed; no application change was needed.
