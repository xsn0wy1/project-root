# Permissions cleanup and UI repairs — 2026-10-26

Complete; paused for review. `user_has_role()` grants a requested role or admin access without changing stored roles. Page guards, clinical/catalog checks, transfer management and admin-only checks use it. `HospitalAuth.hasRole()` applies the same rule to authorization checks, management cards, navigation search and page scripts. Accounts templates/scripts and their raw stored-role checkboxes are unchanged. Crew-duty membership and last-admin membership checks still inspect their actual stored assignments.

Active staff share document lists, metadata, immutable versions, editing, archive and restore. Uploader metadata is retained, and a cross-uploader replacement test verifies the uploader recorded for each version. Anonymous/disabled access, CSRF, conflict handling, immutable bytes and patient-link expiry/token/version/revocation checks remain. Surveys retain owner-plus-admin scope. Admins inherit dispatch access; dispatchers manage all transfers; other staff read only assigned-crew transfers and cannot mutate them.

New Transfer and transfer detail mutation controls are hidden for non-dispatch users, including in the server-rendered HTML; authorized controls keep their initial layout. Visible links, navigation search, management cards, the map and sidebar were audited. Four fictional single-role accounts were registered in an isolated test database and approved using the existing users CLI. Existing presentation role assignments were compared before/after and are unchanged; the actual demo admin opens all 21 staff views.

## Role/page results

**Common (13):** Home, Settings, Gestión hub, Documents list/upload/edit/viewer, Surveys list/new/view, Transfers list/map/detail. Object pages use shared documents, owned surveys or assigned transfers as appropriate; unknown/unowned IDs retain their existing missing-record responses.

| Role | 200 | 403 |
| --- | --- | --- |
| Admin | All 21 | None |
| Dispatcher | Common + Ambulances, Personnel, Destinations, Routes, New Transfer (18) | Patients, Doctors, Accounts |
| Records manager | Common + Patients, Doctors (15) | Ambulances, Personnel, Destinations, Routes, New Transfer, Accounts |
| Staff | Common (13) | All eight restricted pages above |

No visible link targets a forbidden page. Assigned staff have no visible transfer edit/cancel/status controls. Survey rows stay owner-scoped, so their buttons cannot open another owner's survey. Common document actions are available across uploaders. The Gestión hub remains an authenticated common page; its individual cards retain their required roles.

## Four UI repairs

- The patient-language select uses its existing native control, excluded from dropdown enhancement. The original globe/arrow remain correctly spaced on desktop and phone; keyboard/native selection and runtime language switching pass.
- Publish Survey uses the new 24-viewBox, two-pixel-stroke `check-icon.svg`, rendered by the existing 17-pixel button-icon styles and theme filters.
- Map body font smoothing inherits the normal app rendering rather than forcing antialiasing; computed smoothing matches Documents.
- All management labels use `main_menu.gestion`: “Gestión” / “Management”. The unused old dictionary key is retained for compatibility with cached scripts.

All four repairs were captured and visually reviewed in dark/light and desktop/phone layouts, including expanded navigation. Unrelated CSS, icon bytes, markup structure, IDs, API envelopes, schema, stored roles, Accounts UI, form rules, themes and localization behavior remain unchanged.

## Verification

The four-role matrix passes 672 cases: 21 views × 4 roles × 2 languages × 2 themes × desktop/phone, with 536 expected 200s and 136 expected 403s. An additional 84-case smoke verifies server first-paint control visibility and all-role navigation. The actual demo admin passes a separate 21-page check without changing role assignments.

Existing theme/dark-geometry/error-page, language/CPU-first-paint, mounted-language, dropdown, fleet, transfer/survey workflow, full default UI, document-link and recovery browser suites pass. Full UI reports 954 checks / 100 page-theme-viewport cases; document links add 182 browser checks and recovery adds 130. The initial assertions for the superseded admin/document policy were updated and rechecked; unchanged permission denials and safety expectations remain enforced. Affected SQL/API suites plus document links/recovery pass 767 checks. Translation coverage passes 1,827 bilingual keys and all 26 page bindings/assets. Exact source equivalence passes 92 PHP token streams / 35 JS ASTs with 26 explicit file declarations; the Accounts UI, SQL/schema and other application files retain their bytes.

Measurements: +1 SVG file, +864 first-party bytes (+653 code bytes), no page-count or duplicate-eight-line-window increase. Sanitized role/page results, exact declarations and measurements are adjacent JSON files. Screenshots/logs remain ignored under `tests/.runtime/permissions-ui-20261026/`; owned disposable databases/accounts/servers and credential fixtures are removed after verification. The presentation dataset and previous checkpoint status are preserved. No palette rollout, migration, framework, dependency or submission archive was added.
