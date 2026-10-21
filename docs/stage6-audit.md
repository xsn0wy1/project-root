# Stage 6 audit

Completed on 21 October 2026; paused for review. The project remains plain PHP, CSS and JavaScript with no build step. No blocking defect was found in the exercised application flows. Submission packaging is separate.

## Improvements made

- Personnel and Destinations share 159 identical style rules in `public/src/css/management/catalogs.css`. Their distinct layouts and fields retain scoped rules and the original cascade. This removes 13,461 duplicated stylesheet bytes.
- All three staff survey pages load sharing-dialog styles from their common stylesheet. Settings no longer requests the unrelated 17,916-byte Documents stylesheet; shared dropdown styling already covers its controls.
- Removed the unused HTML-routing API and rewrites. All staff PHP views retain their permission guards, and obsolete HTML/API URLs return 404.
- Navigation search no longer suggests cancelled Reports or Calendar features. The missing-configuration error now points to the current README in both languages.
- Repaired stale migration checks and verified sequential upgrades through migration 014. Added small runners around the existing integration/browser suites, with disposable databases and automatic cleanup. Local stylesheet/script failures and safe browser failure diagnostics are now checked explicitly.

## Verification

| Area | Result |
| --- | --- |
| Core integration | 649 checks across 16 existing suites |
| Patient file links | 46 SQL/API checks and 139 browser checks |
| Password recovery | 39 SQL/API checks and 130 browser checks using the mock provider |
| Clean installation | Actual setup, administrator creation, repeated migration, preflight, private paths and guarded pages at `/` and `/hospital`; 79 recorded checks including both browser-suite completions |
| Fresh-install interactions | 228 UI checks across ten interaction groups, plus 53 transfer/survey workflow checks |
| Responsive review | All 25 pages in both themes at desktop/phone sizes: 100 views; 20 additional compact catalog views and a successful isolated narrow-phone journey |
| Visual preservation | Six affected pages: 24 page comparisons and 32 navigation/dialog comparisons; geometry/styles match, with at most 0.008% surrounding-interface pixel difference |
| Integrity | 88 PHP files, 35 application scripts, 12 browser scripts and 25 Python sources pass syntax; 1,847 bilingual keys and all 25 rendered page bindings/assets pass coverage |
| Original assets | All 607 original reference-source hashes and 308 copied artwork/vendor hashes remain unchanged |

The journeys exercise real persistence: failed-save retention, specialty removal/cancellation, profile actions, dropdowns, document upload/replacement/download/archive/restore and QR access, transfer drafts/conflicts/snapshots/shared simulation/completion, and survey publication/invitations/anonymous responses/results. Spanish fallback text, attributes and IDs remain preserved. The only dictionary text change updates the setup-document reference.

One combined browser run completed its 100-view matrix and later timed out waiting for the document viewer at 320px. The isolated journey and final fresh-install replay both pass. That partial run is retained as a diagnostic and is not counted as a fully passed suite. Different local URLs produce different QR pixels; visual comparisons exclude those generated contents while checking their geometry and styling.

## Size and reuse

| Measurement | Before Stage 6 | After Stage 6 |
| --- | ---: | ---: |
| First-party application files | 267 | 264 |
| Code/dictionary bytes | 2,200,794 | 2,184,741 |
| Repeated eight-line groups | 854 | 489 |
| Extra overlapping occurrences | 886 | 517 |

Stage 6 removes **3 files and 16,053 bytes**. Against the original, the project has **94 fewer files and 78,302 fewer bytes**. Vendor, private runtime data, documentation and development tests are excluded. Duplicate windows overlap; the 159 shared rules provide the concrete reuse evidence.

The shared catalog stylesheet is 28,159 bytes, so an individual cold catalog visit loads more stylesheet bytes than before; source consolidation does not imply every page downloads less. Settings saves one stylesheet request. Roughly 10.3 MB of the remaining first-party size is artwork whose bytes are intentionally preserved.

## Remaining work and limits

- Prepare the submission archive after review, excluding private configuration, credentials, uploads, logs, snapshots and development evidence. Preserve required vendor assets and notices.
- Verify actual email delivery when private provider configuration is available. The mock transport verifies the recovery flow, not a provider account.
- Check the final deployment under its actual Apache user/API Alias and on physical phones/Safari. This audit used XAMPP PHP 8.0.28, MariaDB 10.4.28 and Chromium emulation without changing global Apache settings.
- Live map/geocoding providers remain external dependencies. Fictional cached routes, mapping fallbacks and shared server simulation are verified; no real GPS is used.

No additional framework, feature rewrite or image recompression is needed for this checkpoint. The existing isolated demonstration remains available on port 8091. Temporary audit SQL accounts/databases and servers were removed and verified absent. Private evidence remains ignored under `tests/.runtime/stage6/`; detailed results, movements and measurements are in the adjacent Stage 6 JSON files.
