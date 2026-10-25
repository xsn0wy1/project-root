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

## Batch 4 — account and operational management

- Files: accounts.js, personnel.js, destinations.js, routes.js and the previously empty management.js (header only).
- Added page/API/DOM headers; private translated getters are lazy functions; expanded guards, declarations, action branches and normalization stages; named account/person/destination records and comparators. Removed an identity-only route code wrapper.
- Long templates preserve exact raw/cooked text and expression order. Kept native collection callbacks, short attribute choices, unused `_` placeholders and schematic x coordinates where clearer.
- PASS: 742 UI checks / 20 desktop-phone/theme cases / 45 interaction groups, live bilingual switching suite, 98 existing boundary checks; source equivalence: 91 PHP / 35 JS / five exact files. No script errors or asset failures.

## Batch 5 — document pages and requests

- Files: document-api.js, document-form.js, documents.js and documents-viewer.js; added purpose/API/DOM headers.
- Expanded guards, choices and declarations; named file-extension stages, query arguments and rotated image dimensions. QR/canvas assignments are explicit and retain the original setter order.
- Kept native FormData serialization, two-field entry destructuring, short URL/attribute choices and QR x/y coordinates where clearer; request keys and binary handling are unchanged. Template splitting proves identical text/expression order.
- PASS: 184 UI checks / 16 desktop-phone/theme cases; 182 document browser checks across eight root/mount/language/theme/viewport cases; 46 document-link SQL/API checks; 98 boundary checks. Source equivalence: 91 PHP / 35 JS / four exact files.

## Batch 6 — transfers and maps

- Files: trace-api.js, trace.js, trace-view.js, trace-map.js and trace-map-shared.js; added purpose/API/DOM headers.
- Expanded guards, choices, declarations and callback defaults; named date parts, facilities, controls, request revisions, stops, points and haversine terms. Converted translation maps and every consumer to lazy function calls; private STATUS entries are ordinary data with label functions.
- The STATUS objects have no identity comparisons, writes or exported consumers. Kept the public live name/snapshot getters and MediaQuery-style width getter because their property contracts are clearer and safer; kept x/y coordinates and short attribute choices.
- PASS: 93 root/mounted transfer/survey workflow checks, transfer/map UI journeys, 71 runtime bilingual checks, 98 boundary checks; source equivalence: 91 PHP / 35 JS / five exact files. Template text/evaluation order, API keys, stale-request guards and simulation behavior are preserved.

## Batch 7 — survey pages and participant flow

- Files: survey-api.js, survey.js, survey-new.js, survey-view.js, survey-templates.js and survey-user.js. Added purpose/API/DOM headers.
- Expanded guards/declarations, named question/invitation/rating/comparator values and pipeline stages; made coupled state writes explicit with the original setter order. Private type/category data uses lazy label functions; the template function still returns fresh translated objects, with every caller updated.
- Kept the single translated section-symbol getter to maintain a uniform string symbol field without dispatch logic. Other live/read-only interfaces, short attribute choices, optional-call failure boundaries and exact template text/order remain.
- PASS: 93 workflow, 103 survey UI / 16 desktop-phone/theme cases, 71 bilingual and 98 boundary checks; source equivalence: 91 PHP / 35 JS / six exact files. The formatting guard rejected a removed optional-call boundary; the private formatter now restores required parentheses and checks exact AST equality before writing.

## Batch 8 — shared API infrastructure

- Files: api-client.js, auth-session.js, i18n.js, theme.js and universal-search.js; added purpose/API/DOM headers.
- Expanded guards/choices/declarations, named DOM elements and focus/search stages, and replaced 57 private search getters with functions. The three existing eager search translations remain cached in their original order.
- Kept public live session/locale/theme getters, Promise retry chains, null-safe access and concise predicates/value fallbacks; CSRF, diagnostics, locale readiness and timeout behavior are unchanged.
- PASS: 71 i18n checks (including CPU ×6 first paint), 61 theme/session, 136 dropdown, 93 workflow and 98 boundary checks; all-page UI reached 1,584 passing checks before a stale-CSRF test setup failure. Its strict 401 password assertion passes in the focused 62-check rerun after joining page startup/shared CSRF. No application fix or weakened status assertion.
- Source equivalence: 91 PHP / 35 JS / five exact files. The full UI suite is repeated in the final regression; PHP application work has not started at this checkpoint.

## Batch 9 — PHP core, shell, storage and CLI

- Files: 27 changed PHP files across configuration/bootstrap/router/I18n, HTTP/security/storage, core/components, CLI and local race/recovery fixtures (32 reviewed; five already unchanged).
- Added explicit control-structure braces, conventional indentation, one statement per line and argument wrapping; renamed caught exceptions, prepared statements and fixture database configuration. Autoload remains byte-identical.
- Development proofs permit only identifier changes/additional braces, then require equal formatting token streams and every SQL/string/interpolated-text/inline-HTML byte. Native null coalescing, straightforward nullable values and long SQL literals remain where clearer or frozen.
- PASS: auth/settings/migrations/i18n/theme SQL/API suites — 172 checks; full all-page desktop/phone UI suite including strict password rejection; source equivalence: 91 PHP / 35 JS / 27 exact files.
- This full UI pass also verifies batch 8 after its test-startup correction; no application behavior fix was required.

## Batch 10 — authentication, Settings and mail

- Files: backend/php/modules/auth.php, settings.php and mail.php.
- Added explicit control-structure bodies and conventional statement layout; named caught exceptions and prepared statements. SQL, strings, transaction/lock/refund order, mail-worker behavior and response fields retain the original token/data order.
- Kept short nullable field choices, environment fallbacks and native password/hash/serialization operations where clearer or safer. No helpers or algorithms were added.
- PASS: 161 isolated auth/settings/release-fix SQL/API checks (including concurrent budgets/refunds), 61 theme/session, 71 bilingual/first-paint and 197 Settings/account UI checks / 16 cases; source equivalence: 91 PHP / 35 JS / three exact files.

## Batch 11 — PHP catalog modules

- Files: ambulances.php, patients.php, doctors.php, personnel.php, destinations.php and routes.php under backend/php/modules; existing Patients/Routes integration assertions updated.
- Added explicit control bodies and conventional layout; named fields, actors, records, statements and geometry indices. Replaced the nested estimated-age reference-date choice with lazy if/elseif/else; every SQL/string/HTML literal stays identical.
- Kept straightforward nullable serializer values, native callbacks and raw SQL where clearer or frozen. No validation, permission, transaction, version or archive rule changed.
- PASS: 408 SQL/API checks across six isolated modules, 43 fleet browser, 924 catalog UI / 24 desktop-phone/theme cases and 71 bilingual checks; source equivalence: 91 PHP / 35 JS / six exact files.
- Two stale tests expected the superseded role-denial redirect. They now require the accepted HTML 403 page with no Location header; the complete Patients/Routes reruns pass.

## Batch 12 — PHP documents, transfers, tracking and surveys

- Files: document-files.php, documents.php, transfer-access.php, transfer-input.php, transfers.php, tracking.php and surveys.php; existing audit permission assertion updated.
- Added explicit control bodies/layout and descriptive statement/record/question/link/geometry names. Replaced extract/compact catalog packing with the same ordered six-key array; each location resolver branch guarantees the four resolved keys.
- SQL/string/HTML literals are unchanged except four explicit reads of existing catalog keys. Kept native serialization, nullable values, geometry math and concise callbacks where clearer; binary/version, conflict, permission, simulation and survey rules are preserved.
- PASS: 233 isolated SQL/API checks, 93 transfer/survey workflow and 435 document/transfer/survey UI checks / 48 desktop-phone/theme cases; source equivalence: 91 PHP / 35 JS / seven exact files.
- The audit suite's stale account-page redirect assertion now requires the accepted HTML 403 page without Location; its full rerun passes. All rewrite batches are complete; full final regression remains due.
