# Frontend Phase 2 — verified pilot (2026-10-24)

- Home/Settings now use 12 approved variables, including `--tint`, `--panel`, `--shade`; exact alpha values and legacy `--light-*` references remain.
- Removed R036/R092/R103/R104, **39/97 scoped theme rules (40.2%)**, 67 scoped declarations and two embedded light rules; necessary focus/hover priority and native-select resets remain variable-backed.
- `theme.css`: **191,899 → 186,388 bytes** (**5,511 saved**). Total CSS: **698,489 → 693,940 bytes**.
- Existing color literal/function metric: **3,310 → 3,246**; `var()` uses: **1,560 → 1,610**. Channel constructors count as functions under the existing metric.
- First-party application files: **268 → 268**; code/dictionaries: **2,328,686 → 2,324,137 bytes**. All CSS motion declarations are unchanged.
- **144 exact style/pixel comparisons pass**: both languages/themes, 1440/390, resting states, navigation/profile/search/dropdown states, field/card hover/focus, privacy and all five Settings dialogs. Zero ordinary computed-style differences, differing RGBA pixels, script/console errors or missing assets.
- Source equivalence passes **91 PHP token streams / 35 JS ASTs / four exact intentional CSS changes**; test-tool self-checks pass. [Hashes/reasons](frontend-phase2-pilot-changes.json) and [full proof](frontend-phase2-pilot-results.json). Disposable database/servers/credentials are removed.
- Full 636-state/theme/i18n/dropdown/metrics checks and the single-palette fold remain due at Phase 2 end. **Paused before management rollout; no near-equal colors were merged.**
