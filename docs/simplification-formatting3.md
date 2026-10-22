# Doctors and Routes readability checkpoint

Complete; paused for review. The user verified the Destinations/Personnel checkpoint and authorized continuation with Doctors/Routes. Only the two PHP handlers and their two modules were reformatted. The private baseline includes the existing uncommitted fixes; those changes were preserved without a Git commit, tag or reset.

## Change and parity

Expanded compound lines, argument lists, validation conditions and callbacks. Native PHP comparison verifies that **all 90 application PHP token streams remain identical**, including operators, literal bytes, SQL and inline HTML. Significant tokens remain **69,699**. The other **262 first-party application files** retain their hashes, including templates, styles/scripts, dictionaries, migrations and artwork.

Doctors retains combined profile/staff revisions, linked account identities, catalog checks, overnight hours and pending-transfer restrictions. Routes retains ordered stops, catalog associations, immutable transfer snapshots, bounded mapping, cache lifetimes, request serialization and fallback behavior. Transaction order, permissions, API fields/envelopes/statuses and audit actions remain unchanged. No helper, class, dependency or schema change was introduced.

| Edited file | Lines before → after | Lines over 120 before → after |
| --- | ---: | ---: |
| API Doctors | 47 → 58 | 2 → 0 |
| API Routes | 41 → 49 | 1 → 0 |
| Doctors module | 168 → 291 | 33 → 5 |
| Routes module | 190 → 373 | 45 → 4 |

The four files grow **446 → 771 physical lines**. Overlong lines fall **81 → 9**; the remaining nine contain unchanged SQL literals. Function declarations remain 364, decision keyword/operator tokens remain 2,104 and semicolon tokens remain 3,873. Maximum physical function span stays at 91 lines. These measurements establish readability and parity; this pass makes no logic or runtime-performance reduction claim.

## Verification

- **207 focused functional checks pass before and after:** Doctors 18, Patients 66, Routes 21, Transfers 76 and Audit corrections 26. These cover clinical records, CRUD/archive/restore, identity links, revision conflicts, snapshots, mapping fallbacks/locking and the shared fictional simulation. All **12 affected API action outcome signatures match**. Mapping checks use isolated local test services/cache fixtures.
- The existing browser suite gains the optional `HOSPITAL_UI_JOURNEYS=doctorsRoutes` journey. **212 checks pass before and after**, with eight page/theme/viewport comparisons and 13 interaction groups. Both themes and Spanish/English are covered at desktop/390px; new/edit forms, shared selects, retained input after rejected updates, reachable save actions and archive cancellation also run at 320px. Browser update failures are intercepted at 503, preserving fixture records.
- **All 40 screenshots are pixel-identical without masks.** Captures include page/navigation states and new/failed-save dialogs. Representative desktop/phone captures in both themes were inspected. No unhandled script errors, missing local assets or Google Fonts requests occurred.
- All **90 PHP files**, the modified browser test and all **27 Python test sources** pass syntax checks. The existing i18n check passes **1,852 bilingual keys, 25 page bindings/assets and 1,119 literal references**, preserving original Spanish fallback text, attributes and IDs. Two fresh page renders supplement 23 unchanged accepted renders.
- Preflight is healthy on PHP 8.0.28/MariaDB 10.4.28, with no missing migrations. Ten disposable suite schemas and their database accounts/storage were cleaned; read-only verification finds zero remaining verification schemas/accounts. Test ports 8113/8879 are closed. The demonstration responds on port 8091.

Three initial incomplete browser attempts exposed timing/selector mistakes in the newly added test. The test now waits for the asynchronous doctor detail panel and its animation, and uses its existing Edit/archive buttons. Those diagnostics preceded the application update and are excluded from passed totals.

The full core suite, fresh install, standalone recovery/document-link suites and full 25-page visual matrix were not repeated for this token-identical pass. Real email delivery, live mapping providers, physical devices/Safari and eventual Apache-user deployment remain the existing external checks. Original private configuration/data and global Apache settings were unchanged; no submission archive was produced.

## Measurements and continuation

First-party application files remain **266**; code/dictionaries grow **2,200,798 → 2,205,400 bytes** (+4,602). Formatting-neutral duplication stays at **2,534 repeated 16-token groups / 5,251 extra occurrences**. The layout-sensitive overlapping eight-line indicator changes **497 → 496 groups / 518 → 522 extras**. Existing logic was expanded into readable lines; no reuse reduction is claimed.

Cumulative versus the original: **92 fewer first-party files and 57,643 fewer first-party bytes**. Vendor/generated files, development tooling and runtime evidence are excluded. See `simplification-formatting3-source-metrics.json` and `source-metrics.json`.

Detailed verification is in `simplification-formatting3.json`. Baseline source, sanitized contracts, fictional credentials, logs and screenshots remain ignored under `tests/.runtime/simplification/checkpoint3/`.

Review this checkpoint first. The next proposed bounded pass is the Patients PHP handler/module using the same parity and focused verification gates. Semantic consolidation needs the relevant role/failure cases strengthened first. Broader JavaScript/CSS changes, key/asset deletion, advanced-code relocation and packaging remain separate.
