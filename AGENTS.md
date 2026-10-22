# Hospital simplification project

Build only in `/Users/snowy/Downloads/project-root`. The original `/Users/snowy/Downloads/johnproject` is a read-only code, appearance and behavior reference. `/Users/snowy/Downloads/johnproject 2` is a directory-layout guide only: never read or copy its files. Historical instructions under `docs/archive/` describe the original project and do not govern this workspace.

Use `docs/refactor-handoff.md` for context and `docs/progress.md` for current status. The user's current instructions take precedence. Pause after each completed stage with concise verification results for review.

Preserve all implemented features and the exact design. Spanish is the default language; English and both themes remain available. Retain input on failure and report errors to consoles only. Accounts belong to staff; patient/doctor records do not automatically grant login access. Reports, calendars and appointment management are cancelled/out of scope. Tracking is a shared fictional server simulation, never real GPS.

Latest user clarification (Stage 3): all website dropdowns must use the existing Documents dropdown design, including the new-patient form. This intentional styling change supersedes exact visual preservation for dropdown controls only. Preserve field dimensions, values, validation, disabled states, localization, keyboard behavior and surrounding layout while consolidating matching dropdown code.

Latest accepted UI checkpoint: phone layouts use the shared overlay hamburger drawer and responsive styles at 770px, with narrow-phone and touch-landscape adjustments. The user authorized responsive reflow and the listed specialty, Settings profile/switch, map search, duplicate-control and survey-action repairs. Preserve the desktop design and 100px staff header; these accepted mobile changes supersede the original mobile exceptions.

Latest user clarification: modernize the Personnel and Destinations detail dialogs to the current management design. Preserve their information, permissions and edit/archive behavior. This intentional styling change supersedes exact visual preservation for those two detail dialogs only.

Stage 6 is authorized with an audit focus: consolidate remaining code/styles/files where justified, verify workflows, installation and integrity, and fix concrete issues or inefficiencies. Keep the submission archive separate until requested. Preserve the accepted UI and pause with verification results when this audit is complete.

Target plain PHP includes, procedural domain modules, and small shared JS helpers. No framework, ORM, dependency container or build system. Keep public pages/assets in `public/`, API entry points in `backend/api/v1/`, private PHP in `backend/php/`, shared browser infrastructure in `public/src/api-connection/`, and dictionaries in `db/json/`. Existing classes remain temporarily until their scheduled migration; do not maintain duplicate implementations.

Preserve `/api/v1/`, field names, JSON envelopes, HTTP statuses, multipart and binary semantics unless updating every consumer together. Preserve permission checks, transaction/conflict behavior, archive history, immutable document bytes/versions, patient QR expiry/revocation and survey rules. Preserve CSS classes, DOM hooks, image bytes, the 100px desktop staff header and the accepted responsive behavior.

Use the isolated database/private configuration created for this workspace. Never copy or modify the original configuration, key, database or uploads, and never change global XAMPP/Apache settings. Credentials, fixtures with tokens, screenshots, runtime logs and reference snapshots belong in ignored private runtime paths. Only fictional demonstration data is allowed.

Adapt existing focused checks; do not add a parallel test framework. At each stage compare affected pages in both themes and desktop/mobile, run relevant functional checks, and record first-party file/byte and duplication measurements excluding vendor/generated artifacts. Useful tests stay in source control; submission packaging later excludes development artifacts and secrets.
