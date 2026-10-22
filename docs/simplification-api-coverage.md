# API action × suite inventory

There are 96 rows: 95 top-level ?action values and the actionless translation endpoint. Nested body commands and every method/role combination are additional dimensions, not separate rows. The 2,806 sampled requests include before/after core runs and the focused gap replay; they are not independent test cases.

90 rows have shared-Client observations. Source references identify existing separate recovery, multipart and binary tests for the remaining six; those standalone suites were not rerun here. Role context means last authenticated payload, not an independently asserted permission. Statuses include deliberately wrong-method/unauthenticated requests. A missing rejection is a review candidate, not automatically a bug.

| Domain/action | Observed suites | Methods | Statuses | Additional helper evidence |
| --- | --- | --- | --- | --- |
| `accounts:directory` | ambulances, audit_corrections, auth, documents, i18n, patients, release_fixes, settings, theme, transfers | GET | 200, 401, 403 | — |
| `accounts:manage` | ambulances, audit_corrections, auth, documents, i18n, patients, release_fixes, settings, theme, transfers | POST | 200, 409 | — |
| `accounts:reset-password` | audit_corrections | POST | 200 | — |
| `ambulances:list` | ambulances | GET, POST | 200, 401, 403, 405, 422 | — |
| `ambulances:get` | ambulances | GET | 200, 404, 422 | — |
| `ambulances:create` | ambulances | GET, POST | 201, 400, 403, 405, 409, 422 | — |
| `ambulances:update` | ambulances | POST | 200, 409 | — |
| `ambulances:archive` | ambulances | POST | 200, 409 | — |
| `ambulances:restore` | ambulances | POST | 200 | — |
| `auth:change-password` | audit_corrections | POST | 200, 422, 429 | — |
| `auth:csrf` | ambulances, audit_corrections, auth, destinations, doctors, documents, i18n, patients, personnel, release_fixes, routes, settings, surveys, theme, transfers | GET | 200 | — |
| `auth:heartbeat` | auth | POST | 200, 401, 403 | — |
| `auth:login` | ambulances, audit_corrections, auth, destinations, doctors, documents, i18n, patients, personnel, release_fixes, routes, settings, surveys, theme, transfers | GET, POST | 200, 400, 401, 403, 405, 429 | — |
| `auth:logout` | auth, i18n | POST | 200 | — |
| `auth:me` | audit_corrections, auth, i18n, patients, personnel, release_fixes | GET | 200, 401 | — |
| `auth:register` | ambulances, audit_corrections, auth, destinations, doctors, documents, i18n, patients, personnel, release_fixes, routes, settings, surveys, theme, transfers | POST | 201, 400, 409, 422 | — |
| `auth:reset-password` | Separate helper | — | — | `tests/password_recovery_integration.py:140`, `tests/password_recovery_integration.py:167` |
| `auth:reset-request` | Separate helper | — | — | `tests/password_recovery_integration.py:139`, `tests/password_recovery_integration.py:213` |
| `auth:revoke-session` | auth | POST | 200, 404 | — |
| `auth:sessions` | auth | GET | 200 | — |
| `catalogs:facilities` | destinations, settings | GET | 200, 401 | — |
| `catalogs:specialties` | settings | GET, POST | 200, 401, 405 | — |
| `catalogs:document-categories` | audit_corrections, documents | GET | 200 | — |
| `catalogs:patients` | documents, patients | GET, POST | 200, 401, 403, 405 | — |
| `destinations:archive` | destinations | POST | 200 | — |
| `destinations:create` | destinations | POST | 201, 403, 409 | — |
| `destinations:get` | destinations | GET | 200 | — |
| `destinations:list` | destinations | GET | 200, 401, 403 | — |
| `destinations:restore` | destinations | POST | 200 | — |
| `destinations:update` | destinations | POST | 200, 409 | — |
| `doctors:archive` | doctors, release_fixes | POST | 200 | — |
| `doctors:catalogs` | doctors | GET, POST | 200, 401, 403, 405 | — |
| `doctors:create` | audit_corrections, doctors, release_fixes | POST | 201, 403, 409, 422 | — |
| `doctors:get` | doctors, release_fixes | GET | 200 | — |
| `doctors:list` | doctors | GET | 200, 401, 403 | — |
| `doctors:restore` | doctors | POST | 200 | — |
| `doctors:update` | audit_corrections, doctors, release_fixes | POST | 200, 409 | — |
| `documents:list` | documents | GET | 200, 401, 422 | — |
| `documents:get` | audit_corrections, documents, patients | GET | 200, 404 | — |
| `documents:create-token` | audit_corrections, documents, patients | GET | 200 | — |
| `documents:create` | documents | GET | 405 | `tests/documents_integration.py:54` |
| `documents:update` | documents | POST | 200, 403, 404, 409 | — |
| `documents:replace-file` | Separate helper | — | — | `tests/documents_integration.py:184`, `tests/document_links_integration.py:130` |
| `documents:archive` | documents | POST | 200, 404 | — |
| `documents:restore` | documents | POST | 200 | — |
| `documents:bulk-archive` | documents | POST | 200, 422 | — |
| `documents:content` | documents | GET | 422 | `tests/documents_integration.py:68` |
| `documents:patient-link` | Separate helper | — | — | `tests/document_links_integration.py:62`, `tests/document_links_integration.py:98` |
| `documents:public-file` | Separate helper | — | — | `tests/document_links_integration.py:67` |
| `patients:archive` | patients | POST | 200, 403, 409 | — |
| `patients:catalogs` | doctors, patients | GET | 200, 401 | — |
| `patients:create` | doctors, patients | POST | 201, 403, 409, 422, 503 | — |
| `patients:get` | doctors, patients | GET | 200, 401, 403, 404 | — |
| `patients:list` | patients | GET, POST | 200, 403, 405, 422 | — |
| `patients:restore` | patients | POST | 200, 409 | — |
| `patients:update` | patients | POST | 200, 409, 422 | — |
| `personnel:archive` | personnel, release_fixes | POST | 200, 409 | — |
| `personnel:create` | audit_corrections, personnel, release_fixes | POST | 201, 403, 409, 422 | — |
| `personnel:get` | personnel, release_fixes | GET | 200 | — |
| `personnel:list` | personnel | GET | 200, 401, 403 | — |
| `personnel:restore` | personnel | POST | 200 | — |
| `personnel:update` | audit_corrections, personnel, release_fixes | POST | 200, 409 | — |
| `routes:archive` | routes | POST | 200 | — |
| `routes:create` | routes | POST | 201, 403, 409, 422 | — |
| `routes:list` | routes | GET | 200, 401, 403 | — |
| `routes:restore` | routes | POST | 200 | — |
| `routes:update` | routes | POST | 200, 409 | — |
| `settings:diagnostics` | settings | POST | 200, 403 | — |
| `settings:get` | auth, settings, theme | GET | 200, 401 | — |
| `settings:notifications-read` | settings | POST | 200 | — |
| `settings:notifications` | settings | GET | 200 | — |
| `settings:photo-delete` | settings | POST | 200 | — |
| `settings:photo` | Separate helper | — | — | `tests/settings_integration.py:48`, `tests/settings_integration.py:64` |
| `settings:preferences-update` | i18n, settings, theme | POST | 200, 401, 403, 409, 422 | — |
| `settings:profile-update` | audit_corrections, i18n, release_fixes, settings | POST | 200, 409, 422 | — |
| `surveys:answers` | surveys | GET | 200, 404 | — |
| `surveys:create` | surveys | POST | 201, 403, 422 | — |
| `surveys:get` | surveys | GET | 200, 404 | — |
| `surveys:link-update` | surveys | POST | 200 | — |
| `surveys:links` | surveys | GET | 200, 404 | — |
| `surveys:list` | surveys | GET | 200, 401 | — |
| `surveys:transition` | surveys | POST | 200, 422 | — |
| `surveys:update` | surveys | POST | 200, 409 | — |
| `surveys:public-get` | surveys | POST | 200, 401, 404, 409 | — |
| `surveys:public-submit` | surveys | POST | 201, 403, 404, 409, 422 | — |
| `tracking:snapshot` | transfers | GET | 200, 401 | — |
| `transfers:catalogs` | ambulances, audit_corrections, destinations, patients, personnel, routes, transfers | GET | 200, 403 | — |
| `transfers:create-token` | ambulances, audit_corrections, destinations, patients, personnel, routes, transfers | GET | 200 | — |
| `transfers:create` | ambulances, audit_corrections, destinations, patients, personnel, routes, transfers | POST | 201, 403, 409, 422, 503 | — |
| `transfers:draft` | routes, transfers | GET, POST | 200, 409 | — |
| `transfers:get` | ambulances, destinations, personnel, routes, transfers | GET | 200, 404 | — |
| `transfers:list` | transfers | GET, POST | 200, 403, 405, 422 | — |
| `transfers:patients` | patients, transfers | POST | 200, 403 | — |
| `transfers:status` | ambulances, destinations, patients, personnel, routes, transfers | POST | 200, 409, 422 | — |
| `transfers:update` | audit_corrections, destinations, routes, transfers | POST | 200, 409 | — |
| `translations:(none)` | i18n | GET, POST | 200, 400, 405 | — |

## Negative-case and role review

The JSON report retains role contexts, error codes and top-level envelope/data-key shapes for every sampled action. These rows had no rejection in the shared-Client sample (some have separate helper evidence):

`accounts:reset-password`, `ambulances:restore`, `auth:csrf`, `auth:logout`, `auth:reset-password`, `auth:reset-request`, `auth:sessions`, `catalogs:document-categories`, `destinations:archive`, `destinations:get`, `destinations:restore`, `doctors:archive`, `doctors:get`, `doctors:restore`, `documents:create-token`, `documents:replace-file`, `documents:restore`, `documents:patient-link`, `documents:public-file`, `personnel:get`, `personnel:restore`, `routes:archive`, `routes:restore`, `settings:notifications-read`, `settings:notifications`, `settings:photo-delete`, `settings:photo`, `surveys:link-update`, `transfers:create-token`.

Before consolidating dispatchers or transactions, add targeted unauthorized/wrong-role, CSRF, method, malformed/extra-field, stale-version and ownership cases where the current shared checks do not cover that behavior. Preserve existing error priority: catalogs authenticate before patient-search CSRF, while several domain dispatchers check CSRF before authentication. Binary HEAD/range cases, two-method photo/draft actions, public survey participation and nested account/survey commands need their own variants.

The one baseline signature variation is the existing concurrent heartbeat/logout check. Its allowed outcomes are 200/401/403; an exact snapshot of whichever response wins would create a flaky gate. This observation does not weaken deterministic Ambulances comparisons.
