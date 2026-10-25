# Explicit notifications — 2026-10-25

`HospitalNotify.show(text, type)` is the single shared component for new popups. It renders plain text, stacks at the top-right, supports `error` / `success` / `info`, closes individually and expires after six seconds. Errors use `role="alert"`; other types use `role="status"`. The component loads on staff, auth, participant and standalone error pages, including inside dialogs, with both themes and phone layouts.

The request-error event and visible-message scanner are removed. Transport errors are logged once without deciding presentation; a caller's `HospitalApi.report(error, context, options)` shows the actual error message once unless explicitly silent, aborted or a staff-page 401. Login, registration and recovery explicitly keep their server errors inline and retain input. Settings gains its missing save confirmation; existing catalog, document, transfer and survey confirmations/toasts are retained without duplicate success popups.

Explicit silent calls:

| Source | Calls |
| --- | --- |
| `auth-session.js` | Initial/session `me`, heartbeat, visibility/pageshow/cross-tab refresh and optional diagnostics. Diagnostics 429 retains its existing silent discard. |
| Ambulances, Doctors, Personnel, Destinations | Automatic `list` refresh on window focus; foreground reads/saves still report normally. |
| `traces/trace.js` | The 15-second overview refresh (`load(false, true)`); explicit filtering/paging remains visible on failure. |
| `traces/trace-view.js` | Three-second tracking snapshots and automatic base-map checks. |
| `traces/trace-map.js` | Automatic fleet/snapshot polling, including its missing-selection fallback. |
| `surveys/survey-view.js` | Fifteen-second/focus survey refresh and answer reads caused by automatic refresh, locale or resize redraws; explicit answer search/paging remains foreground. |
| Shared API/auth forms | Transport-only console reporting, native constraint reporting, and errors explicitly displayed inline by login/register/recovery. |

There is no timer-based draft autosave. The existing draft save/clear calls are explicit user actions and retain their existing feedback.

Passed: 277 theme/notification plus 13 actual polling checks, 1,590 UI checks / 100 page/theme/viewport cases, 136 dropdown, 93 transfer/survey, 71 i18n, 130 recovery and 43 fleet browser assertions. The eight notification cases cover both languages/themes and desktop/phone, wrong credentials, real SQL duplicate plates, offline errors, input retention, roles, stacking/close/expiry and existing-success-toast preservation. All-page theme checks pass 50 viewport pairs and 128 error-page cases with exactly theme/UI scripts. Recovery passes 58 SQL/API checks; the five focused domain suites pass 336, with the fleet's fresh-fixture rerun also passing 99.

Translation coverage passes 1,827 bilingual keys / 26 fresh rendered pages / 1,111 literal references. Source equivalence passes 91 PHP / 35 JS files with 21 exact intentional declarations; 247 application files, 47 API/core/domain/SQL files and both dictionaries retain bytes. Existing CSS outside the replaced notification block and new auth-error rule retains bytes. The 98 readability decision checks pass. Application files/pages stay 268/26; code adds 2,188 bytes and overlapping eight-line duplicate groups/extra occurrences each add five; no reduction claim.

Exact results, declarations and measurements are in the adjacent JSON files. The 32-image gallery and logs stay private under ignored `tests/.runtime/notifications/`. Owned databases, servers, storage and credentials are cleaned up. No new application files, framework, schema/configuration change or palette rollout. Paused for review; the earlier readability final regression remains a separate checkpoint, with these notification/error-output changes intentional.
