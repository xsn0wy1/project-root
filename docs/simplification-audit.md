# Simplification groundwork and PHP formatting pilot

Complete; paused for review. This is the agreed bounded checkpoint from the supplied roadmap, not completion of its ten phases. The baseline includes the latest independent-review fixes. No production behavior, API, schema, dictionary, template, JavaScript or CSS changed. Only two Ambulances PHP files were reformatted.

The detailed inventories, all similarity pairings and verification are in `simplification-audit.json`. The action-by-suite table is `simplification-api-coverage.md`. Source measurements are in `simplification-source-metrics.json`; screenshots, fixture credentials and the pre-change application snapshot remain ignored.

## Assumptions checked against the current source

| ID | Result | Evidence and implication |
| --- | --- | --- |
| A1 | Partly outdated | A core entry point already runs 16 suites and writes summary JSON. Recovery, document links, browser workflows and installation remain separate. Added optional sanitized --contracts capture; no second test framework. |
| A2 | Refuted as a uniform pattern | 14 action dispatchers plus the actionless translations endpoint, not 17 equivalent dispatchers. Patients list is POST; binary document reads accept HEAD; drafts/photo accept two methods; public survey/auth paths and catalogs have different guard ordering. Preamble inventory retains the differences. |
| A3 | Partial resemblance; no broad merge justified | Maximum whole-module normalized PHP similarity is 62.08% (Destinations/Personnel). Ambulances/Destinations is 48.87%. These values do not establish identical lock/error semantics. Personnel legacy profiles, patient encounters, route snapshots and dispatch/clinical checks differ. |
| A4 | Refuted as messages-only differences | Shared settings_text, transfer choices/notes and Request revision helpers already exist. Remaining text/integer/date/phone validators differ in null handling, required values, Unicode/control checks, bounds, return types and field-error maps. Patient revision validation is a narrow future candidate; no merge made. |
| A5 | Confirmed in four SQL insert sites | Three procedural audit writers and one direct facilities CLI insert. Known patient/settings/password wrapper calls are inventoried. Preserve actor/resource type, request ID and dynamic action spelling before any consolidation. |
| A6 | One intentional indirect translation | 294 ApiException throws: 293 translate directly; survey_input_fail receives an already-translated message from all 29 callers. No untranslated error was found by this inventory. |
| A7 | Already completed | The obsolete API page.php, rewrite and HTML-routing branch are gone. Retain backend/php/core/page.php, which is the current staff guard. |
| A8 | Confirmed with qualification | 27 cache-buster strings remain. No dedicated test assertion on those literal values was found. Any URL helper still needs normalized rendered-HTML/asset verification. |
| A9 | Only one strong pair | Destinations/Personnel JS reaches 62.52%; all other pairings are below 40%. This supports inspecting a few matching operations, not one shared implementation for all six pages. JS comparison is lexical, not an AST proof. |
| A10 | Confirmed | No project build manifest or formatter configuration was found; the checked JS parser/formatter packages are unavailable. PHP/JS/CSS ship directly. Audit tools use standard Python and the existing PHP tokenizer; no runtime dependency was added. |
| A11 | Mapped, not proven exhaustive | 90/96 top-level routes have runtime observations, with source references for six dedicated-helper routes. Added 10 focused checks for specialties, doctor catalogs and translations. Per-role, negative, deep-schema and SQL row snapshots remain incomplete; the matrix states its limits. |
| A12 | Confirmed | staff_sidebar and staff_header each contain 24 conditional-question tokens, including nested expressions. Their HTML-generating logic was not changed in this pilot. |

## Duplication and exception findings

Similarity uses a whitespace-independent PHP token comparison and a lexical JS comparison. Domain words are normalized; other string literals remain intact. Percentages are a matching-token screening measure, not percentages of safely removable code, and do not prove equivalent behavior. All 15 pairs per language are retained in JSON.

| Pair | PHP normalized | JS normalized |
| --- | ---: | ---: |
| destinations / personnel | 62.08% | 62.52% |
| ambulances / destinations | 48.87% | 32.96% |
| ambulances / doctors | 42.17% | 39.73% |
| doctors / patients | 21.1% | 25.04% |

The only throw without a direct I18n::t call is `backend/php/modules/surveys.php:11`, in `survey_input_fail($message)`. All 29 call sites translate before invoking it. This is an intentional helper, not evidence of an English-only message. All 294 throw sites and remaining validators are inventoried with source locations.

## Audit writes and action names

| SQL insert site | Existing resource handling |
| --- | --- |
| `backend/php/modules/auth.php:229` (`users_audit`) | User resource type; account, session, Settings and survey callers retain their current behavior. |
| `backend/php/modules/documents.php:52` (`documents_audit`) | Document resource type, actor and request ID. |
| `backend/php/modules/transfer-access.php:42` (`transfer_audit`) | Caller-supplied resource type; transfers, catalogs, patients and simulation. |
| `backend/php/cli/catalogs.php:28` | Direct facility CLI insert, with its existing CLI request-ID format. |

The 49 direct/known-wrapper calls are listed in JSON, including patient, Settings and password-change wrappers. Fixed action literals:

`account.approved`, `account.password_changed`, `account.password_reset`, `account.password_reset_requested`, `account.registered`, `ambulance.archived`, `ambulance.created`, `ambulance.restored`, `ambulance.updated`, `destination.archived`, `destination.created`, `destination.restored`, `destination.updated`, `doctor.archived`, `doctor.created`, `doctor.restored`, `doctor.updated`, `document.archived`, `document.created`, `document.downloaded`, `document.file_replaced`, `document.patient_link_created`, `document.patient_link_revoked`, `document.previewed`, `document.restored`, `document.updated`, `document.viewed`, `patient.archived`, `patient.created`, `patient.listed`, `patient.restored`, `patient.updated`, `patient.viewed`, `personnel.archived`, `personnel.created`, `personnel.restored`, `personnel.updated`, `route.archived`, `route.created`, `route.restored`, `route.updated`, `session.login`, `session.logout`, `session.revoked`, `settings.photo_deleted`, `settings.photo_updated`, `settings.preferences_updated`, `settings.profile_updated`, `survey.link_created`, `survey.link_revoked`, `survey.responded`, `survey.saved`, `transfer.catalog_import`, `transfer.created`, `transfer.draft_saved`, `transfer.simulation_arrived`, `transfer.updated`.

Dynamic action families are retained exactly: `account.{approve,roles,disable,reactivate,revoke_sessions}`, `survey.{publish,close,reopen,archive,restore}`, `simulation.{start,pause,resume,stop,reset}` and `transfer.{in_progress,arrived,completed,cancelled}`. The direct CLI writes `facility-add`, `facility-rename`, `facility-archive` or `facility-restore`. Account approval currently writes both its approval and management actions; this audit does not rename or collapse them.

## Pilot and measurements

Expanded the Ambulances dispatcher/module into shorter expressions and one statement per line, preserving all PHP tokens, including strings/operators and inline HTML elsewhere in the tree. The two files grow from 165 to 260 lines; lines over 120 characters fall from 14 to zero. No helper, branch, function or file was added to the application.

| Measure | Before | After |
| --- | ---: | ---: |
| Significant PHP tokens | 69,699 | 69,699 |
| Semicolon tokens | 3,873 | 3,873 |
| Decision keyword/operator tokens | 2,104 | 2,104 |
| Function declarations including closures/arrows | 364 | 364 |
| Physical lines in PHP files including embedded HTML | 8,190 | 8,285 |
| PHP-file lines over 120 characters | 1,652 | 1,638 |
| Maximum physical function span | 68 | 68 |
| Repeated 16-token groups | 2,534 | 2,534 |

Semicolons include for-loop headers; decision tokens and ternary candidates are lexical indicators, not AST statement counts or cyclomatic complexity. Token-window duplication excludes formatting; the older overlapping eight-line indicator is retained separately for continuity. Raw lines/bytes are reported without treating formatting growth as a regression.

First-party files stay at **266**; code/dictionaries grow **2,196,283 → 2,197,347 bytes** (+1,064). Both duplication indicators remain unchanged, so no reuse reduction is claimed. Cumulative versus the original: **92 fewer files and 65,696 fewer first-party bytes**.

Application counts cover only public/backend/db, excluding vendor, local.php, .DS_Store and .gitkeep. They include application CLI/router/config examples, SQL migrations, dictionaries and first-party artwork. Tests, process documentation, runtime snapshots, storage, logs and Git are outside the count. The 96 artwork/non-code files contribute total bytes but not code bytes; locally vendored fonts remain excluded.

## Verification and limits

- All 16 core suites pass before and after the pilot: **660 checks in each run**. Ten new catalog/translation checks pass in the existing Settings/Doctors/i18n suites (99 checks in that focused replay); current unique core counts total **670**, without counting repeated runs as additional coverage.
- All six Ambulances action outcome signatures match. A concurrent heartbeat/logout signature varies within the test's allowed 200/401/403 outcomes; aggregate status/error-code sets match. Exact snapshots must account for that race.
- Browser verification passes **114 checks**, four affected-page theme/viewport views and four interaction groups. Ambulances is reviewed in dark Spanish/light English at desktop/phone sizes; representative management/document dialogs and narrow-phone actions are exercised. Accepted mobile-dark and desktop-light screenshots were inspected. No unhandled script errors, missing assets or Google Fonts requests occurred.
- Syntax passes for **90 PHP, 35 application JS, 12 browser CJS and 27 Python files**. All **1,852 bilingual keys**, 25 page bindings/assets and 1,119 literal key references pass. The key scanner now accepts multiline calls; the unchanged 25-template render baseline was reused.
- Application manifest comparison finds only the two intended formatting changes; the other **264 first-party files**, including all templates, styles, scripts, dictionaries, schema/migrations and artwork, retain their bytes. Preflight is healthy; temporary SQL schemas/accounts and verification ports are absent. Demonstration port 8091 remains available.

This is not a complete golden-master framework. The matrix records actual sampled outcomes and explicit blind spots, not every role/invalid-input/deep-schema/header/message combination or SQL audit/notification row snapshot. The fresh-install, standalone recovery/document-link and full 25-page visual suites were not rerun for this token-identical PHP pilot. Real provider delivery and physical-device/Apache deployment checks remain separate.

## Continuation

Review this pilot first. The next bounded pass should format the dense Destinations and Personnel PHP modules/dispatchers with the same token gate and focused checks, retaining domain logic and messages. Before any broader semantic consolidation, strengthen the role/rejection and transaction-order cases identified by the matrix. Do not treat whole-module similarity as approval for a CRUD framework. JavaScript formatting/consolidation, CSS pruning, asset/key deletion, advanced-code moves and submission packaging remain deferred.

Reproduce core observations with `python3 tests/verify_project.py --contracts --output tests/.runtime/<new-run>`. Recompute inventories using `python3 tests/source_audit.py --baseline tests/.runtime/simplification/checkpoint1/baseline --observations tests/.runtime/<new-run> --output tests/.runtime/<new-report>.json`. The audit uses standard Python and the existing PHP tokenizer; it never loads application configuration or executes source during inventory.
