# Saved settings and staff-page startup audit

Completed 2026-10-23. Frontend Phase 2 remains unstarted.

The existing staff guard reads locale, theme and the four preference flags in one query. The Settings template renders each switch's saved class and ARIA state. A missing row uses the schema/new-account defaults: photo visibility **off**, optional diagnostics **off**, security notifications **on**, automatic logout **on**. `settings.js` is byte-identical; its render, saving, failure recovery and cross-tab behavior remain intact.

Staff HTML also carries the saved theme, and the early theme script uses it for staff pages. Public appearance keeps its cache behavior. Authenticated PHP labels use the page's saved locale; API requests retain their existing header-language behavior. Settings renders the matching theme highlight/buttons, staff name and specialty chips. Existing normalized readers and access checks supply the initial values; API/SQL schema and CSS are unchanged.

## Audit of every staff page

All 21 staff pages were inspected in source and in Chrome at CPU ×6 with populated fictional records and a stale dark cache against a saved light theme. A second pass held API responses until fonts settled, separating font/PDF loading from API state corrections. The Form deep link was checked separately, for 22 visits.

The common theme correction could repaint **every staff page** after the API response: high visibility with a cold/stale cache. Saved-theme HTML and staff initialization now prevent it. The table lists the other findings; ordinary loading availability remains functional.

| Page(s) | Initial correction and visibility | Result |
| --- | --- | --- |
| Settings | Four switches had opposite hardcoded states; the visible knob animated for 0.36 s. Light theme moved its highlight for 0.42 s. Staff name widened its control about 54 px; six specialty chips moved the add button about 142 px. | Saved switches, theme controls, name and chips render on the server. |
| Documents directory | Empty national-ID/role lines and placeholder name moved the sidebar actions about 45 px. Filters correctly start at all files/unsorted/grid/unarchived. | Staff metadata renders before paint. Paging availability can still dim while data loads. |
| Accounts | An empty count footer gained 14 px after loading and shortened the list. The default all-status filter already matches JS. | The real authorized account count renders initially. |
| Survey detail | `?tab=form` initially showed Summary, then switched panels and tab styling. The API status badge added 4 px above the tabs. | Requested tab and access-checked status render initially. JS uses that tab during its first reload and initializes hidden Summary content without selecting/painting it. |
| Transfer map | “Simulation” changed to stopped/stale/paused/history wording, shifting header counters about 45 px. Layer/status filters already match their checked defaults. | The read-only simulation state and authorized historical selection seed the label/class. Live simulation updates remain active. |
| Main menu | Greeting/name data fills its existing panel; no other selected control changes. | Common theme fix only. |
| Management overview | Counts arrive inside existing cards. Low visibility; no control movement or state transition. | Unchanged. |
| Patients, Doctors, Ambulances, Personnel, Destinations, Routes directories | Statistics and closed filter choices populate. Initial status, role and archive filters match JS; no selected-control transition or layout jump. | Unchanged. |
| Document upload and edit | Catalogs and record fields load into fixed controls. Save availability briefly dims while its required data/editor is loading. | Retained as loading/ready behavior, rather than a saved-state correction. |
| Document viewer | PDF counters/thumbnails arrive after decoding. A roughly 2 px tool adjustment follows PDF/font settling; no remembered thumbnail-control state is corrected. | Retained document loading behavior. |
| Survey directory | Count text replaces zero within its badge; list/archive/filter defaults match. Low visibility; surrounding controls stay in place. | Unchanged. |
| Survey builder | Loaded drafts select their color/configuration in initially hidden stages. Completion markers update inside fixed circles without transitions. | Unchanged. |
| Transfers directory | Initial filters match; live counts/data fill reserved areas. | Unchanged. |
| New transfer | Catalog choices and readiness populate the form; no persisted filter/tab is corrected. | Unchanged. |
| Transfer detail | Record/lifecycle data populates its reserved panels and timeline. No additional startup control transition. | Unchanged. |

Counter glyph widths may change as real figures replace placeholders; the corrected headers/footers prevent those updates from moving surrounding controls. Interactive saves, loading states and actual live-data changes retain their existing behavior.

## Verification

- **CPU ×6 first paint:** an account with off/off/on/on preferences, opposite to the old HTML defaults, paints those exact saved states. API confirmation is deliberately delayed until after first contentful paint. Both dark/stale-light and light/stale-dark cases have **zero preference changes, zero knob/highlight transition events and zero active animations**. Before the fix all four states were corrected after paint and the visible knob generated four transition events. The normal CSS transitions remain unchanged. The permanent check runs through `theme_behavior.cjs` using `settings_first_paint.cjs`.
- **432 unique final computed-style/pixel comparisons pass with zero differences**, covering all 25 pages, both languages/themes, desktop/mobile and the Form deep link. The initial 424-state matrix was followed by a survey-only initialization correction: all 24 affected states were rerun/replaced, 400 source-identical states were retained, and eight Form-route states were added. Full-property checks include hidden DOM; no masks or tolerances were added.
- **96 isolated API checks** pass: Settings 54, localization 27, themes 15. The focused theme browser suite passes **32 checks**, including saves, cross-tab updates, account changes, failed saves, drafts and both first-paint cases. The i18n browser suite passes **71 assertions**, and mounted switching **8 checks**.
- **10 raw HTML assertions** verify missing-row defaults and the opposite saved booleans/light theme using a separate fictional account. PHP/JS syntax and structural equivalence pass: 90 PHP files, 35 JS files and ten exact intentional declarations in `settings-startup-changes.json`. `settings.js` and every API entry point retain their bytes.
- Inventory remains **267 first-party application files** excluding vendor/generated/private artifacts. Code grows **2,303,626 → 2,308,855 bytes** (+5,229) for server rendering. All CSS bytes/rules/motion and duplication measurements are unchanged: 693,435 CSS bytes, 66 page duplicate groups / 77 extras, 559 eight-line groups / 622 extras. JS adds nine intentional nonblank lines; no formatting-only growth.

Aggregates and scope proof are in `settings-startup-results.json`. Raw snapshots, captures, reports and logs remain ignored under `tests/.runtime/settings-startup/`. Owned disposable SQL, credential, storage and server fixtures are cleaned up after verification. The original installation and existing demonstration server are preserved.
