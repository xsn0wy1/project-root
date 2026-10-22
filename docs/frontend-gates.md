# Frontend simplification gates

The accepted plan is in [frontend-simplification-plan.md](frontend-simplification-plan.md). These are development checks, using the existing Playwright/Chrome, pngjs and locked Acorn tools. The application has no new dependency or build step. Raw HTML, source snapshots, credentials, tokens, screenshots and runtime logs stay under ignored `tests/.runtime/`.

## Computed styles and pixels (WP-0.1)

`tests/refactor_baseline.cjs` retains the existing representative navigation, dialog, menu and dropdown interactions. With `BASELINE_COMPUTED_STYLES=1`, its default matrix is all 25 PHP pages × dark/light × Spanish/English × 1440×900, 390×844 and 320×640. The complete matrix has 300 page views and 336 open-state comparisons.

`tests/computed_styles.cjs` records every top-document element, including hidden elements, and generated `::before`/`::after` pseudo-elements. It enumerates all computed properties, including inherited custom properties, sorts them and creates a SHA-256 fingerprint per element/pseudo-element. Identity uses DOM position and tag, so class-name spelling is not part of the identity. Added/removed elements and any changed property fail the gate. Only `animation-*`, `transition-*` and their WebKit equivalents are omitted. Values and colors are never rounded or otherwise normalized. Compressed JSON snapshots retain the complete property/value dictionaries, not just hashes.

The two source copies use the same browser origin: the reference context proxies requests to its private reference server. Real generated URLs and QR data therefore match. The visual clock is fixed; ordinary timers remain active. Decorative CSS motion is frozen for comparison. Captures wait for API requests, fonts and visible image decoding. Vendor map tiles receive a deterministic transparent image; map controls, routes, markers and all their computed styles remain compared. The existing vendor-tile pixel exclusion remains available. No application dropdown/control mask is allowed in this mode.

Acceptance requires **zero computed-style differences** and a maximum pixel difference of **0.041%**, using the existing channel threshold of 12/255. Missing local assets and unhandled script errors fail. Console output is compared against the reference, retaining message/status/count differences while normalizing random server request IDs. Expected anonymous 401 logging is not called a new regression.

### Reproducing a comparison

Use the existing bundled Node/Playwright environment and install only the locked development parser if needed (`npm ci --prefix tests --ignore-scripts`). Run from the repository root. Start with a clean, accepted application baseline; the Phase 0 application baseline is `f0f1c7f`.

```sh
mkdir -p tests/.runtime/frontend/next/reference
git archive f0f1c7f public backend db | tar -x -C tests/.runtime/frontend/next/reference

export HOSPITAL_THEME_TEST_DIR="$PWD/tests/.runtime/frontend/next/fixture"
export HOSPITAL_THEME_TEST_PORT=8879
python3 tests/theme_environment.py prepare

export BASELINE_ENV_DIR="$HOSPITAL_THEME_TEST_DIR"
export BASELINE_REFERENCE_DIR="$PWD/tests/.runtime/frontend/next/reference"
export BASELINE_SERVER_NAMES=reference
export BASELINE_REFERENCE_PORT=8114
python3 tests/baseline_environment.py start

export BASELINE_COMPUTED_STYLES=1
export BASELINE_CURRENT_URL=http://127.0.0.1:8879
export BASELINE_REFERENCE_PROXY_URL=http://127.0.0.1:8114
export BASELINE_OUTPUT_DIR="$PWD/tests/.runtime/frontend/next/visual"
node tests/refactor_baseline.cjs

python3 tests/baseline_environment.py stop
python3 tests/theme_environment.py cleanup
```

`BASELINE_FILES` selects page suffixes; `BASELINE_SIZES` and `BASELINE_LOCALES` select matrix dimensions. The default strict matrix covers both languages in each theme. `--resume` is for interrupted captures of the **same unchanged source copies** and matrix; it reruns incomplete or failed visual pages. Start a new output directory after source changes. `BASELINE_TRACE_PROGRESS=1` reports capture preparation and style-reading progress without fixture values.

For WP-1.2 only, `BASELINE_ADDED_BASE_SHEET=1` declares the authorized new `/src/css/base.css` head resource. The strict same-origin reference proxy inserts an empty counterpart link before the first stylesheet and serves that reference CSS request with an empty sheet. Only the candidate receives the extracted declarations. Both captures then contain the same head elements, and **every element/property remains compared**, including the new link; no style or UI node is exempted. The report records this counterpart, the reference must not already load `base.css`, and the option defaults off. Comparisons against a baseline that already includes the base sheet leave it off.

The environment helpers support private directories and an alternate theme-server port without changing normal configuration, demonstration data or Apache. Reference snapshots must exclude private configuration; `git archive` includes tracked application/vendor assets and excludes ignored secrets.

Run `node tests/computed_styles.cjs` to check exact replay, one-channel color rejection, hidden/pseudo-element changes, motion-property exclusion, custom-property changes and element removal.

## JavaScript equality and behavior (WP-0.2)

Use `tests/source_equivalence.py` and `tests/js_ast.cjs`, with Acorn 8.15.0. Formatting and rename-only batches must keep normalized ASTs equal. The AST retains identifiers, property names, directives and literal/template contents; renaming is never automatically waived as formatting. A change that alters structure requires the existing exact before/after SHA-256 declaration with a reason and the relevant behavior checks. Missing/wrong parsers and parse failures fail closed.

```sh
python3 tests/source_equivalence.py --self-test
python3 tests/source_equivalence.py --baseline tests/.runtime/frontend/next/reference \
  --format-only --output tests/.runtime/frontend/next/source-equivalence.json
node tests/readability_checks.cjs
```

Behavior changes run the existing `i18n_browser.cjs`, `theme_browser.cjs`, `dropdowns_browser.cjs`, `ambulances_browser.cjs`, `transfers_surveys_browser.cjs`, `document_links_browser.cjs` and `password_recovery_browser.cjs`. Reuse the disposable fixture instructions in README. The last two are provisioned and cleaned by their corresponding Python integration runners with `--browser`; recovery uses local mock delivery. Live provider/inbox verification is deferred until hosting.

The fleet integration checks require an empty schema and an administrator without implicit clinical/dispatcher permissions. Keep them separate from populated all-page fixtures. `python3 tests/verify_project.py --modules ambulances --fleet-browser --output tests/.runtime/frontend/next/fleet` runs the existing fleet integration and browser journey together before the usual automatic cleanup.

`theme_browser.cjs` accepts `HOSPITAL_THEME_TEST_DIR`, matching its existing environment helper. It freezes decorative motion during geometry comparisons, verifies actual routes/statuses and renews its own disposable session. The i18n suite waits for the current consolidated update endpoints and completed registration navigation.

## Metrics (WP-0.3)

```sh
node tests/frontend_metrics.cjs --self-test
node tests/frontend_metrics.cjs --baseline-root tests/.runtime/frontend/next/reference \
  --output tests/.runtime/frontend/next/metrics.json
```

The inventory excludes vendor/generated files and private configuration. It reports first-party files/bytes, CSS bytes/style-rule counts, grouping/keyframe counts, `theme.css` bytes/rules, color literals and `var()` uses, context-sensitive selector/declaration duplicate groups, JS nonblank/long lines, actual helper definitions separately from bindings/aliases, getters, nested conditional expressions, direct nested decision chains, functions over 60 physical lines, inline style attributes and cache-buster strings. It includes per-file CSS counts and every long JS span. AST-equal JS file line changes are reported separately from changes in files whose AST changed; the latter may mix formatting and behavior.

Phase 1 also records a motion signature over ordered per-file selectors, at-rule contexts, animation/transition declarations (including unresolved shorthands) and keyframe declarations. `motion_declarations_equal` verifies their preservation independently of frozen screenshots and the computed-style motion exclusion. Ordinary color changes outside keyframes do not alter this signature.

CSS parsing uses Chromium CSSOM, preserving at-rule context, serialized shorthand declarations and declaration order for duplicate candidates. Split selector lists count separately. Keeping serialized shorthands prevents different `var()` values from matching merely because their unresolved longhands are empty. Phase 1 corrected the accepted-source page-sheet count from 109 groups / 133 extras to 89 / 113; other metrics are unaffected. CSSOM canonicalization makes these **candidates for review**, not proof of removable source; the computed-style gate decides equivalence. Colors are counted from original declaration values, with explicit hex/function occurrences separated from named colors. Eight-line duplicate windows overlap and are only a comparison indicator.

The all-property gate deliberately flags new custom properties. Phase 2's token additions will therefore require resolving that allowance explicitly before the pilot; no custom-property exemption is enabled in Phase 0.
