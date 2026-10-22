# Frontend simplification — Phase 0

**Complete; paused for review.** Baseline `f0f1c7f` matches the attached plan. All **266 first-party application files retain their bytes**: no HTML/PHP presentation, CSS, JavaScript, dictionary, domain/API, database schema or artwork changed. The phase adds development gates and measurements only. No submission archive was created.

The plan is saved in [frontend-simplification-plan.md](frontend-simplification-plan.md), reusable commands and definitions in [frontend-gates.md](frontend-gates.md), verification in [frontend-phase0.json](frontend-phase0.json), and complete before/after metrics in [frontend-phase0-metrics.json](frontend-phase0-metrics.json).

## Verified gates

- **WP-0.1:** 25 pages × both themes × both languages × desktop/390px/320px produces **300 page comparisons and 336 open-state comparisons**. All **636 comparisons have zero computed-style differences and 0% pixel difference**, below the 0.041% limit. They cover 160,954 element observations and 77,765,452 property values per side, including hidden elements, custom properties and generated pseudo-elements. No missing local assets, new console errors or unhandled script errors remain. Chrome version: 155.0.8059.85.
- **WP-0.2:** Native PHP tokens remain equal for **90 files**, and normalized Acorn ASTs remain equal for **35 JS files**. All 266 application hashes match; no intentional-file declaration is required. The existing seven required browser suites pass, as shown below. Readability decision checks pass **98** boundary/keyboard/lazy-read cases; translation coverage passes **1,816 bilingual keys, 25 fresh page bindings/assets and 1,111 literal references**.
- **WP-0.3:** `tests/frontend_metrics.cjs` measures every requested category, with per-file CSS details and all 70 long JS function spans. Its self-check preserves media context and distinguishes near-equal colors. Computed-style rejection checks and the existing structural-gate self-checks also pass.

| Existing suite | Accepted result |
|---|---|
| `theme_browser` | 50 page/viewport cases, light appearance and restoration of dark geometry |
| `i18n_browser` | 58 checks |
| `dropdowns_browser` | 136 control checks, 52 page/theme/viewport variants, two journeys |
| `ambulances_browser` | 18 checks, with 99 companion SQL/API/permission checks |
| `transfers_surveys_browser` | 53 checks at root and `/hospital` |
| `document_links_browser` | 139 checks, with 46 companion SQL/API checks |
| `password_recovery_browser` | 130 checks, with 58 companion SQL/API checks using local mock delivery |

That is **534 browser assertions plus the 50 theme cases**, and **203 focused SQL/API checks**. Repeated diagnostics are not counted as additional coverage. Live provider/inbox verification remains deferred until hosting, as directed; these mock checks make no real-delivery claim.

## Before and after

Application metrics are identical before and after Phase 0. Vendor/generated artifacts, private configuration, documentation and development tools are excluded.

| Measurement | Before | After |
|---|---:|---:|
| First-party application files | 266 | 266 |
| First-party bytes, including artwork | 12,599,680 | 12,599,680 |
| Code/dictionary bytes | 2,310,420 | 2,310,420 |
| CSS files | 26 | 26 |
| CSS bytes | 696,173 | 696,173 |
| CSS style rules (CSSOM) | 5,787 | 5,787 |
| CSS grouping / keyframe rules | 166 / 23 | 166 / 23 |
| `theme.css` bytes | 191,486 | 191,486 |
| `theme.css` style rules / grouping blocks | 1,166 / 8 | 1,166 / 8 |
| Explicit hardcoded colors (hex/functions) | 2,991 | 2,991 |
| Named hardcoded colors | 301 | 301 |
| `var()` uses | 1,556 | 1,556 |
| Cross-page-sheet duplicate groups / extra occurrences | 109 / 133 | 109 / 133 |
| All-sheet duplicate groups / extra occurrences | 110 / 134 | 110 / 134 |
| Theme unscoped duplicate groups / extra occurrences | 92 / 98 | 92 / 98 |
| JS files / bytes | 35 / 597,520 | 35 / 597,520 |
| JS nonblank lines | 14,311 | 14,311 |
| JS lines over 140 / 250 characters | 184 / 98 | 184 / 98 |
| Actual definitions of the named candidate helpers | 48 | 48 |
| Getter definitions | 218 | 218 |
| `innerHTML`/`setHTML` member references | 80 | 80 |
| Nested conditional expressions / direct decision chains | 35 / 0 | 35 / 0 |
| JS functions over 60 physical lines, including closures | 70 | 70 |
| Inline style source attributes | 215 | 215 |
| Inline-styled `i18n-text` wrappers in PHP/HTML | 174 | 174 |
| Additional inline-styled `i18n-text` JS template | 1 | 1 |
| Distinct view cache-busters / all source cache-busters | 27 / 28 | 27 / 28 |
| Formatting-only JS nonblank line delta | — | 0 |
| Eight-line duplicate groups / extra occurrences | 601 / 664 | 601 / 664 |

Actual helper definitions are: `setBusy` 10, `report` 10, `icon` 9, `toast` 5, `escape` 2, `escapeHtml` 1, `renderPagination` 3, `upsert` 4 and `fromApi` 4. Bindings and existing shared-function aliases are reported separately in the metrics JSON; a variable named `icon` holding a DOM element is not a helper definition.

The inventory confirms the plan's CSS byte/color/variable totals, 109 page-sheet duplicate groups and JS line totals. Its other estimates are replaced by the defined measurements above: there are 26 sheets, 218 getters, 174 static wrappers and **zero direct nested ternary decision chains**. The 35 nested conditional expressions include independent decisions inside templates. `theme.css` has 1,176 literal opening braces, but CSSOM style rules and grouping blocks are different measures. The plan's 127 unscoped duplicate rules are not reproduced by the context-preserving counter, which finds 92 candidate groups. No deletion or reduction claim is based on those estimates.

## Changes and limits

All intentional changes are in tests/documentation: full-property snapshots, strict pixel acceptance, deterministic source origins/vendor tiles, image/API readiness, isolated fixture controls, AST inventory exports, corrected browser waits and an optional fleet journey in the existing verification runner. Formatting/rename batches still require equal ASTs; structural changes retain the existing exact-hash declaration requirement. No application dependency or build step was introduced.

Raw captures and diagnostic attempts stay in ignored `tests/.runtime/frontend/phase0/`. Diagnostics exposed stale update URLs, incomplete navigation/loading/image decoding and decorative-motion timing in the test harness; the final accepted runs use explicit readiness without relaxed visual limits. Fleet empty-schema/permission checks use their own disposable fixture. The full backend runner and fresh installation were not repeated for this unchanged frontend baseline.

All resources created for this phase were cleaned. Eleven test ports are closed, and the existing demonstration returns HTTP 200 on port 8091. An older Stage 5 fixture was identified and preserved; it is outside this phase's cleanup. Isolated preflight is healthy. Original installations/private data and global Apache settings were not changed.

There are **no intentional application changes or color merges**, and no color-merge candidate was approved or applied. Phase 2 adds custom properties, which the strict all-property gate currently treats as significant; resolve that allowance explicitly before its pilot. Phase 1 has not started.

Local work-package commits: `56b28a7` (WP-0.1), `2d77601` (WP-0.2), and the metrics/report commit containing this document (WP-0.3).
