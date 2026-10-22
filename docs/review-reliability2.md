# Review reliability — Checkpoint 2

Complete; paused for review. The user verified Checkpoint 1 and authorized this authentication/recovery checkpoint. Readability/style work and submission packaging remain separate.

## Authentication and Accounts

Login atomically reserves both existing failure budgets before credential lookup/verification: **10 attempts per username/source and 60 per IP, each for 15 minutes**. Both use the existing short `FOR UPDATE` transactions in a stable order; a blocked budget rolls back the entire reservation. No new advisory locks or throttle row locks span password verification. Successful login resets only its own source history and refunds one shared-IP charge, preserving other users’ failures and in-flight work.

Current-password checks reserve **5 attempts per user/source in 15 minutes** before verification. Invalid credentials retain their charge. Validation, authorization and infrastructure failures release only their own provisional charges. Refunds match the charged expiry window, cannot decrement zero, and are idempotent within the request. In-flight attempts count temporarily; a large valid burst can receive 429 until earlier successes finish/refund, as explicitly accepted in the plan.

On success, refund/reset SQL runs inside the existing login/password transaction before commit. Only memory is cleared afterward. If cleanup fails, the business transaction rolls back; password hashes, revisions, reset tokens, session revocation and audit writes remain consistent. Administrator resets retain their existing permission/version behavior. The obsolete non-atomic `check()` and broad `clear()` methods have no remaining callers and are removed.

The Accounts dispatcher now uses a separate `$operation` for its body action. Endpoints, body field names, statuses and envelopes remain unchanged.

## Recovery readiness

Retained the detached bounded mail worker and private, atomically published jobs. Added optional `mail_php_binary` / `HOSPITAL_MAIL_PHP_BINARY`: an absolute executable override, including paths containing spaces. Automatic discovery checks the current CLI executable, `PHP_BINDIR` and the binary’s directory. No private configuration file was changed.

A **two-second dry probe** verifies CLI mode, PHP 8+, PDO MySQL/cURL/mbstring and required transport/locking functions. It receives no application credentials, opens no SQL connection and creates/sends nothing. Web readiness also checks process functions and private writable mail storage. Configured failures return uniform `RECOVERY_UNAVAILABLE` 503 before account lookup.

Preflight adds `recovery_email.status` and safe capability booleans: `unconfigured`, `ready` or `unavailable`. Optional unconfigured mail does not itself fail installation health; configured unavailable delivery does. The note identifies the checked user/runtime and does not equate capability readiness with actual inbox delivery. Existing manual draining remains available.

The provider transport, current-token/revision/expiry checks, stale-job rejection, owner-only job permissions and protection against older failed deliveries deleting newer tokens retain their implementations. Provider calls remain outside web workers; environment forwarding remains explicitly allowlisted. Real provider credentials/delivery and actual Windows execution are still unverified.

## Verification

- The **full existing 16-module runner passes 842 checks**, including every catalog, documents, transfers, surveys, migrations, localization and themes. The focused authentication/Settings/release suite passes **140 before / 161 after**; the extra **21** checks cover synchronized real-process admission, source/IP thresholds, blocked credential work, shared-IP refunds, expiry, zero/double refunds, rollback/retry and injected cleanup failures. Failed credentials and policy/validation rejection remain distinct. The password cleanup fault occurs after credential/reset/session/audit writes and proves their complete rollback.
- Recovery passes **58 SQL/API checks** and **130 browser checks**. Fifteen new readiness checks cover automatic discovery, executable paths with spaces, relative/missing/nonexecutable/wrong executables, disabled CLI transport/process functions, bounded probe timeout, partial configuration, unwritable storage and uniform known/unknown failures. A delayed mock provider proves prompt 202 responses and web-worker availability; worker delivery actually uses the spaced executable path. Existing token expiry/replacement/revocation and older-failure protections pass. Totals are **900 core/recovery functional checks**; repeated focused runs are not added to that total.
- Affected login, recovery, Settings and Accounts pages pass **110 browser checks across 16 desktop/phone/theme views and two security interaction groups**. Real password changes and independent session revocation succeed, with failed form input retained and console-only errors. Together with recovery’s 130 checks, **240 final browser checks** pass. No unhandled scripts, missing local assets or Google Fonts requests occur.
- **18 unmasked before/after captures** compare: thirteen are pixel-identical; five have RGB channel differences of at most **3/255**, with none above 12. Exact differing-pixel percentages reach 40.651% on image backgrounds because even tiny channel changes count. Login captures and the phone Settings dialog were inspected; no layout/style change was found. Frontend sources, fonts/artwork, CSS, JS and all page templates retain their bytes.
- Structural comparison passes **90 PHP streams / 35 JS ASTs** with exactly **seven hash-bound intentional application changes**. **83 PHP streams and all 35 JS ASTs remain equal; 259 other application files retain their hashes.** Only `auth_login` and `password_change` change among existing authentication functions; the other 35 and the mail send/job-validation implementations retain their tokens. The scope proof and exact declarations are recorded privately and in `review-reliability2-changes.json`.
- Syntax passes **90 application PHP, 35 application JS, one CLI test PHP, 13 test CJS and 30 Python files**. Translation/DOM coverage passes **1,816 bilingual keys, 25 bindings/assets and 1,111 literal references**, with four fresh affected-page renders plus 21 unchanged accepted renders. No new fallback exception is needed. Baseline observed authentication/Settings/release outcome and top-level envelope/data-key signatures are retained; this is sampled contract evidence, not exhaustive nested-schema coverage.
- Preflight is healthy on PHP 8.0.28/MariaDB 10.4.28; the demonstration reports mail `unconfigured`. Read-only cleanup verifies **22 suite schemas / 44 account entries** absent, zero remaining recovery-test schemas/accounts, removed temporary storage and seven closed test ports. Demonstration 8091 and the existing fictional browser fixture remain available.

One initial recovery diagnostic assumed `php -n` disabled XAMPP’s statically compiled extensions. The test was corrected to disable an actually required CLI transport function; capability probing now covers that case too. The incomplete diagnostic is excluded from passed totals. Private logs/screenshots/credentials remain ignored under `tests/.runtime/review-cleanup/checkpoint2/`.

A new full 25-page browser matrix, standalone patient-link suite and complete fresh-install replay were not run here; they remain scheduled for the final integrity checkpoint. CLI/web capability checks do not replace verification under the actual Apache user/PHP configuration, Windows, physical devices/Safari or a real provider.

## Measurements and continuation

First-party files remain **266**. Code/dictionaries grow **2,210,429 → 2,216,784 bytes** (**6,355 added**) for atomic refund handling and executable/readiness checks. Total first-party bytes become **12,506,044**. Repeated eight-line groups remain **497**. No overall reduction or performance improvement is claimed for this correctness checkpoint. Vendor/generated files and development tests/tools/docs are excluded; source metrics include formatting-neutral PHP counts separately.

No schema migration, original private configuration/data change, global Apache change or submission archive was made. Details are in `review-reliability2.json` and `review-reliability2-source-metrics.json`.

After review, Checkpoint 3 covers the agreed domain formatting batches, native PHP/JS AST gates, natural function splits around a soft 60-line target, and separately verified ternary replacements. Checkpoint 4 covers proven style consolidation and final installation/integrity verification. Neither begins automatically.
