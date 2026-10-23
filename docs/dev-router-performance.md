# Development-router static-file performance

Completed 2026-10-23, independently of the frontend phase sequence.

`backend/php/dev-router.php` now loads `Config.php` directly for the validated mount. It requires the existing autoloader only for API routes, allowed PHP views and the existing configuration-error response. Static resources and missing/blocked public paths avoid all 16 domain modules. The autoloader, configuration class, API handlers and public files retain their exact bytes. The README development-server command now includes `-d opcache.enable_cli=1`.

## Measurement

The same unmodified `/src/css/theme.css` (191,486 bytes) was fetched over loopback using PHP 8.0.28 and Python's HTTP client. Each run starts its own server, warms 20 requests, then times 300 sequential requests through complete body receipt and verifies every response against the source bytes. Both versions use the same command and settings; the router is the sole application change. Temporary servers are stopped after each run.

| CLI option | Median before | Median after | 95th percentile before | 95th percentile after |
| --- | ---: | ---: | ---: | ---: |
| `opcache.enable_cli=0` | 10.127 ms | 0.968 ms | 11.316 ms | 1.120 ms |
| `opcache.enable_cli=1` | 10.121 ms | 0.975 ms | 11.863 ms | 1.101 ms |

The measured static-file response meets the approximately 3.5 ms target. This installed PHP binary does not load Zend OPcache, so these results measure the routing change; the README option enables CLI caching when the extension is available. No global PHP/Apache setting was changed. These timings describe this local HTTP measurement, not browser page-settle time.

## Verification

- PHP syntax and the existing structural-equivalence gate pass: 90 PHP token streams, 35 JavaScript ASTs and one exact intentional-file declaration in `dev-router-performance-changes.json`.
- All 101 existing fresh-install checks pass, including 22 added assertions for root and `/hospital` static bytes, MIME/nosniff/cache headers, HEAD, missing assets and missing-API JSON. Existing checks cover unconfigured 503 responses, private paths, login, staff-page guards, permissions, migrations and preflight. Disposable configuration, SQL accounts/databases, storage and servers are cleaned up.
- A private shutdown probe passes 76 routing/include assertions. CSS, JS, SVG, HEAD and blocked/missing static requests include no autoloader or domain modules; root/mounted PHP views and API errors include all 16 modules. Mount boundaries and traversal rejection are retained.
- First-party inventory excluding vendor/private/generated files remains 267 files: 12,592,208 → 12,592,414 bytes (+206 bytes in the router). The other 266 files are byte-identical. All public HTML/PHP templates, CSS, JavaScript and images are unchanged, so frontend duplication counts and the accepted visual design are unchanged.
- `autoload.php` retains SHA-256 `136029e14cdb4aa4be97b31873087f68918455b495dffafaa765728aea3d6089`. The installation checks exercise its CLI consumers.

Raw timings, baseline, include traces and test reports remain ignored under `tests/.runtime/dev-router-performance/`. Frontend Phase 2 remains unstarted.
