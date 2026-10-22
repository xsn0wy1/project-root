# Review cleanup — Checkpoint 1

Complete; paused for review. This is the first checkpoint of the revised four-checkpoint plan. Throttling/mail changes and the later readability/style work have not started.

## Changes

Removed the legacy “Today’s appointments” card from Patients. The three remaining metrics use three desktop columns; accepted container/phone overrides remain. Removing the fourth card can shorten a summary row and move following content upward. The stylesheet URL is versioned for the change. The appointment table, historical migrations, `appointments_today` API field/calculation and appointment-based archive guard are preserved. No appointment-management workflow was added.

Removed **37 keys from each dictionary**: 35 unused keys after complete-key and dynamic-consumer review, plus the two card-only labels. Both dictionaries now contain **1,816 keys**, and all retained values are identical. The four dynamically constructed account-role labels, document-category family and appointment archive-error translation remain. The reviewed inventory is `review-cleanup1-keys.json`; it supersedes the external report’s unsupported count of 46 dead keys.

Only four first-party application files change: the Patients view/stylesheet and the Spanish/English dictionaries. All backend/API/SQL, JavaScript, artwork and other application files retain their bytes. No schema migration, original private-data/configuration change, global Apache change or submission archive was made.

## Automated gates

Added `tests/source_equivalence.py`, reusing the existing native PHP tokenizer, plus a small Acorn AST fingerprint utility. The parser is pinned to **Acorn 8.15.0** in development-only test package/lock files; `tests/node_modules/` is ignored. The website has no new runtime dependency or build step. This host lacks npm, so the published package was extracted into the ignored test dependency directory only after verifying its SHA-512 against the committed lock. Other environments can use the documented `npm ci --prefix tests --ignore-scripts` command.

The untouched baseline passes **90 PHP token streams and 35 JS ASTs** before application edits. The completed batch passes with exactly four hash-bound intentional-file declarations in `review-cleanup1-changes.json`; **89 PHP streams and all 35 JS ASTs remain equal**. The remaining PHP difference is the approved template card removal/cache version. The other **262 application files** keep their hashes.

Self-checks reject SQL/inline-HTML changes, JavaScript ASI return changes, removed directives, changed regexes/template text and invalid syntax, while accepting trivia changes and BigInt literals. Separate negative checks verify that a missing parser fails and strict formatting mode rejects precisely the four intentional application edits. No broad formatting exception was added.

## Verification

- **93 focused functional checks pass before; 94 pass after**: Patients 66 → 67 and localization 27 → 27. The added check verifies three rendered cards. Existing tests prove Montevideo appointment-day boundaries, appointment archive protection, persistence, permissions, conflicts and rollback. All four disposable suite schemas and eight account entries were removed and verified absent.
- All **seven Patients actions retain 27 observed outcome signatures**. One other-domain observation differs because the existing archive/transfer race legitimately permits either archive-first or booking-first; both runs pass its invariant. This is documented rather than reported as full cross-domain signature equality.
- Browser verification passes **340 baseline / 330 final checks**, ten page/theme/viewport views and seven interaction groups. The ten fewer checks correspond to the removed card’s value assertion in each matrix view. Summary checks cover both languages in each theme at 1440×900, 390×844, 320×640, 768×1024 and 844×390. Navigation, new/edit/detail dialogs, keyboard dropdowns, clinical controls, failed-save input retention and archive cancellation remain covered. There are no unhandled script errors, missing local assets or Google Fonts requests.
- **50 matched screenshot pairs** were compared without masks. Desktop, phone, narrow and landscape captures were inspected. The maximum unmasked difference is **24.840%**, including the intentionally removed card and resulting movement of following content; these are not zero-difference comparisons. No unrelated visual change was found.
- Translation/DOM coverage passes **1,816 bilingual keys, 25 page bindings/assets and 1,111 literal references**. One fresh Patients render supplements 24 unchanged accepted renders. Only the removed article is excepted from Spanish fallback comparison; all other text, attributes and IDs remain covered. Removed-key checks reject reintroduced keys/references, and browser checks exercise dynamic role/category translations in both languages.
- Syntax passes for **90 PHP, 35 application JS, 13 test CJS and 29 Python files**. Preflight is healthy on PHP 8.0.28/MariaDB 10.4.28. Test port 8113 is closed; the demonstration on 8091 responds normally.

The full core suite, a new full 25-page browser matrix, fresh installation and standalone recovery/document-link suites were not repeated for this bounded cleanup. Those remain scheduled for the appropriate later checkpoints. Preflight still has the previously identified mail-worker capability gaps, scheduled for Checkpoint 2; its current healthy result does not verify those capabilities.

## Measurements and next checkpoint

First-party application files remain **266**. Code/dictionaries shrink **2,216,413 → 2,210,429 bytes**, removing **5,984 bytes**. Total first-party bytes shrink **12,505,673 → 12,499,689**. Repeated overlapping eight-line groups fall **501 → 497**, extra occurrences **528 → 524**; formatting-neutral PHP duplication is unchanged. Vendor/generated files and development tools/tests/docs are excluded from these application figures. No runtime-performance improvement is claimed.

Detailed results and measurements are in `review-cleanup1.json` and `review-cleanup1-source-metrics.json`. Source baselines, fictional credentials, contracts, captures and logs remain ignored under `tests/.runtime/review-cleanup/checkpoint1/`.

After review, Checkpoint 2 implements atomic precharge/refund throttling without advisory locks across verification, separates the Accounts operation variable, and hardens the retained mail worker’s executable/configuration/preflight checks. Checkpoints 3–4 cover readability/natural function splits and justified style consolidation/final installation checks. No later checkpoint starts automatically.
