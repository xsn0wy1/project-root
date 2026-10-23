# Initial language and UI corrections

Completed 2026-10-23. The baseline includes the preceding development-router performance fix. Frontend Phase 2 remains unstarted.

Staff translation startup now uses the server's `<html lang>` (`en` or the Spanish fallback). Public pages retain their query-language logic, and `auth-session.js` remains byte-identical. Only English bodies wait for translation. Readiness is set after the first whole-document translation, on dictionary failure, or through a 1500 ms safety timer; mutation-observer translations of individual nodes cannot reveal an untranslated document early. Spanish text stays visible while dictionaries are pending. Runtime language changes retain the existing focus, input, selection and account-confirmation behavior.

The development router skips the unchanged autoloader for the exact translations endpoint, retaining mount/file validation. The endpoint source and dictionaries are byte-identical. Sixteen root/mounted GET, invalid-language, array/path-input, POST and HEAD responses match their baseline status, body bytes and headers; only the naturally changing HTTP Date value is excluded. Fourteen include probes confirm dictionaries/static files load zero domain modules, while other APIs/views retain all 16.

## First paint

Chrome 155.0.8059.85, CPU throttled ×6, HTTP cache disabled, an English account, 1440×900 viewport, five navigations per version. The observer checks every visible `data-i18n` element whose Spanish/English copy differs, including display-contents wrappers. A PNG filmstrip captures the actual paint sequence. Times are measured from navigation start.

| Median | Before | After |
| --- | ---: | ---: |
| Visible DOM contains English interface copy | 466.3 ms | 417.7 ms |
| First captured English paint | 476.7 ms | 433.7 ms |

All five baseline runs expose Spanish text before English. All five corrected runs expose zero Spanish frames. Four additional captures pass in dark/light themes at desktop/mobile sizes; their first English paints occur at 447.5, 466.0, 454.6 and 420.6 ms. Paint times have filmstrip/frame granularity and describe this local measurement.

## Final rendering and requested UI changes

The three language/routing changes were first verified independently: **424 comparisons** (200 page states and 224 menu/dialog interactions across all 25 pages) have zero computed-style and pixel differences after load, in both languages/themes at desktop/mobile sizes.

Two intentional UI corrections then followed:

- Survey stage three shows completion only when its existing configuration checks and the survey information/form content are complete. Empty surveys and surveys with only a title retain the stage number; valid templates/default settings retain completion. Save/publish rules and payloads are unchanged.
- The light-mode map filter icon uses its button's text color. Its dimensions, surrounding layout and dark-mode appearance are unchanged.

**40 focused comparisons** cover those pages in both languages/themes at desktop/mobile sizes, including open filters, empty survey configuration and valid templates. Computed changes are limited to the filter background color and the completion circles' text/border/background colors (including Chromium's currentColor aliases). Every control keeps its geometry, and all changed pixels stay inside those controls. Valid surveys and the dark map retain zero differences.

## Checks and inventory

- Existing i18n browser suite: **71 assertions pass**, including CPU ×6 first paint, dictionary failure/stall recovery, Spanish pending dictionaries, both UI corrections, runtime switching, cross-tab persistence, save failures, unsaved input and public survey participation.
- Mounted i18n browser suite: **8 checks pass**. Localization API suite: **27 checks pass**.
- PHP/JS syntax and structural equivalence pass: **90 PHP token streams, 35 JavaScript ASTs, five exact intentional-file declarations**, recorded in `i18n-startup-changes.json`. All other application files retain bytes, including the autoloader, auth confirmation and translation endpoint.
- First-party inventory excluding vendor/generated/private artifacts remains **267 files**. Code grows **2,303,154 → 2,303,626 bytes** (+472); CSS **693,367 → 693,435 bytes** (+68, one readiness rule). Page CSS duplicates remain **66 groups / 77 extras**; eight-line duplication remains **559 groups / 622 extras**. Motion signatures are unchanged. JS adds eight nonblank lines, with no formatting-only growth or additional nested condition chains.

Raw captures, exact style snapshots, metrics and logs remain ignored under `tests/.runtime/i18n-startup/`. Disposable SQL/storage/credential/server fixtures are removed after verification; the existing demonstration server and original installation are preserved.
