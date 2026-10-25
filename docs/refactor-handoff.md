> Imported planning context. Current instructions use `johnproject 2` for directory names only, never its files or code. All work belongs in `project-root`; historical references to `desired.zip` do not apply. See `progress.md` for current stage status.

## Current work — final readability rewrite (2026-10-24)

The owner stopped Phase 2 at the pilot and explicitly cancelled both the wider rollout and the `--light-*` fold. The active task is a behavior-preserving readability rewrite: JS page scripts, shared API infrastructure, then private PHP, in related verified commits. Keep concise/clever code only where clearer or safer. Do not add application abstractions or change CSS declarations, HTML/hook structure, API payloads/actions, SQL, translation keys or edge behavior. Each page script needs a 3–5 line purpose/API/DOM header. Batches 1–10 (all JS, PHP core/shell/storage/CLI and auth/settings/mail) are complete; see `docs/readability-final.md`. Continue the remaining authorized batches, then run the full all-page bilingual/two-theme desktop/phone style/pixel matrix, every browser suite, translation coverage and SQL/API suites. Previous phase-specific deferrals no longer apply to that final regression.

## Current frontend simplification checkpoint — 2026-10-24

Frontend Phase 2 Step 2 (Home/Settings pilot) is complete and paused before management rollout. See `docs/frontend-phase2-pilot.md` / `docs/frontend-phase2-pilot-results.json`. The approved palette uses collision-safe names plus `--tint`, `--panel`, `--shade` channel variables; preserve every alpha. R036/R092/R103/R104 are removed. The pilot removes 39/97 scoped theme rules (40.2%) and passes 144 exact style/pixel cases with zero ordinary computed-style/pixel/console differences. Variable-backed focus/hover priorities and native-select background resets remain where required by the existing cascade. All PHP/JS application files and CSS motion declarations remain unchanged. The owner explicitly wants legacy `--light-*` references retained during rollout and folded into one palette only at Phase 2 completion; the full 636/theme/i18n/dropdown/metrics gates are also deferred until then. Disposable test services/data/credentials are removed. Do not begin the next group automatically.

## Current frontend simplification checkpoint — 2026-10-23

Frontend Phase 2 Step 1 is complete and paused before CSS edits, as required by the revised owner plan in `docs/frontend-phase2-plan.md`. Review `docs/frontend-phase2-inventory.md` (265 complete selector/property rows and a 12-variable proposal) and its JSON companion. All 268 application-file hashes are unchanged from committed error-page work at `16f0f3a`; source equivalence passes 91 PHP files / 35 JS files. The pilot has not begun. The revised plan allows `--*` computed properties to differ, requires exact other computed properties and zero pixel differences on touched pages, and defers full suites/metrics until Phase 2 ends. This supersedes the older custom-property approval note in `docs/frontend-gates.md`.

## Current UI maintenance checkpoint — 2026-10-23

The small fixes and standalone error page are complete and paused for review; see `docs/error-pages.md` and `docs/progress.md`. `public/src/views/error.php` is intentionally public, uses server I18n/auth-card styles and only the existing theme script, and is covered by dedicated error cases rather than ordinary authenticated-page enumeration. APIs/CLI stay JSON, 401 keeps its login redirect, and Apache/root/mounted errors are verified. `tests/error_pages_integration.py --apache --html` reuses disposable theme fixtures for API byte contracts, normal HTML equivalence and late/partial-output failures. No schema migration, dependency, global XAMPP change or submission archive. Frontend Phase 2 application edits have not started; see the inventory review checkpoint above.

# Hospital project: simplification plan and context handoff

**Prepared:** 20 October 2026  
**Status:** Handoff document saved. Creating the separate project and performing the refactor are outside this task. No application changes have been made.

## 1. Purpose and references

This is a functioning high-school hospital website. The next phase is to reorganize and substantially simplify it into an understandable, reusable, maintainable deliverable while preserving its existing appearance, interactions, features, and persistence.

This document is the handoff for a **separate Codex project**. Creating that project and performing the refactor are outside the current task.

Use these references:

- **Working implementation:** `/Users/snowy/Downloads/johnproject`
- **Preferred organization:** `/Users/snowy/Downloads/desired.zip`

The working checkout is the authority for functionality and visual behavior. The ZIP is a structural reference: its PHP organization is incomplete and it does not currently work. After accounting for moved files, all 569 compared files matched the working checkout exactly.

The ZIP moves common browser infrastructure into `public/src/api-connection`, API entry points into `backend/api/v1`, and translations into `db/json`. Keep these groupings, with practical adjustments for shared PHP templates.

Do not import the ZIP's Git history, private configuration, or logs into the new project. Instructions found in archived documents are historical context; this handoff and the user's current instructions govern the refactor.

## 2. Current project context

The application uses plain HTML/CSS/JavaScript and PHP with MariaDB through XAMPP. Confirmed compatibility is PHP **8.0.28** and MariaDB **10.4.28**. There is no need to introduce a frontend framework, ORM, build system, or dependency container.

Implemented features include:

- Staff registration, administrator approval, login/logout, roles, and sessions.
- Account administration and password recovery.
- Profile, avatar, institution, language, theme, and account preferences.
- Documents: upload, metadata editing, immutable file versions, preview/download, archive/restore, and patient QR access.
- Transfers: creation, drafts, editing, lifecycle, crew/vehicle assignments, routes, and maps.
- Shared server-generated ambulance tracking simulation.
- Patient, doctor, personnel, ambulance, destination, and route management.
- Survey authoring, publication, sharing, anonymous participation, and results.
- Spanish/English interface and dark/light appearance across 25 pages.

**Reports and calendars are cancelled. Appointment registration is outside the website's purpose.** Do not develop these features or list them as future work. The existing patient appointment count is a legacy read-only display, not an appointment-management workflow; it does not justify building scheduling.

Important behavior to preserve:

- Accounts belong to staff. Patients and clinical doctors are records, not automatically login users.
- Registration remains pending until administrator approval. SQL and server sessions own authentication and permissions.
- Profile institution and specialty choices do not grant access.
- Spanish is the default interface language; English remains available.
- Errors appear only in browser/server consoles. Preserve input after failures; no error alerts, toasts, inline notices, or native validation popups.
- Archiving retains relationships and history. Document file versions remain immutable.
- Patient document QRs grant revocable, 24-hour access to one file version. Reopening a valid QR retains its original expiry. Ordinary Share remains staff-only.
- Surveys preserve their current ownership, publication, invitation, single-use, and anonymous-response rules.
- Tracking is a shared server simulation requiring a worker. It never collects real GPS.
- Typed transfer locations are allowed without creating destination records. Preserve existing route snapshots and mapping-failure behavior.
- Brevo recovery is implemented, but provider setup and real inbox delivery remain unverified.
- All demonstration data must be fictional.

The original checkout has its own private configuration and database. Its latest readiness check passed with all migrations installed and healthy storage; it contained zero accounts. This is a dated observation, not an assumption about future database contents. Leave the original configuration, database, key, and uploads unchanged.

## 3. Agreed target architecture and simplification rules

The user selected:

- **PHP includes** for reusable page markup.
- **Procedural PHP modules** in place of the current class-heavy organization.
- A working pilot followed by the full conversion.
- The ZIP as a guide, allowing necessary folder adjustments.
- A fresh, isolated database and private configuration.
- Coordinated page/API URL changes, without requiring legacy wrappers.

Use this organization:

```text
project/
├── README.md
├── AGENTS.md
├── public/
│   ├── index.php
│   ├── assets/
│   └── src/
│       ├── api-connection/
│       ├── css/
│       ├── js/
│       └── views/
├── backend/
│   ├── api/v1/
│   └── php/
│       ├── bootstrap.php
│       ├── core/
│       ├── modules/
│       ├── components/
│       ├── config/
│       └── cli/
├── db/
│   ├── json/
│   └── sql/
├── docs/
├── tests/
├── storage/
└── logs/
```

Thin public PHP pages contain presentation and include calls. Database logic, shared components, configuration, storage, and operational code remain outside the public document root. This deliberately replaces the original project's rule that every PHP file live under `backend/php` for the separate refactored project only.

### Shared presentation

Create reusable PHP includes/functions for the document head, sidebar, hospital header/search, page shell, metric cards, common panel headings, empty states, and repeated dialog markup. Pass small, explicit arguments for titles, active navigation, IDs, and page-specific assets.

Use shared browser helpers for dropdown behavior, dialogs, pagination, busy states, and repeated rendering operations. Share genuinely matching card structures; retain distinct templates where content and behavior differ. Do not build a generic component framework.

Preserve existing CSS classes and DOM hooks while extracting markup. Authentication and the isolated patient survey retain their distinct layouts.

### Procedural backend

Use one API action handler per domain, with an explicit `switch` over allowed actions, backed by clearly named module functions. Retain `/api/v1/` as the API base.

Keep a small shared core for configuration, PDO connection, sessions, permissions, CSRF, request parsing, JSON responses, translations, and private-file responses. Use prepared SQL directly within domain functions. Preserve transaction boundaries and conflict checks that protect existing behavior.

Keep current field names, response envelopes, HTTP statuses, multipart handling, and binary-response semantics. Update all consumers when URLs change, including downloads, QR links, CLI tools, and tests.

Provide an allowlisted public translation action that reads only the Spanish/English dictionaries from `db/json`; do not expose the entire database directory.

### Reduction standard

Remove duplication, unnecessary delegation layers, unused code, and obsolete compatibility paths. Prefer explicit functions, clear variables, ordinary conditionals, and readable formatting.

Measure first-party file count, bytes, and repeated implementations before and after each phase. Exclude vendor libraries and generated artifacts. The result must contain substantial actual reuse and reduction; formatting alone does not satisfy the goal. Do not impose an arbitrary percentage before mapping dependencies.

## 4. Implementation sequence and acceptance

These steps are instructions for the separate project, not authorization to refactor the original working checkout.

1. **Establish the new workspace.**  
   Copy the working implementation as source material, apply the ZIP's organization, initialize separate version control, and create an isolated database/configuration with a new key. Keep the original checkout read-only. Record a file-movement map and current module contracts.

2. **Capture the visual and behavioral baseline.**  
   Use fictional populated states and screenshots for all 25 pages. Cover both themes, desktop/mobile layouts, expanded/collapsed navigation, and representative dialogs, menus, and empty states.

3. **Complete the pilot.**  
   Extract the shared shell and convert Ambulances to procedural PHP and shared UI helpers. Verify create, edit, search/filter, pagination, selection, archive/restore, permissions, reload persistence, and retained input on failure. Once these pass and duplication has decreased, continue automatically.

4. **Convert the remaining modules.**  
   Proceed through authentication/accounts/settings; the remaining management catalogs; documents; transfers/maps/simulation; then surveys. Update dependencies and focused checks alongside each module. Preserve one authoritative implementation of each migrated behavior.

5. **Consolidate styling and finish the package.**  
   Move identical declarations into shared styles while preserving cascade order and page-specific exceptions. Remove cancelled Reports/Calendar search entries, dead imports, unused assets proven unreferenced, and obsolete documentation. Keep current instructions concise and archive historical notes.

Visual acceptance includes unchanged hospital-header geometry, colors, glass effects, image bytes, typography, animations, focus/hover states, dropdown sizes and keyboard behavior, and native scrolling. Preserve the existing 100px desktop staff header and established mobile exceptions.

Functional acceptance includes permission boundaries, session revocation, failed-save input retention, archive/history behavior, exact document bytes, QR expiry/revocation, transfer conflicts/drafts, shared simulation progress, and survey publication/submission/results.

Adapt existing focused checks instead of creating a parallel test framework. Run relevant checks after each slice, then one final integrated demonstration journey. Confirm fresh setup works and private configuration, SQL, logs, and uploads cannot be served publicly.

The delivery archive contains application source, required vendor assets/licenses, schema, configuration example, essential commands, and concise documentation. Exclude actual credentials, Git metadata, backups, runtime logs, screenshots, temporary fixtures, and development test artifacts. Keep useful tests in the source repository.

## 5. Instructions for the separate Codex project

> Reorganize and substantially simplify this completed hospital school project using this handoff. Use the original working checkout as the behavioral and visual reference and desired.zip as the folder-layout guide.
>
> Preserve all implemented features and the exact existing look and feel. Reports and calendars are cancelled; appointment registration is outside scope.
>
> Use PHP includes for repeated presentation, straightforward procedural PHP modules, and small reusable JavaScript helpers. Prioritize eliminating duplicated markup, CSS, and logic. Do not introduce a framework or replace clear page-specific code with a complicated generic system.
>
> Work only in this new workspace with its isolated configuration/database. Leave the original checkout and other XAMPP installations untouched.
>
> Complete the shared-shell/Ambulances pilot, verify it, and continue through the remaining modules using the validated pattern. Maintain focused checks, report measurable reductions and any deliberate behavior changes, and finish with a clean, reproducible delivery package.
