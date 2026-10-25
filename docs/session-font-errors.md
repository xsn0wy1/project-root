# Session, font and request-error fixes — 2026-10-25

- Automatic logout sends the existing heartbeat every 60 seconds while staff tabs are open, including hidden/idle tabs; the disabled activity policy and server 300-second limit stay unchanged. The existing Spanish/English tooltip key describes closing all tabs.
- Login, registration, recovery and standalone errors load the existing local Inclusive Sans stylesheet. Auth pages also load the existing shared UI script.
- One translated temporary fallback covers network/5xx failures, including document bytes and public survey requests; it reuses available toasts, defers to page messages, works inside dialogs and retains input. No fallback for 401, validation or optional diagnostics 429.
- Real wall-clock test: 360.464 seconds, six successful native-hidden Chrome heartbeats, session still authenticated; the separate session with all app tabs closed redirected to login. No clock/visibility mock in this test.
- Passed: 128 auth/settings/theme + 58 recovery SQL/API checks; 94 theme/session/notification, 145 auth/Settings UI, 71 i18n, 130 recovery and four real-time browser assertions; CPU ×6 first-paint checks included.
- Passed: 50 all-page theme/viewport cases, six auth-font viewport pairs and 128 error-page cases across languages/themes/auth/root/mount/desktop/phone. Fonts are actually loaded; screenshots reviewed in both themes, with eight notification captures.
- Passed: 1,827 bilingual keys / 26 rendered pages / 1,112 literal references, 98 readability checks, and 91 PHP / 35 JS structural checks with 11 exact intentional declarations. Other 257 application files and 47 API/core/domain/SQL files retain bytes; existing CSS rules and translation keys are unchanged.
- First-party files/pages and eight-line duplication are unchanged; code grows 4,573 bytes. No schema/configuration, dependency or palette rollout. See the adjacent results, metrics and exact-change JSON files.
- Private evidence and the 28-image review gallery are under ignored `tests/.runtime/session-font-errors/`; owned test databases, servers, browser profile and credentials are cleaned up. Paused for review.
