# Presentation demo data — 2026-10-25

Complete; paused for review. The only new application-tree file is the explicitly run `backend/php/cli/demo-data.php`. All 268 existing application files, including every shared module, API, schema, template, script, style and asset, retain their exact bytes. Setup and migration do not invoke the seeder. No new application dependency or build step was introduced.

The presentation database contains the retained main admin, one dispatcher, one records manager, 15 patients, 8 doctors, 6 drivers, 4 copilots, 7 ambulances, 6 destinations, 6 routes, 12 transfers, 6 PDF documents, and 3 surveys. Fleet status is 5 available / 1 maintenance / 1 out of service. Transfers are 6 completed / 2 cancelled / 2 in progress / 2 scheduled, spanning 29 days ago through 3 days ahead. Published surveys have 32 and 36 synthetic responses; the third survey remains a draft.

All people, CIs, professional registrations, accounts, contacts and reports are invented. The records manager shares the identity of one of the four fictional copilot profiles. Normal personnel saves create that association, allowing assigned transfers to appear without broadening the existing account permissions. Doctor and patient profiles do not create login accounts.

## Reset and normal validation

`--reset` asks for `SI` unless `--yes` is supplied. The script refuses unmarked/production database names, remote database hosts, disabled mapping, unavailable routing and an active simulation worker. All six paths are checked live before deletion; failed preflight rolls back its cache writes and leaves existing records/files intact. SQL reset uses foreign keys normally, with no disabled constraints or schema migration. Stored documents and the old profile image are moved aside and restored if the deletion transaction fails, then removed after it commits. Reference roles/document categories and the admin login are retained.

Accounts use registration, approval and profile domain functions; directories, routes, transfers and surveys use their existing domain saves. PDFs pass the unchanged upload API, including a real multipart upload, file checks and immutable storage. A temporary loopback PHP server is stopped afterward. Historical transfers pass create/start/arrive/complete or create/cancel validation at an accepted current time; only their generated chronology is then backdated. The application date and transition rules remain unchanged.

Passwords are generated afresh on each reset, written with mode `0600` under the ignored `tests/.runtime/demo-data/<database>/credentials.json`, and never printed in command or upload-server logs. The main admin password is unchanged. The README documents the command and the existing simulation worker.

## Verification

- Final CLI reset/seeding ran twice consecutively on the presentation database and twice in a disposable database; counts, relationships and stored-file checks pass. Old placeholder titles/names and prior stored files are gone.
- Thirteen focused checks cover confirmation, explicit reset, database/host guards, disabled/unreachable mapping, worker locking, side-effect-free helper import, repeatability, private passwords and generated PDF bytes. Both positive disposable runs used an unreachable geocoder, proving the fixed facility coordinates suffice.
- Every route and transfer has more than 20 points; the minimum is 91. Distances/durations are the live router's estimates, passed through normal route saves.
- Each environment passes 504 browser cases: 21 staff views × 3 roles × 2 languages × 2 themes × desktop/phone. There are 432 successful authorized page responses and 72 expected role-denied 403 pages per sweep. Seeded document/survey content, saved language/theme, runtime/API/asset errors and unexpected error popups are checked. Saved preferences are restored afterward. The existing optional-diagnostics 429 exception is classified separately.
- The existing simulation worker moved both active vehicles along their routed paths in the disposable database; the worker was stopped. The presentation worker remains explicitly started by the presenter.
- All six small PDF reports parse and render as readable one-page documents, with a clear fiction disclaimer and no photos. Normal storage preflight is healthy: no missing/mismatched files or missing migrations.
- Source equivalence passes 92 PHP token streams / 35 JS ASTs, with one exact declared addition and 268 byte-identical existing files. Translation coverage passes 1,827 bilingual keys and all 26 unchanged page templates/bindings/assets. Syntax and whitespace checks pass.

Private evidence lives under `tests/.runtime/demo-data-verification/`; generated credentials remain in the database-specific private directory. Disposable database/accounts/servers and temporary browser credential fixtures are removed after verification. Measurements and the exact addition declaration are adjacent in `demo-data-metrics.json` and `demo-data-equivalence.json`: +1 application file / +46,448 bytes, no page or repeated-eight-line-block increase. The earlier readability final regression remains a separate checkpoint.

## Facility sources

Addresses were checked against the linked official facility/government pages. Stored WGS84 coordinates are centres derived from the corresponding OpenStreetMap hospital feature bounds, rather than runtime geocoding. OpenStreetMap data is [© OpenStreetMap contributors, ODbL](https://www.openstreetmap.org/copyright).

| Facility | Official address | Latitude, longitude | Primary geometry |
| --- | --- | --- | --- |
| Hospital de Clínicas | [Avenida Italia 2870](https://www.cposgrados.fmed.edu.uy/node/330) | -34.8915346, -56.1521338 | [OSM way 37514653](https://api.openstreetmap.org/api/0.6/way/37514653/full.json) |
| Hospital Maciel | [25 de Mayo 174](https://www.gub.uy/tramites/servicio-medicina-paliativa-hospital-maciel) | -34.9083467, -56.2118677 | [OSM way 905392238](https://api.openstreetmap.org/api/0.6/way/905392238/full.json) |
| Hospital Pasteur | [Larravide 2458](https://www.asse.com.uy/contenido/Hospital-Pasteur-5213) | -34.8750852, -56.1384209 | [OSM way 180467229](https://api.openstreetmap.org/api/0.6/way/180467229/full.json) |
| Hospital Pereira Rossell | [Bulevar Artigas 1550](https://pereirarossell.gub.uy/) | -34.8986503, -56.1628384 | [OSM way 156270380](https://api.openstreetmap.org/api/0.6/way/156270380/full.json) |
| Hospital Español | [Avenida Garibaldi 1729](https://www.descubrimontevideo.uy/hospital-espanol) | -34.8750288, -56.1814031 | [OSM way 251822831](https://api.openstreetmap.org/api/0.6/way/251822831/full.json) |
| Centro Hospitalario Saint Bois | [Camino Fauquet 6358](https://www.descubrimontevideo.uy/hospital-saint-bois) | -34.7882944, -56.2411702 | [OSM way 270163664](https://api.openstreetmap.org/api/0.6/way/270163664/full.json) |
