# Small fixes and standalone error pages

Completed 2026-10-23; paused for review. Frontend Phase 2 remains unstarted.

The ambulance detail status trigger retains its desktop 28px height and 10px text on phones and coarse-pointer layouts. Only that control overrides the responsive minimums; dropdown menus, the editor and surrounding actions retain their behavior. The viewer archive title and confirmation use the existing `patients.archivar` translation. Optional diagnostics 429s are dropped before the shared API logger, including non-JSON responses; other failures still report normally.

`public/src/views/error.php` supplies one `render_error_page()` function and its standalone entry point. It reuses the login card, backgrounds, typography, buttons, existing alert icon and theme script. Three scoped CSS rules align the card and keep the icon readable. There is no new JavaScript file or error-page session/API script. Text comes from PHP `I18n`; saved account language/theme take precedence, with the existing browser theme cache used when the account theme is unknown. Anonymous language links retain `?lang=`. Only 500/503 display the request ID.

Role denials render 403; the 401 login redirect is unchanged. The exception handler preserves logging and API/CLI JSON output. Only page HTML is buffered, allowing exceptions after partial rendering to return one clean error document with the correct status. Missing PHP views render 404; missing assets retain an empty 404. Configuration failures also leave the error card's public assets available. Apache ErrorDocument handles 403/404/500, preserves Alias mounts and the original query language, and keeps missing static assets plain. Expression targets follow the [Apache ErrorDocument documentation](https://httpd.apache.org/docs/2.4/mod/core.html#errordocument); isolated Apache processes verified both root and `/hospital` without changing global configuration.

| Gate | Result |
| --- | --- |
| Existing computed styles and pixels | 636 comparisons; zero differences |
| Focused control comparisons | 48 pairs across six fine/coarse layouts; zero style or pixel differences outside the changed control |
| Error-page browser matrix | 128 cases: four statuses, both languages/themes, anonymous/staff, root/mount, desktop/mobile |
| Exception/routing contracts | 350 checks: 80 exact API error pairs, 200 normal HTML pairs, 24 page failure cases, logging and 20 Apache checks |
| Existing SQL/API suites | 517 checks passed |
| Existing focused browser suites | 724 assertions plus 50 theme page/viewport cases passed |
| Fresh installation / readability | 105 / 98 checks passed |
| Translation/assets | 1,827 bilingual keys, 26 fresh page renders, 1,112 literal references and 414 versioned asset URLs |
| Structure/syntax | 91 PHP files and 35 application JS files parse; 86 equal PHP token streams, 32 equal JS ASTs; exactly 13 declared application changes |

The error matrix checks titles, descriptions, correct navigation, stale cached staff themes, visible English text, request-ID visibility and missing assets. CPU ×6 i18n/settings first-paint and runtime switching checks pass after the buffering change. The failure probes include database connection loss and more than 14 KB of partial page output. Exact API comparisons fix only the test request ID and exclude the two transport-dependent Date/Host headers; body bytes, statuses and application headers match. Normal HTML pairs span all 25 existing pages in both languages/themes at root and `/hospital`, allowing only the two requested viewer archive labels.

One API-settle timeout was resumed with unchanged source. A three-pixel tablet rasterization discrepancy disappeared in a targeted replay. The initial CPU-throttled filmstrip timeout passed on rerun after the large visual matrix. No limits were relaxed. The final page-buffer change preserves all normal response bytes and was followed by fresh error, language and theme checks. Real modal screenshots are retained; outside-pixel checks neutralize only the dialog backdrop/shadow in both copies.

Inventory grows **267 → 268 first-party files**, solely the new PHP page. Code/dictionary bytes grow **2,320,661 → 2,328,686 (+8,025)**. CSS grows **698,091 → 698,489 bytes**, adding five rules: three for the error card and two for the compact trigger. `theme.css`, motion declarations, all 371 assets and 48 protected API/domain/SQL/autoload/response files retain their bytes. All 1,818 pre-existing dictionary values are unchanged. Page-sheet duplication stays **66 groups / 77 extras**, cross-sheet **69 / 80**, and repeated eight-line groups/extras **559 / 622**. No reduction or performance claim is made for this feature/fix batch.

Exact declarations are in `error-pages-changes.json`; results and complete measurements are in `error-pages-results.json` and `error-pages-metrics.json`. Actual before/after screenshots and error-card previews are under ignored `tests/.runtime/error-pages/review.html`. Credentials, fixtures, logs and screenshots are not application/submission files. Local recovery checks use mock delivery.

The retained exception check reuses the existing theme fixture and HTTP client. Set `HOSPITAL_THEME_TEST_DIR` to a prepared disposable fixture and `HOSPITAL_ERROR_BASELINE` to a private pre-change source snapshot, then run `python3 tests/error_pages_integration.py --apache --html`. Apache is optional and uses private loopback configurations. The browser status cases are part of `tests/theme_browser.cjs`; `THEME_FILES=error.php` runs just those dedicated cases. Fixture helpers now honor the existing `HOSPITAL_DB_SOCKET` override, retaining their original default.

All owned SQL schemas/accounts, uploads, storage and servers are removed; 20 temporary ports are verified closed. The private MariaDB instance used because the normal database service was stopped is also removed. Normal/original configuration, data, pre-existing fixtures and global XAMPP/Apache settings are unchanged. No archive or dependency was added.
