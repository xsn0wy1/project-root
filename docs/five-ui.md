# Five requested UI changes

Completed 23 October 2026. Frontend Phase 2 remains unstarted.

The [before/after gallery](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/review.html) contains 11 paired capture groups, including the five changes, both requested sidebars, all three viewer dialogs, and English/light phone examples. Original captures remain in ignored private runtime paths; no screenshots, credentials or capabilities are added to application source.

## 1. Login theme → account

An explicit change on the login page is remembered for that login. After successful authentication, the existing `preferences-update` request saves only `theme` with the settings revision returned by login, before navigation. An untouched choice or a click on the already-selected theme keeps the account preference. Failed authentication never saves a theme; retrying retains the choice. A failed or conflicting preference save reports to the console and still navigates successfully. Account language and other preferences are retained.

The same Light selection previously opened a Dark staff page and left the account Dark; it now opens the staff page in Light with Light saved in SQL.

| Before | After |
| --- | --- |
| ![Staff page stayed Dark after selecting Light](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/login-account-0.png) | ![Staff page uses the selected Light theme](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/login-account-1.png) |

## 2. Ambulance status quick change

Active ambulance details have the shared Documents-design dropdown for Operativa / Mantenimiento / Fuera de servicio. It sends the current revision and unchanged plate/model/capacity through the existing update endpoint. Success refreshes details, rows and metrics and uses the existing feedback. Failure preserves the selection, releases busy controls and reports to the console. A real concurrent save is rejected by the existing version check. Archived records retain their restore-only behavior, and the edit form retains its status field.

| Before | After |
| --- | --- |
| ![Plain ambulance status](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/ambulance-desktop-es-UY-dark-0.png) | ![Quick status dropdown](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/ambulance-desktop-es-UY-dark-1.png) |

## 3. New Transfer validation feedback

Failed local validation uses the existing message area to identify fields, marks invalid inputs and visible dropdown buttons with the existing error colors/ring, and scrolls to and focuses the first invalid control. Driver, companion and patient catalog mismatches also show a localized “choose from the list” explanation beside the field. Errors clear as values are corrected. Native validation popups are unnecessary; API/server failures retain their existing console-only behavior and input retention.

The rules are unchanged:

- **Nine always-required fields:** ambulance, driver, start date, start time, reason, origin, destination, transfer type and priority.
- **Patient:** additionally required from the patient catalog when the transfer type is Patient; disabled for equipment/biological transfers.
- **Crew:** driver must be a catalog driver. Companion is optional; when supplied, it must be a catalog copilot or doctor. Driver and companion must differ.
- **Date:** the form requires a date on or after today. The retained API separately permits scheduling from 24 hours before the server time through 366 days after it.
- **Locations:** origin and destination must differ. Existing facilities and typed addresses remain supported; selecting a saved route retains its existing endpoint/version rules.
- Existing permissions, operational availability, reservation/conflict, transaction and route checks remain in the unchanged API/domain code. Optional notes and option checkboxes remain optional.

| Before | After |
| --- | --- |
| ![Catalog mismatch had no visible explanation](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/validation-desktop-es-UY-dark-0.png) | ![Visible catalog explanations and first-invalid focus](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/validation-desktop-es-UY-dark-1.png) |

## 4. Sidebar

New Transfer and Upload Document now include the existing Gestión link, with its normal destination, icon and translations. Other sidebar output is preserved, including Edit Document's existing arrangement.

| Page | Before | After |
| --- | --- | --- |
| New Transfer | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/transfer-sidebar-desktop-es-UY-dark-0.png) | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/transfer-sidebar-desktop-es-UY-dark-1.png) |
| Upload Document | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/upload-sidebar-desktop-es-UY-dark-0.png) | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/upload-sidebar-desktop-es-UY-dark-1.png) |

## 5. Document viewer dialogs

Archive uses a styled confirmation with the actual document name, Cancel, a red confirm action and the existing translations. Cancel leaves the record unchanged; failed saves keep the dialog open for retry; busy controls and Escape protection prevent conflicting actions. Successful archive and restore keep their existing API calls, revision checks, history and navigation.

Share and QR use management-style icon headers, close buttons, readable URL fields and a primary copy action. QR revoke is outlined. Existing IDs, selected-file-version URLs, immutable bytes, clipboard fallback, staff permissions, patient-link expiry and revocation behavior remain intact. PDF-password dialog styling is unchanged.

| Dialog | Before | After |
| --- | --- | --- |
| Staff Share | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/share-desktop-es-UY-dark-0.png) | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/share-desktop-es-UY-dark-1.png) |
| Patient QR | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/qr-desktop-es-UY-dark-0.png) | [Capture](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-final/qr-desktop-es-UY-dark-1.png) |
| Archive | [Invoking page](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/archive-desktop-es-UY-dark-0.png) | [Styled confirmation](/Users/snowy/Downloads/project-root/tests/.runtime/five-ui/ui-returned-final/archive-desktop-es-UY-dark-1.png) |

The previous archive confirmation was browser chrome and is outside page screenshots. Its original message is recorded in the private report and gallery; the before image shows the invoking page. The Share/QR before images show their complete original dialogs.

## Verification

- **696 visual comparisons pass:** 564 existing strict states, 96 targeted-control states and 36 corrected/cancelled/viewer-sidebar states. The full original 636-state matrix is covered, with 60 added states. Both languages, themes and 1440×900 / 390×844 / 320×640 layouts are represented.
- The strict 564 states have zero computed-style and pixel differences. Targeted states compare every remaining computed property, hidden element and generated pseudo-element, with zero differences or changed pixels outside declared controls. Only the added navigation link consumes the transparent sidebar spacer's height; the spacer is never a pixel exclusion. Corrected form states place hidden dropdown/suggestion menus at identical current geometry through normal open/focus actions.
- Open Share/QR comparisons retain real screenshots, then neutralize the intentionally changed backdrop and shadow in both copies for the secondary outside-control pixel measurement. No comparison threshold is relaxed; all other page content remains compared.
- **517 SQL/API checks pass:** seven focused modules total 413, document links 46 and mock recovery 58. All API entry points, domain modules, SQL and the autoloader retain their bytes.
- **688 focused browser checks pass:** themes 57, fleet 23, transfers/surveys 89, dropdowns 136, i18n 71, mounted i18n 8, document links/forms 174 and recovery 130. The full theme suite adds 50 page/viewport cases with no script errors. Its reference now explicitly selects Dark; previously a saved Light account could seed the wrong reference palette. Existing limits are unchanged.
- Readability checks pass 98 cases. Translation/DOM coverage passes 1,818 bilingual keys, 25 fresh before/after renders, 1,112 literal references, all existing Spanish fallback/attribute/ID preservation, and 408 versioned asset URLs. PHP/JS syntax and structural equivalence pass 90 PHP files, 35 JS files and 13 exact intentional declarations.
- Final hover inspection confirms the light-theme copy action retains white text and its primary background, and the archive confirm remains red.

Inventory remains **267 first-party application files**; 254 retain their bytes. Code grows **2,308,855 → 2,320,661 bytes** (+11,806); CSS grows **693,435 → 698,091 bytes** (+4,656), with 33 rules added. JS adds 115 nonblank lines for the requested behavior, with zero direct nested conditional chains. Page-sheet duplication remains 66 groups / 77 extras; eight-line duplication remains 559 / 622. Cross-sheet duplicates rise by one group/extra to 69 / 80. Existing motion signatures are identical. This feature batch makes no reduction claim.

Exact changes are in [five-ui-changes.json](five-ui-changes.json), and sanitized totals are in [five-ui-results.json](five-ui-results.json). Raw sources, screenshots, credentials and logs remain ignored under `tests/.runtime/five-ui/`. The preliminary dropdown run used an older disposable Stage 3 fixture; those results were excluded and all 136 checks were rerun in this task's fixture. The suite archived its fictional test patient; the older fixture was retained.

Owned disposable SQL, credentials, storage and test servers are cleaned after verification. The original installation, normal private configuration, global Apache settings and demonstration server are preserved. No submission archive or schema migration was produced.
