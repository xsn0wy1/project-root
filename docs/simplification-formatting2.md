# Destinations and Personnel readability checkpoint

Complete; paused for review. The user verified the initial Ambulances pilot and authorized this next bounded pass. Only the two PHP handlers and their two domain modules were reformatted. Existing uncommitted review fixes were included in the private baseline and preserved; no Git tag, commit or reset was made.

## Change and parity

Expanded dense expressions, argument lists and compound lines while keeping variable/function names, branches, callbacks, SQL literals and statement order intact. Native PHP token comparison verifies **all 90 application PHP files** against the baseline: **69,699 significant tokens remain identical**. The other **262 first-party application files**, including all 25 HTML templates, styles/scripts, dictionaries, SQL/migrations and artwork, retain their bytes.

Personnel still handles legacy staff profiles, combined revisions, linked account identities, non-crew roles and active-transfer reservations. Destinations still clears outdated coordinates when an address changes and preserves historical route snapshots. Named locks, actor checks, transaction rollback, conflict handling, audit actions and all public interfaces remain unchanged. No helper, class, framework or runtime dependency was added.

| Edited file | Lines before → after | Lines over 120 before → after |
| --- | ---: | ---: |
| API Destinations | 44 → 57 | 1 → 0 |
| API Personnel | 44 → 57 | 1 → 0 |
| Destinations module | 98 → 197 | 18 → 2 |
| Personnel module | 140 → 283 | 36 → 5 |

The pass removes **49 overlong lines**. The remaining seven contain unchanged SQL literals; their exact bytes take precedence over the preferred line length. Function declarations remain 364, decision keyword/operator tokens remain 2,104, and semicolon tokens remain unchanged. The maximum physical function span grows from 68 to 91 lines because statements are expanded; no function or branch was added.

## Verification

- **131 focused functional checks pass before and after:** Destinations 17, Personnel 17, Routes 21 and Transfers 76. Disposable schemas cover CRUD, archive/restore, clinical/dispatch access, revisions, catalog availability, immutable snapshots, resource conflicts and the shared fictional simulation. All 12 affected action outcome signatures match.
- The existing browser suite now accepts `HOSPITAL_UI_JOURNEYS=catalogs`. It passes **164 checks before and after**, with eight affected-page theme/viewport views and 13 interaction groups. Both themes and Spanish/English are covered at desktop/390px; details, shared selects, failed-save retention, cancellation and reachable actions are additionally checked at 320px. Update failures are intercepted; the fictional browser fixture is not edited.
- **24 screenshots are compared without masks**, including navigation and detail dialogs. Maximum exact pixel difference is **0.000155%**; no pixel differs by more than 12 in any color channel. Representative phone/light and desktop/dark detail captures were inspected. No unhandled script errors, missing assets or Google Fonts requests occurred.
- All **90 PHP files**, the modified browser test and all **27 Python sources** pass syntax checks. The existing i18n check passes **1,852 bilingual keys, 25 bindings/assets and 1,119 literal references**. The two affected pages were freshly rendered; the other 23 unchanged templates reuse their accepted render baseline.
- Existing local preflight is healthy on PHP 8.0.28/MariaDB 10.4.28. Temporary verification SQL schemas/accounts and the test server were removed and verified absent. The demonstration remains available on port 8091.

The full core suite, fresh-install check, standalone recovery/document-link checks and full 25-page visual matrix were not repeated for this token-identical change. Physical devices/Safari and eventual Apache-user deployment remain separate checks. No schema, private configuration, original-project data or global Apache setting changed.

## Measurements

First-party files remain **266**; code/dictionaries grow **2,197,347 → 2,200,798 bytes** (+3,451). The pass improves readability and claims no logic/reuse reduction. Formatting-neutral 16-token duplication stays at **2,534 groups / 5,251 extra occurrences**. The older overlapping eight-line indicator changes **489 → 497 groups / 509 → 518 extra occurrences** as already-existing statements become separate matching lines.

Cumulative versus the original: **92 fewer first-party files and 62,245 fewer first-party bytes**. Vendor/generated artifacts, runtime evidence and development tooling remain excluded from application measurements. See `simplification-formatting2-source-metrics.json` and `source-metrics.json`.

## Evidence and continuation

Detailed verification is in `simplification-formatting2.json`. Baseline source, sanitized API observations, fictional credentials, screenshots and logs remain ignored under `tests/.runtime/simplification/checkpoint2/`.

Review this checkpoint first. The next proposed bounded pass is Doctors and Routes PHP handlers/modules, using the same baseline/token/focused-functional/visual checks. Semantic helper extraction needs the relevant role/failure cases strengthened first. JavaScript/CSS consolidation, key/asset deletion, advanced-code relocation and submission packaging remain separate.
