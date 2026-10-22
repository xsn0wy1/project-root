# Patients readability checkpoint

Complete; paused for review. The user verified Doctors/Routes and authorized continuation with the Patients PHP handler and module. This finishes formatting the six management catalog handler/module pairs. The private baseline includes all previously accepted uncommitted work; no Git commit, tag or reset was made.

## Change and parity

Expanded dense statements, argument lists, conditions, payload arrays and callbacks. Native PHP comparison verifies **all 90 application PHP token streams remain identical**, including SQL/string literals, operators and inline HTML. Significant tokens stay at **69,699**. The other **264 first-party application files**, including templates, browser code/styles, dictionaries, migrations and artwork, retain their hashes.

Patients preserves its POST/CSRF list contract, explicit records-manager access, date/estimated-age semantics, creation-token retries, shared identity/phone validation, emergency contacts and encounter history. Discharge/re-admission, retained inactive doctor history, revision conflicts, transaction rollback, archive restrictions and audit actions remain unchanged. Existing legacy appointment counts/restrictions were preserved without adding appointment management. No helper, dependency or schema change was introduced.

| Edited file | Lines before → after | Lines over 120 before → after |
| --- | ---: | ---: |
| API Patients | 49 → 60 | 2 → 0 |
| Patients module | 282 → 504 | 66 → 14 |

The two files grow **331 → 564 physical lines**; overlong lines fall **68 → 14**. Remaining overlong lines contain unchanged SQL literals. Function declarations stay at 364, decision keyword/operator tokens at 2,104, semicolon tokens at 3,873 and the maximum physical function span at 91 lines. This is a readability change with unchanged logic.

## Verification

- **177 focused checks pass before and after:** Patients 66, Doctors 18, Personnel 17 and Transfers 76. Coverage includes access restrictions, clinical validation, creation retries, competing edits, rollback after SQL faults, encounter history, shared document/transfer identities and archive conflicts. All **seven Patients API action outcome signatures match**.
- The existing browser suite gains `HOSPITAL_UI_JOURNEYS=patients`. **140 checks pass before and after**, with four page/theme/viewport comparisons and seven interaction groups. Both themes and Spanish/English are represented at desktop/390px; new/edit dialogs, keyboard dropdown selection, clinical-field enablement, birth-date/age behavior, details, retained input after failed saves and archive cancellation also run at 320px. Update failures are intercepted at 503; browser checks do not modify patient records.
- **All 24 final screenshots are pixel-identical without masks.** Page/navigation, new/edit and detail states are covered. Representative narrow-phone/light and desktop/dark captures were inspected. No unhandled script errors, missing local assets or Google Fonts requests occurred.
- All **90 PHP files**, the modified browser test and all **27 Python sources** pass syntax checks. The existing i18n check passes **1,852 bilingual keys, 25 page bindings/assets and 1,119 literal references**, preserving Spanish fallback text, attributes and IDs. One fresh Patients render supplements 24 unchanged accepted renders.
- Preflight is healthy on PHP 8.0.28/MariaDB 10.4.28, with no missing migrations. Eight disposable suite schemas and their accounts/storage were cleaned; read-only verification finds zero remaining verification schemas/accounts. Port 8113 is closed and the demonstration responds on port 8091.

Initial browser diagnostics corrected the new test's native-option assertion: direct Chrome inspection confirmed `OPTION.disabled` was already correct. Two initial narrow-light captures differed in scroll/focus state. The test now blurs the focused field and settles document/dialog/field scroll before form captures. Matched baseline/formatted runs were repeated; initial diagnostics are retained privately and excluded from final visual results. Application focus, scrolling, styles and animations were unchanged.

The full core suite, fresh install, standalone recovery/document-link suites and full 25-page browser matrix were not repeated for this token-identical pass. Real email delivery, live mapping providers, physical devices/Safari and eventual Apache-user deployment remain the existing external checks. Original private configuration/data and global Apache settings were unchanged; no submission archive was produced.

## Measurements and continuation

First-party application files remain **266**; code/dictionaries grow **2,205,400 → 2,208,286 bytes** (+2,886). Formatting-neutral duplication stays at **2,534 repeated 16-token groups / 5,251 extra occurrences**. The layout-sensitive overlapping eight-line indicator changes **496 → 507 groups / 522 → 534 extras**. No logic/reuse/runtime-performance reduction is claimed for formatting.

Cumulative versus the original: **92 fewer first-party files and 54,757 fewer first-party bytes**. Vendor/generated files, development tooling and runtime evidence are excluded. See `simplification-formatting4-source-metrics.json` and `source-metrics.json`.

Detailed verification is in `simplification-formatting4.json`. Baseline source, sanitized observations, fictional credentials, logs and screenshots remain ignored under `tests/.runtime/simplification/checkpoint4/`.

Review this checkpoint first. The next proposed bounded pass strengthens Destinations/Personnel write-permission, rejection and rollback cases in the existing suites before assessing a small shared helper. The earlier API matrix identifies gaps for get/archive/restore and rejected updates. Broad JavaScript/CSS changes, key/asset deletion, advanced-code relocation and packaging remain separate.
