# Hospital website — simplification workspace

This project preserves the hospital school website while reducing duplication and simplifying its code in reviewed stages. It uses plain HTML/CSS/JavaScript, PHP 8.0+ and MariaDB through XAMPP. There is no frontend build step.

The original `johnproject` remains the read-only behavior/design reference. `johnproject 2` supplies directory organization only; its files are never code references. Current progress and verification are in [docs/progress.md](docs/progress.md). Imported historical documents are under `docs/archive/`; they describe the original installation.

## Open the current demonstration

With XAMPP MariaDB running, from this project root:

```sh
python3 tests/baseline_environment.py start
```

Open <http://127.0.0.1:8091/>. The generated local login is in `tests/.runtime/stage1/access.json`; it is private and ignored by Git. All displayed records and uploaded fixture documents are fictional. The demonstration uses its own database, database account, private key and storage. Reference comparison servers use ports 8092 and 8093; no global Apache settings are changed. Stop them with `python3 tests/baseline_environment.py stop`.

The baseline uses saved fictional map geometry and disables outbound address lookup. It does not start the tracking worker automatically. Real Brevo email delivery requires separate private provider configuration and remains unverified.

## Install a fresh source checkout

Do not copy `local.php`, runtime snapshots or another installation's data. With XAMPP MariaDB running, the following creates the refactor project's separate database/account and generates a new private configuration/key:

```sh
HOSPITAL_DB_NAME=hospital_refactor HOSPITAL_DB_USER=hospital_refactor_app \
HOSPITAL_DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock \
HOSPITAL_APP_URL=http://127.0.0.1:8091/ \
/Applications/XAMPP/xamppfiles/bin/php backend/php/cli/setup.php

/Applications/XAMPP/xamppfiles/bin/php backend/php/cli/users.php create-admin chosen_admin admin@hospital.example 10000001
/Applications/XAMPP/xamppfiles/bin/php -S 127.0.0.1:8091 -t public backend/php/dev-router.php
```

The administrator command prompts for a password. Give accounts explicit `records_manager` and/or `dispatcher` roles with `backend/php/cli/users.php roles USER ROLE,ROLE` as appropriate; `admin` alone does not grant those permissions. Use fictional identities. On other XAMPP installations, substitute the installed PHP executable and database host/socket. The schema is installed through `setup.php`, which selects the configured database. Keep `public/` as the only web document root.

`setup.php` creates owner-only `backend/php/config/local.php`, a new application key, a dedicated database user and private storage directories. It creates no login or clinical records. Run `backend/php/cli/preflight.php` with the same PHP executable to verify the installation. Repeating setup/migration preserves the saved key/configuration; for later source updates run `backend/php/cli/migrate.php` before preflight. Keep configuration, key and storage together in private backups. For a mounted development installation, set both `HOSPITAL_BASE_PATH=/hospital` and `HOSPITAL_APP_URL=http://127.0.0.1:8091/hospital/` consistently. The optional Apache example is `backend/apache/hospital.conf.example`; the automated installation checks use PHP's local server and do not modify Apache.

## Organization

- `public/`: browser pages, styles, artwork/vendor assets, and page-specific JavaScript.
- `public/src/api-connection/`: shared API, session, language, theme and navigation-search scripts.
- `backend/api/v1/`: API entry points exposed at `/api/v1/` by the development router or the supplied Apache example.
- `backend/php/`: private bootstrap, configuration, shared core, procedural domain modules, components and CLI tools.
- `db/json/`: private Spanish/English dictionaries and fictional seed input; only the two dictionaries are exposed through `api/v1/translations.php?lang=es` or `lang=en`.
- `db/sql/`: schema and numbered migrations. `storage/` and `logs/` remain private runtime directories.

All 25 pages now use PHP. The 21 staff views share the guarded head/shell in `backend/php/components/shell.php`; the map retains its distinct navigation and layout. Document upload/edit share `backend/php/components/document-form.php`. Login, registration and recovery share `backend/php/components/auth.php`. The participant survey keeps its isolated public interface.

Personnel and Destinations share `public/src/css/management/catalogs.css`, with scoped exceptions for their distinct fields and layouts. Survey sharing dialogs live in the common `surveys/survey.css`. Each page loads the styles it uses. Retired HTML routes and the former HTML page API return 404; navigation search lists only implemented features.

All domain modules use ordinary functions in `backend/php/modules/`. Their API actions are explicit in `auth.php`, `accounts.php`, `settings.php`, `catalogs.php`, `patients.php`, `doctors.php`, `personnel.php`, `destinations.php`, `routes.php`, `ambulances.php`, `documents.php`, `transfers.php`, `tracking.php` and `surveys.php`, all under `/api/v1/`. Core HTTP, session and storage utilities retain their existing implementations. Document uploads retain their original bytes in private storage, with immutable versions and revocable patient links that expire after 24 hours.

Transfer booking, related catalog edits and simulation ticks use one shared database lock in `transfer-access.php`. Mapping happens before the booking lock and is revalidated inside the transaction; existing route snapshots and drafts remain versioned. Tracking is a shared fictional server simulation. Using the same PHP executable, run `backend/php/cli/simulate-transfers.php start SPEED`, then `backend/php/cli/simulate-transfers.php worker`; viewing a map never advances it. Surveys retain publication locks, revocable shared links, single-use invitations, anonymous participation and SQL results.

All select controls, backed category/center menus and autocomplete suggestions share `public/src/js/dropdowns.js` and `public/src/css/dropdowns.css`. Original native fields hold submitted values; the shared control preserves required/disabled states, localization, keyboard navigation, dynamic options and retained input. Dropdown styling follows Documents, as requested. Page geometry, fonts and imagery remain the appearance reference.

`public/src/css/responsive.css` is loaded last by the shared page heads and public participant page. It handles phone reflow at 770px, narrower fields below 500px, and touch-phone landscape. The shared navigation/profile helpers in `public/src/js/ui.js` keep drawers over the page, trap drawer focus, restore focus on dismissal and preserve desktop layouts. The 100px staff header remains on desktop. The public participant language picker is also available on phones.

Open the management hub at <http://127.0.0.1:8091/src/views/management/management.php> or the patient form at <http://127.0.0.1:8091/src/views/management/patients.php>. All in-app page links and API consumers use the new paths. Movements and measurements for each stage are in `docs/stage*-movements.json` and `docs/stage*-source-metrics.json`; `docs/source-metrics.json` compares this copy with the original. Current contracts are in [docs/api-contracts.md](docs/api-contracts.md).

## Verification

```sh
/Applications/XAMPP/xamppfiles/bin/php backend/php/cli/preflight.php
python3 tests/verify_project.py
python3 tests/install_integration.py --browser
python3 tests/source_inventory.py --reference tests/.runtime/stage1/reference
```

`verify_project.py` orchestrates the existing 16 integration suites, with a separate disposable database/account/storage for each module and automatic cleanup. Use `--modules settings,documents` for focused checks and `--output tests/.runtime/STAGE/functional` to retain private evidence. `install_integration.py` copies only application source into a new private directory, runs actual setup/admin/migration/preflight commands, verifies root/mounted routing and private-path isolation, and removes its temporary SQL resources/configuration/uploads. `--browser` adds the existing all-page UI and transfer/survey journeys against that fresh installation. Both commands require local setup database privileges, XAMPP PHP and the browser dependencies described below; they leave normal configuration and demonstration data untouched.

The browser run writes `ui/rendered-pages.json` inside its private output directory; pass it to `python3 tests/i18n_coverage.py --rendered-pages PATH` for translation/DOM and local script/stylesheet coverage. Stage 6 evidence and remaining deployment limits are recorded in `docs/stage6-verification.json` and `docs/progress.md`. Submission packaging is a separate step.

Existing integration/browser checks require their documented isolated `*_test` environment. `tests/ambulances_integration.py` checks fleet CRUD/transfer history, consolidated actions, and page permissions; `tests/ambulances_browser.cjs` uses its private fixtures to exercise persistence, search/pagination, focus, archive/restore, input retention and permission redirects. The browser journey uses a separate disposable database, not the demonstration database.

`tests/documents_integration.py` covers uploads, validation, exact downloads, version conflicts, history, ownership and storage recovery. `python3 tests/document_links_integration.py --browser` provisions and cleans its own fictional schema/accounts/storage, then checks patient expiry/revocation, byte ranges, both mounts/themes/viewports, staff sharing and an upload/edit/archive/restore journey. It requires the local setup database privileges, XAMPP PHP and bundled browser dependencies described above. Set `HOSPITAL_DOCUMENT_LINK_TEST_DIR` to keep private evidence in a stage-specific directory.

`tests/transfers_integration.py` and `tests/surveys_integration.py` cover booking conflicts, drafts, immutable routes, shared simulation/worker controls, survey ownership, publication, invitations and response rules. `tests/transfers_surveys_browser.cjs` uses the existing fictional browser fixtures to exercise actual draft/retry/edit/completion, two independent map browsers, survey authoring/publication, failed response retention and persisted results at root and mounted URLs. It uses the same Playwright tooling as the other browser checks. Set `HOSPITAL_WORKFLOW_TEST_DIR` to its private `env.json` directory and `HOSPITAL_WORKFLOW_MOUNT_URL` for a different test mount.

`tests/refactor_baseline.cjs` compares the private reference snapshot against this copy using fictional records, both themes, desktop/mobile, expanded navigation and representative dialogs/dropdowns. `BASELINE_FILES` selects original filenames; candidate URLs are mapped to their PHP equivalents. `BASELINE_OUTPUT_DIR` separates stage evidence. Set `BASELINE_DROPDOWN_CHANGE=1` when comparing the intentional Stage 3 dropdown/autocomplete styling; full screenshots are retained and matching control bounds are excluded from the surrounding-interface pixel comparison. Load telemetry is stubbed only in visual comparisons to keep repeated captures from exhausting its rate bucket; real diagnostics behavior has separate API checks. Its `rendered-pages.json` supplies authenticated PHP output to the existing translation/DOM coverage check. Browser checks require Node, Playwright with Chrome, and pngjs as development tools. Captures, raw reports, snapshots, credentials and capability tokens stay under ignored `tests/.runtime/` and must not be included in the submission archive.

For a later PHP snapshot, set `BASELINE_ENV_DIR`, `BASELINE_REFERENCE_DIR`, `BASELINE_REFERENCE_URL` and `BASELINE_CURRENT_URL`. Defaults retain the original comparison workflow. `HOSPITAL_RECOVERY_TEST_DIR` gives the existing mock-provider recovery checks a separate private evidence directory, as `HOSPITAL_DOCUMENT_LINK_TEST_DIR` does for patient links. Real provider email delivery remains unverified.

`tests/dropdowns_browser.cjs` uses isolated fictional fixtures to check shared menu styling, keyboard navigation, clipping, disabled controls, dynamic survey fields and free-text suggestions across both themes and desktop/mobile. It also verifies a real patient save after a simulated failed request. The original reference and all copied artwork/vendor bytes remain unchanged.

`tests/ui_browser.cjs` uses those same Playwright dependencies and isolated fictional browser fixtures. Set `HOSPITAL_UI_TEST_DIR` to the private directory containing `env.json` and `records.json`, and optionally `HOSPITAL_UI_URL` for its mounted URL. By default it reviews all 25 pages in both themes at desktop/phone sizes, then exercises the repaired Settings/map/survey actions and management/document dialogs. `HOSPITAL_UI_FILES` selects comma-separated page-name suffixes, `HOSPITAL_UI_SIZES` selects `desktop,mobile,narrow,tablet,landscape,tabletWide`, and `HOSPITAL_UI_LAYOUT_ONLY=1` skips the functional journeys. Set `HOSPITAL_UI_OUTPUT` to an ignored private evidence directory. UI changes and acceptance evidence are recorded in `docs/ui-verification.json` and `docs/ui-source-metrics.json`.

For compact management layouts, the same browser check also accepts `widePhone` (650px), `compact` (800px), `medium` (1000px) and `smallDesktop` (1200px). It checks text containment inside summary cards/buttons, neighboring controls, header/search separation and management navigation open/closed. `HOSPITAL_UI_JOURNEYS=settings` selects only the Settings effects/cancellation/failure journeys; the default remains `settings,map,surveys,dialogs`. Summary/list reflow uses the available panel width, including space taken by desktop navigation, while desktop staff headers retain their height.
