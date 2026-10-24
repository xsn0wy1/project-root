# Frontend Phase 2 — Step 1 inventory and variable proposal

> Approved on 2026-10-24 with RGB channel revisions. This table is the historical pre-pilot inventory; see the [verified pilot](frontend-phase2-pilot.md) for the implemented palette and current status.

Inventory of commit `16f0f3a` (2026-10-23). The error-page work is committed. Application files are unchanged; the pilot has **not** started.

The revised [owner plan](frontend-phase2-plan.md) requires “Send me the table and the proposed variable list before editing.” This is that review checkpoint. The [machine-readable table](frontend-phase2-inventory.json) includes source locations and exact values.

## Scope and counting

All six loaded first-party sheets were read in their actual load order: base, main-menu, settings (Settings only), theme, dropdowns, responsive. Every light declaration in the two page scopes is included, plus the shared/global light rules in those sheets. Global map/photo/palette rules that are inactive here are retained and labeled. Base and main-menu currently contain no light overrides.

| Sheet | Source rules | Expanded selectors | Selector × declaration rows |
| --- | ---: | ---: | ---: |
| `public/src/css/dropdowns.css` | 7 | 9 | 18 |
| `public/src/css/responsive.css` | 2 | 2 | 2 |
| `public/src/css/settings.css` | 5 | 5 | 8 |
| `public/src/css/theme.css` | 110 | 137 | 237 |
| **Total** | **124** | **153** | **265** |

`theme.css` contains 55 main-menu scoped rules (60 selector occurrences / 85 declaration rows), 42 Settings scoped rules (64 selector occurrences / 124 declaration rows), and 13 common/global rules (13 selector occurrences / 28 declaration rows) in this inventory. A comma-separated rule counts once as a source rule and once per selector in the expanded table; a selector repeated in a later rule is counted again. The 97 scoped source rules are the pilot removal denominator.

Pairs are grouped by exact color components (including the written decimal opacity), or the entire non-color value. Equivalent spelling such as `#eee` / `#eeeeee`, whitespace and `.025` / `0.025` is grouped; `.025` / `.018` and `.375` / `.38` are separate. Shorthand border widths/styles stay untouched. Chromium can serialize alpha to rounded values; those serializations are **not** used to merge source pairs.

The dark column records the corresponding authored dark rule, including hover/focus/selected states. The Light column preserves each authored light declaration and resolves its existing variables in parentheses. Earlier declarations that are later superseded remain in the inventory and are marked. Phone-only cascade differences and initial/inherited values are explicit.

## Proposed pilot variables

Define the following in `base.css`, dark in `:root` and light in `html[data-theme="light"]`. Four names are from the proposed starting set. Eight added names each have evidence on at least three pages, including three that avoid collisions with existing page variables. No pair is approximated. Only occurrences with the matching dark **and light** pair qualify.

| Variable | Dark value | Light value on the pilot | Evidence |
| --- | --- | --- | --- |
| `--surface-base` (additional) | `#101732` | `#f3f5fa` | body; Settings dialog; Upload Document; Transfer Detail |
| `--surface-raised` | `rgba(16, 23, 50, 0.38)` | `rgba(245, 248, 255, 0.38)` | sidebar/topbar; Settings panel |
| `--text-primary` (additional) | `#eeeeee` | `#20283d` | headings/body; Settings heading/dialog; Documents |
| `--text-muted` | `rgba(238, 238, 238, 0.55)` | `#5e6a82` | home/search copy; Settings tab/--trace-muted |
| `--line-default` (additional) | `rgba(238, 238, 238, 0.18)` | `rgba(88, 108, 151, 0.16)` | topbar/profile menu; Settings panel; Ambulances |
| `--accent` | `#abbaea` | `#586ca7` | navigation/search accents; Settings dialog accent |
| `--icon-filter` | `brightness(0) invert(1)` | `F` (full filter below) | navigation/brand; Settings avatar/upload/theme/dialog icons |
| `--line-soft` (additional) | `rgba(238, 238, 238, 0.14)` | `rgba(88, 108, 151, 0.16)` | sidebar/search; Settings search; Upload Document nav indicator |
| `--fill-faint` (additional) | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | home cards; Settings avatar/focused fields; Routes search |
| `--fill-soft` (additional) | `rgba(255, 255, 255, 0.04)` | `rgba(89, 110, 167, 0.04)` | search icon; Settings specialty hover; Documents filter |
| `--fill-hover` (additional) | `rgba(255, 255, 255, 0.06)` | `rgba(89, 110, 167, 0.06)` | sidebar/indicator; Settings avatar hover; Upload Document indicator |
| `--text-secondary` (additional) | `rgba(238, 238, 238, 0.6)` | `#5e6a82` | navigation on Home/Settings/Documents; Settings theme option |

Full light icon filter: `brightness(0) saturate(100%) invert(16%) sepia(19%) saturate(1095%) hue-rotate(184deg) brightness(93%) contrast(90%)`.

The existing `--light-*` palette stays available during the pilot for unmigrated sheets. The new light text/canvas/accent/icon values should reference those existing palette variables with the listed exact fallbacks. That preserves the existing root overrides on trace-map (`--light-muted: #536079`, `--light-accent: #4e6099`) and auth pages; freezing a second independent light palette would change shared navigation elsewhere. Element-local primary-button/photo exceptions also stay in place.

The requested names `--surface`, `--text` and `--line` are already used with different values: `catalogs.css:4–8` defines them on the Personnel/Destinations page, and `trace-map.css:5/8` defines surface/text at the root. Shared headers would inherit catalog text/lines after a naive replacement. Propose `--surface-base`, `--text-primary` and `--line-default` for the new pairs; leave the existing page-local variables untouched. The added names are currently unused in application source.

Additional-name evidence, beyond the two pilot pages:

- `--surface-base`: Upload Document `body`, dark `documents-upload.css:7`, light `theme.css:292`; also Transfer Detail `.route-preview-vehicle::after`, dark `trace-view.css:173`, light `theme.css:981`.
- `--text-primary`: Ambulances `--ambulance-text`, dark `ambulances.css:1`, light `theme.css:472`; also Documents and other staff pages.
- `--line-default`: Ambulances `.directory-panel`, dark `ambulances.css:144`, light `theme.css:479`; also Patients and other staff pages.
- `--line-soft`: Upload Document `.document-workspace .nav-indicator`, dark `documents-upload.css:43`, light `theme.css:293`.
- `--fill-faint`: Routes `.route-search:focus-within`, dark `routes.css:224`, light `theme.css:464`.
- `--fill-soft`: Documents `.filter-trigger`, dark `dropdowns.css:4`, page-specific light `theme.css:262` (which has higher specificity than the generic dropdown light rule).
- `--fill-hover`: Upload Document `.document-workspace .nav-indicator`, dark `documents-upload.css:43`, light `theme.css:293`; also trace-map `.icon-button:hover`.
- `--text-secondary`: the shared `.nav-item` is rendered on Home, Settings and Documents (and other staff pages), dark `main-menu.css:171`, light `theme.css:45`. Settings `.theme-option` uses the same pair. The saved-HTML color probe confirms the pair on those three pages.

`--accent-image`, `--danger` and `--success` are deferred: the pilot has no common exact pair across the two page contents for those roles. The Settings switch and the theme highlight share dark green but have different light values. Reset-password and dialog-save images also have different existing URL spellings/cache parameters. Keep those exact exceptions until their rollout inventory justifies a shared pair.

## Every light override

Selector abbreviations below are exact textual prefixes: **M** = `html[data-theme="light"][data-theme-styles~="main-menu"]`; **S** = `html[data-theme="light"][data-theme-styles~="settings"]`; **L** = `html[data-theme="light"]`. Unprefixed `.theme-switch[data-theme="light"]` is an element state. `F` in a value means the full light icon filter printed above. Source locations are `file:line`. Group IDs link equal pairs across the following tables.

### theme.css — main-menu scope

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R027 / G029 | `theme.css:37` | `M` | `color-scheme` | `dark` | `light` | Keep layout/native-control state.  |
| R028 / G030 | `theme.css:38` | `M body` | `background` | `#101732` | `var(--light-canvas)` (`#f3f5fa`) | Candidate --surface-base.  |
| R029 / G001 | `theme.css:38` | `M body` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R030 / G013 | `theme.css:39` | `M .sidebar` | `border` | `1px solid rgba(238, 238, 238, 0.14)` | `1px solid rgba(88, 108, 151, 0.16)` | Candidate --line-soft.  |
| R031 / G010 | `theme.css:39` | `M .sidebar` | `background` | `rgba(16, 23, 50, 0.38)` | `rgba(245, 248, 255, 0.38)` | Candidate --surface-raised.  |
| R032 / G031 | `theme.css:40` | `M .app-shell.menu-open .sidebar` | `background` | `rgba(12, 17, 38, 0.65)` | `rgba(245, 248, 255, 0.65)` | Keep exact pair/unique override; no proposed token. Desktop dark value shown. Phone dark is #111934 (responsive.css:76); this page-scoped light rule still wins on phones, keeping rgba(245,248,255,.65). |
| R033 / G061 | `theme.css:40` | `M .app-shell.menu-open .sidebar` | `box-shadow` | `0 20px 50px rgba(0, 0, 0, 0.3)` | `0 20px 50px rgba(44, 61, 104, 0.075)` | Keep shadow effect.  |
| R034 / G014 | `theme.css:41` | `M .app-shell:not(.menu-open) .sidebar:hover` | `background` | `rgba(255, 255, 255, 0.06)` | `rgba(89, 110, 167, 0.06)` | Candidate --fill-hover.  |
| R035 / G032 | `theme.css:41` | `M .app-shell:not(.menu-open) .sidebar:hover` | `border-color` | `rgba(238, 238, 238, 0.28)` | `rgba(88, 108, 151, 0.21)` | Keep exact pair/unique override; no proposed token.  |
| R036 / G009 | `theme.css:42` | `M .sidebar-action span` | `background` | `#eeeeee` | `#ffffff` | Earlier superseded value; remove only with final covered override in pilot. Earlier white light value is superseded by theme.css:1404 or :1408; final value is var(--light-text). |
| R037 / G062 | `theme.css:43` | `M .app-shell.menu-open .sidebar-action:hover span` | `background` | `#c44343` | `#a33450` | Keep exact pair/unique override; no proposed token.  |
| R038 / G014 | `theme.css:44` | `M .nav-indicator` | `background` | `rgba(255, 255, 255, 0.06)` | `rgba(89, 110, 167, 0.06)` | Candidate --fill-hover.  |
| R039 / G063 | `theme.css:44` | `M .nav-indicator` | `border` | `1px solid rgba(255, 255, 255, 0.1)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R040 / G033 | `theme.css:45` | `M .nav-item` | `color` | `rgba(238, 238, 238, 0.6)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-secondary.  |
| R041 / G004 | `theme.css:46` | `M .nav-item img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R042 / G001 | `theme.css:47` | `M .nav-item:hover` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R043 / G007 | `theme.css:48` | `M .nav-item.active` | `color` | `#abbaea` | `var(--light-accent)` (`#586ca7`) | Candidate --accent.  |
| R044 / G064 | `theme.css:49` | `M .nav-item.active img` | `filter` | `brightness(0) saturate(100%) invert(77%) sepia(11%) saturate(1478%) hue-rotate(189deg) brightness(97%) contrast(96%)` | `var(--light-icon-filter)` (`F`) | Keep exact pair/unique override; no proposed token.  |
| R045 / G011 | `theme.css:50` | `M .topbar` | `border` | `1px solid rgba(238, 238, 238, 0.18)` | `1px solid rgba(88, 108, 151, 0.16)` | Candidate --line-default.  |
| R046 / G010 | `theme.css:50` | `M .topbar` | `background` | `rgba(16, 23, 50, 0.38)` | `rgba(245, 248, 255, 0.38)` | Candidate --surface-raised.  |
| R047 / G001 | `theme.css:51` | `M .topbar-action` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R048 / G001 | `theme.css:52` | `M .brand` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R049 / G004 | `theme.css:53` | `M .brand img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R050 / G065 | `theme.css:54` | `M .topbar.search-active .search-wrapper` | `background` | `rgba(8, 12, 28, 0.55)` | `rgba(245, 248, 255, 0.55)` | Keep exact pair/unique override; no proposed token.  |
| R051 / G013 | `theme.css:54` | `M .topbar.search-active .search-wrapper` | `border-color` | `rgba(238, 238, 238, 0.14)` | `rgba(88, 108, 151, 0.16)` | Candidate --line-soft.  |
| R052 / G034 | `theme.css:54` | `M .topbar.search-active .search-wrapper` | `box-shadow` | `inset 0 2px 8px rgba(0, 0, 0, 0.25)` | `inset 0 2px 8px rgba(44, 61, 104, 0.0625)` | Keep shadow effect.  |
| R053 / G001 | `theme.css:55` | `M .search-input` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R054 / G066 | `theme.css:56` | `M .search-input::placeholder` | `color` | `rgba(238, 238, 238, 0.45)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R055 / G067 | `theme.css:57` | `M .search-action:hover` | `background` | `rgba(255, 255, 255, 0.05)` | `rgba(89, 110, 167, 0.05)` | Keep exact pair/unique override; no proposed token.  |
| R056 / G015 | `theme.css:58` | `M .search-action img` | `filter` | `brightness(0) saturate(100%) invert(93%) sepia(2%) saturate(0%) hue-rotate(180deg) brightness(104%) contrast(96%)` | `var(--light-icon-filter)` (`F`) | Keep exact pair/unique override; no proposed token.  |
| R057 / G068 | `theme.css:59` | `M .search-wrapper:focus-within` | `border-color` | `rgba(171, 186, 234, 0.7)` | `rgba(88, 108, 151, 0.525)` | Keep exact pair/unique override; no proposed token.  |
| R058 / G011 | `theme.css:60` | `M .search-results` | `border` | `1px solid rgba(238, 238, 238, 0.18)` | `1px solid rgba(88, 108, 151, 0.16)` | Candidate --line-default.  |
| R059 / G069 | `theme.css:60` | `M .search-results` | `background` | `rgba(16, 23, 50, 0.97)` | `rgba(245, 248, 255, 0.97)` | Keep exact pair/unique override; no proposed token.  |
| R060 / G070 | `theme.css:60` | `M .search-results` | `box-shadow` | `0 26px 65px rgba(0, 0, 0, 0.42)` | `0 26px 65px rgba(44, 61, 104, 0.105)` | Keep shadow effect.  |
| R061 / G001 | `theme.css:60` | `M .search-results` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R062 / G007 | `theme.css:61` | `M .search-results-heading` | `color` | `#abbaea` | `var(--light-accent)` (`#586ca7`) | Candidate --accent.  |
| R063 / G001 | `theme.css:62` | `M .search-result` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R064 / G016 | `theme.css:63` | `M a.search-result:hover` | `border-color` | `rgba(171, 186, 234, 0.26)` | `rgba(88, 108, 151, 0.195)` | Keep exact pair/unique override; no proposed token.  |
| R065 / G006 | `theme.css:63` | `M a.search-result:hover` | `background` | `rgba(171, 186, 234, 0.12)` | `rgba(89, 110, 167, 0.12)` | Keep exact pair/unique override; no proposed token.  |
| R066 / G016 | `theme.css:63` | `M a.search-result:focus-visible` | `border-color` | `rgba(171, 186, 234, 0.26)` | `rgba(88, 108, 151, 0.195)` | Keep exact pair/unique override; no proposed token.  |
| R067 / G006 | `theme.css:63` | `M a.search-result:focus-visible` | `background` | `rgba(171, 186, 234, 0.12)` | `rgba(89, 110, 167, 0.12)` | Keep exact pair/unique override; no proposed token.  |
| R068 / G016 | `theme.css:63` | `M .search-result.is-active` | `border-color` | `rgba(171, 186, 234, 0.26)` | `rgba(88, 108, 151, 0.195)` | Keep exact pair/unique override; no proposed token.  |
| R069 / G006 | `theme.css:63` | `M .search-result.is-active` | `background` | `rgba(171, 186, 234, 0.12)` | `rgba(89, 110, 167, 0.12)` | Keep exact pair/unique override; no proposed token.  |
| R070 / G035 | `theme.css:66` | `M .search-result-icon` | `border` | `1px solid rgba(238, 238, 238, 0.12)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R071 / G017 | `theme.css:66` | `M .search-result-icon` | `background` | `rgba(255, 255, 255, 0.04)` | `rgba(89, 110, 167, 0.04)` | Candidate --fill-soft.  |
| R072 / G004 | `theme.css:67` | `M .search-result-icon img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R073 / G008 | `theme.css:68` | `M .search-result-copy small` | `color` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R074 / G007 | `theme.css:69` | `M .search-result-end` | `color` | `#abbaea` | `var(--light-accent)` (`#586ca7`) | Candidate --accent.  |
| R075 / G036 | `theme.css:70` | `M .search-result.is-unavailable .search-result-end` | `color` | `rgba(238, 238, 238, 0.65)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R076 / G036 | `theme.css:71` | `M .search-empty` | `color` | `rgba(238, 238, 238, 0.65)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R077 / G071 | `theme.css:72` | `M .search-results-footer` | `border-top` | `1px solid rgba(238, 238, 238, 0.1)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R078 / G072 | `theme.css:72` | `M .search-results-footer` | `color` | `rgba(238, 238, 238, 0.4)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R079 / G001 | `theme.css:73` | `M .hero-copy h1` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R080 / G008 | `theme.css:74` | `M .hero-copy p` | `color` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R081 / G005 | `theme.css:75` | `M .feature-card` | `background` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R082 / G073 | `theme.css:75` | `M .feature-card` | `border` | `1px solid rgba(238, 238, 238, 0.13)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R083 / G074 | `theme.css:75` | `M .feature-card` | `box-shadow` | `0 20px 50px rgba(0, 0, 0, 0.08)` | `0 20px 50px rgba(44, 61, 104, 0.02)` | Keep shadow effect.  |
| R084 / G075 | `theme.css:76` | `M .feature-card:hover` | `background` | `rgba(255, 255, 255, 0.035)` | `rgba(89, 110, 167, 0.035)` | Keep exact pair/unique override; no proposed token.  |
| R085 / G076 | `theme.css:77` | `M .feature-icon` | `background` | `rgba(255, 255, 255, 0.015)` | `rgba(89, 110, 167, 0.015)` | Keep exact pair/unique override; no proposed token.  |
| R086 / G035 | `theme.css:77` | `M .feature-icon` | `border` | `1px solid rgba(238, 238, 238, 0.12)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R087 / G015 | `theme.css:78` | `M .feature-icon img` | `filter` | `brightness(0) saturate(100%) invert(93%) sepia(2%) saturate(0%) hue-rotate(180deg) brightness(104%) contrast(96%)` | `var(--light-icon-filter)` (`F`) | Keep exact pair/unique override; no proposed token.  |
| R088 / G001 | `theme.css:79` | `M .feature-card h2` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R089 / G008 | `theme.css:80` | `M .feature-card p` | `color` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R090 / G077 | `theme.css:81` | `M .card-button` | `border` | `1px solid rgba(238, 238, 238, 0.17)` | `1px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R091 / G005 | `theme.css:81` | `M .card-button` | `background` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R092 / G009 | `theme.css:82` | `M .card-button::before` | `background` | `#eeeeee` | `#ffffff` | Earlier superseded value; remove only with final covered override in pilot. Earlier white light value is superseded by theme.css:1404 or :1408; final value is var(--light-text). |
| R093 / G078 | `theme.css:83` | `M .card-button:hover` | `background` | `rgba(255, 255, 255, 0.07)` | `rgba(89, 110, 167, 0.07)` | Keep exact pair/unique override; no proposed token.  |
| R094 / G032 | `theme.css:83` | `M .card-button:hover` | `border-color` | `rgba(238, 238, 238, 0.28)` | `rgba(88, 108, 151, 0.21)` | Keep exact pair/unique override; no proposed token.  |
| R095 / G011 | `theme.css:84` | `M .profile-menu` | `border` | `1px solid rgba(238, 238, 238, 0.18)` | `1px solid rgba(88, 108, 151, 0.16)` | Candidate --line-default.  |
| R096 / G079 | `theme.css:84` | `M .profile-menu` | `background` | `rgba(16, 23, 50, 0.55)` | `rgba(245, 248, 255, 0.55)` | Keep exact pair/unique override; no proposed token.  |
| R097 / G080 | `theme.css:84` | `M .profile-menu` | `box-shadow` | `0 15px 35px rgba(0, 0, 0, 0.3)` | `0 15px 35px rgba(44, 61, 104, 0.075)` | Keep shadow effect.  |
| R098 / G015 | `theme.css:85` | `M .profile-menu-item img` | `filter` | `brightness(0) saturate(100%) invert(93%) sepia(2%) saturate(0%) hue-rotate(180deg) brightness(104%) contrast(96%)` | `var(--light-icon-filter)` (`F`) | Keep exact pair/unique override; no proposed token.  |
| R099 / G081 | `theme.css:86` | `M .profile-menu-divider` | `background` | `rgba(238, 238, 238, 0.15)` | `rgba(89, 110, 167, 0.15)` | Keep exact pair/unique override; no proposed token.  |
| R100 / G082 | `theme.css:87` | `M .profile-button` | `border` | `1px solid rgba(238, 238, 238, 0.55)` | `1px solid rgba(88, 108, 151, 0.413)` | Keep exact pair/unique override; no proposed token.  |
| R101 / G083 | `theme.css:87` | `M .profile-button` | `background` | `rgba(16, 23, 50, 0.45)` | `rgba(245, 248, 255, 0.45)` | Keep exact pair/unique override; no proposed token.  |
| R102 / G084 | `theme.css:87` | `M .profile-button` | `box-shadow` | `0 20px 45px rgba(0, 0, 0, 0.22)` | `0 20px 45px rgba(44, 61, 104, 0.055)` | Keep shadow effect.  |
| R103 / G009 | `theme.css:88` | `M .profile-button::before` | `background` | `#eeeeee` | `#ffffff` | Earlier superseded value; remove only with final covered override in pilot. Earlier white light value is superseded by theme.css:1404 or :1408; final value is var(--light-text). |
| R104 / G009 | `theme.css:89` | `M .profile-button::after` | `background` | `#eeeeee` | `#ffffff` | Earlier superseded value; remove only with final covered override in pilot. Earlier white light value is superseded by theme.css:1404 or :1408; final value is var(--light-text). |
| R105 / G037 | `theme.css:90` | `M .profile-button:hover` | `background` | `rgba(255, 255, 255, 0.08)` | `rgba(89, 110, 167, 0.08)` | Keep exact pair/unique override; no proposed token.  |
| R106 / G037 | `theme.css:90` | `M .profile-wrapper.profile-active .profile-button` | `background` | `rgba(255, 255, 255, 0.08)` | `rgba(89, 110, 167, 0.08)` | Keep exact pair/unique override; no proposed token.  |
| R237 / G001 | `theme.css:1404` | `M .sidebar-action span` | `background` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R238 / G129 | `theme.css:1405` | `M .card-button::before` | `border-left-color` | `currentColor (initial border color; inherits the button text color)` | `var(--light-accent)` (`#586ca7`) | Keep exact pair/unique override; no proposed token. Border width is zero; keep this element-specific finishing override. |
| R239 / G001 | `theme.css:1407` | `M .profile-button::before` | `background` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R240 / G001 | `theme.css:1407` | `M .profile-button::after` | `background` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R241 / G001 | `theme.css:1407` | `M .card-button::before` | `background` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |

### theme.css — Settings scope

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R107 / G018 | `theme.css:137` | `S` | `--trace-panel` | `#111934` | `var(--light-canvas)` (`#f3f5fa`) | Keep exact pair/unique override; no proposed token.  |
| R108 / G085 | `theme.css:137` | `S` | `--trace-card` | `rgba(18, 26, 55, 0.68)` | `rgba(245, 248, 255, 0.68)` | Keep exact pair/unique override; no proposed token.  |
| R109 / G086 | `theme.css:137` | `S` | `--trace-border` | `rgba(150, 163, 209, 0.24)` | `rgba(88, 108, 151, 0.18)` | Keep exact pair/unique override; no proposed token.  |
| R110 / G008 | `theme.css:137` | `S` | `--trace-muted` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R111 / G011 | `theme.css:138` | `S .bg-panel` | `border` | `1px solid rgba(238, 238, 238, 0.18)` | `1px solid rgba(88, 108, 151, 0.16)` | Candidate --line-default.  |
| R112 / G010 | `theme.css:138` | `S .bg-panel` | `background` | `rgba(16, 23, 50, 0.38)` | `rgba(245, 248, 255, 0.38)` | Candidate --surface-raised.  |
| R113 / G087 | `theme.css:139` | `S .bg-panel` | `scrollbar-color` | `rgba(171, 186, 234, 0.35) transparent` | `rgba(88, 108, 151, 0.262) transparent` | Keep exact pair/unique override; no proposed token.  |
| R114 / G013 | `theme.css:140` | `S .settings-search-host.search-active .search-wrapper` | `border-color` | `rgba(238, 238, 238, 0.14)` | `rgba(88, 108, 151, 0.16)` | Candidate --line-soft.  |
| R115 / G088 | `theme.css:140` | `S .settings-search-host.search-active .search-wrapper` | `background` | `rgba(8, 12, 28, 0.85)` | `rgba(245, 248, 255, 0.85)` | Keep exact pair/unique override; no proposed token.  |
| R116 / G034 | `theme.css:140` | `S .settings-search-host.search-active .search-wrapper` | `box-shadow` | `inset 0 2px 8px rgba(0, 0, 0, 0.25)` | `inset 0 2px 8px rgba(44, 61, 104, 0.0625)` | Keep shadow effect.  |
| R117 / G001 | `theme.css:141` | `S .settings-heading h1` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R118 / G008 | `theme.css:142` | `S .settings-tab` | `color` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R119 / G089 | `theme.css:143` | `S .settings-tab:hover` | `color` | `rgba(238, 238, 238, 0.86)` | `var(--light-text)` (`#20283d`) | Keep exact pair/unique override; no proposed token.  |
| R120 / G090 | `theme.css:144` | `S .settings-tab.active` | `color` | `rgba(171, 186, 234, 0.62)` | `var(--light-accent)` (`#586ca7`) | Keep exact pair/unique override; no proposed token.  |
| R121 / G091 | `theme.css:144` | `S .settings-tab.active` | `text-shadow` | `0 0 18px rgba(123, 145, 223, 0.12)` | `0 0 18px rgba(44, 61, 104, 0.03)` | Keep shadow effect.  |
| R122 / G003 | `theme.css:145` | `S .settings-tab:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R123 / G003 | `theme.css:145` | `S .user-control:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R124 / G003 | `theme.css:145` | `S .specialty-chip:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R125 / G003 | `theme.css:145` | `S .specialty-add:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R126 / G003 | `theme.css:145` | `S .upload-box:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R127 / G003 | `theme.css:145` | `S .toggle:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R128 / G003 | `theme.css:145` | `S .theme-option:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R129 / G003 | `theme.css:145` | `S .reset-password:focus-visible` | `outline` | `2px solid #abbaea` | `2px solid rgba(88, 108, 151, 0.65)` | Keep exact pair/unique override; no proposed token.  |
| R130 / G038 | `theme.css:153` | `S .user-control` | `color` | `rgba(238, 238, 238, 0.58)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R131 / G001 | `theme.css:154` | `S .user-control:hover` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R132 / G092 | `theme.css:155` | `S .user-avatar` | `border` | `2px solid rgba(238, 238, 238, 0.45)` | `2px solid rgba(88, 108, 151, 0.338)` | Keep exact pair/unique override; no proposed token.  |
| R133 / G005 | `theme.css:155` | `S .user-avatar` | `background` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R134 / G093 | `theme.css:156` | `S .user-control:hover .user-avatar` | `border-color` | `rgba(238, 238, 238, 0.72)` | `rgba(88, 108, 151, 0.54)` | Keep exact pair/unique override; no proposed token.  |
| R135 / G014 | `theme.css:156` | `S .user-control:hover .user-avatar` | `background` | `rgba(255, 255, 255, 0.06)` | `rgba(89, 110, 167, 0.06)` | Candidate --fill-hover.  |
| R136 / G004 | `theme.css:157` | `S .user-avatar img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R137 / G094 | `theme.css:158` | `S .user-arrows` | `color` | `rgba(238, 238, 238, 0.75)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R138 / G002 | `theme.css:159` | `S .settings-field label` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R139 / G002 | `theme.css:159` | `S .select-setting label` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R140 / G002 | `theme.css:159` | `S .section-label` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R141 / G002 | `theme.css:159` | `S .visibility-setting > p` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R142 / G002 | `theme.css:159` | `S .privacy-setting p` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R143 / G002 | `theme.css:159` | `S .theme-setting > p` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R144 / G002 | `theme.css:159` | `S .password-setting p` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R145 / G002 | `theme.css:159` | `S .specialties-group > p` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R146 / G019 | `theme.css:167` | `S .settings-field input` | `border` | `2px solid rgba(143, 158, 207, 0.24)` | `2px solid rgba(88, 108, 151, 0.18)` | Keep exact pair/unique override; no proposed token.  |
| R147 / G020 | `theme.css:167` | `S .settings-field input` | `background` | `rgba(8, 13, 32, 0.13)` | `rgba(245, 248, 255, 0.13)` | Keep exact pair/unique override; no proposed token.  |
| R148 / G021 | `theme.css:167` | `S .settings-field input` | `color` | `rgba(238, 238, 238, 0.74)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R149 / G019 | `theme.css:167` | `S .settings-field select` | `border` | `2px solid rgba(143, 158, 207, 0.24)` | `2px solid rgba(88, 108, 151, 0.18)` | Keep exact pair/unique override; no proposed token.  |
| R150 / G020 | `theme.css:167` | `S .settings-field select` | `background` | `rgba(8, 13, 32, 0.13)` | `rgba(245, 248, 255, 0.13)` | Keep exact pair/unique override; no proposed token.  |
| R151 / G021 | `theme.css:167` | `S .settings-field select` | `color` | `rgba(238, 238, 238, 0.74)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R152 / G019 | `theme.css:167` | `S .select-setting select` | `border` | `2px solid rgba(143, 158, 207, 0.24)` | `2px solid rgba(88, 108, 151, 0.18)` | Keep exact pair/unique override; no proposed token.  |
| R153 / G020 | `theme.css:167` | `S .select-setting select` | `background` | `rgba(8, 13, 32, 0.13)` | `rgba(245, 248, 255, 0.13)` | Keep exact pair/unique override; no proposed token.  |
| R154 / G021 | `theme.css:167` | `S .select-setting select` | `color` | `rgba(238, 238, 238, 0.74)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R155 / G002 | `theme.css:170` | `S .settings-field input::placeholder` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R156 / G012 | `theme.css:171` | `S .settings-field input:hover` | `border-color` | `rgba(171, 186, 234, 0.4)` | `rgba(88, 108, 151, 0.3)` | Keep exact pair/unique override; no proposed token.  |
| R157 / G022 | `theme.css:171` | `S .settings-field input:hover` | `background-color` | `rgba(255, 255, 255, 0.018)` | `rgba(89, 110, 167, 0.018)` | Keep exact pair/unique override; no proposed token.  |
| R158 / G012 | `theme.css:171` | `S .settings-field select:hover` | `border-color` | `rgba(171, 186, 234, 0.4)` | `rgba(88, 108, 151, 0.3)` | Keep exact pair/unique override; no proposed token.  |
| R159 / G022 | `theme.css:171` | `S .settings-field select:hover` | `background-color` | `rgba(255, 255, 255, 0.018)` | `rgba(89, 110, 167, 0.018)` | Keep exact pair/unique override; no proposed token.  |
| R160 / G012 | `theme.css:171` | `S .select-setting select:hover` | `border-color` | `rgba(171, 186, 234, 0.4)` | `rgba(88, 108, 151, 0.3)` | Keep exact pair/unique override; no proposed token.  |
| R161 / G022 | `theme.css:171` | `S .select-setting select:hover` | `background-color` | `rgba(255, 255, 255, 0.018)` | `rgba(89, 110, 167, 0.018)` | Keep exact pair/unique override; no proposed token.  |
| R162 / G023 | `theme.css:174` | `S .settings-field input:focus` | `border-color` | `rgba(171, 186, 234, 0.64)` | `rgba(88, 108, 151, 0.48)` | Keep exact pair/unique override; no proposed token.  |
| R163 / G005 | `theme.css:174` | `S .settings-field input:focus` | `background-color` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R164 / G024 | `theme.css:174` | `S .settings-field input:focus` | `box-shadow` | `0 0 0 4px rgba(122, 145, 220, 0.08)` | `0 0 0 4px rgba(44, 61, 104, 0.02)` | Keep shadow effect.  |
| R165 / G023 | `theme.css:174` | `S .settings-field select:focus` | `border-color` | `rgba(171, 186, 234, 0.64)` | `rgba(88, 108, 151, 0.48)` | Keep exact pair/unique override; no proposed token.  |
| R166 / G005 | `theme.css:174` | `S .settings-field select:focus` | `background-color` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R167 / G024 | `theme.css:174` | `S .settings-field select:focus` | `box-shadow` | `0 0 0 4px rgba(122, 145, 220, 0.08)` | `0 0 0 4px rgba(44, 61, 104, 0.02)` | Keep shadow effect.  |
| R168 / G023 | `theme.css:174` | `S .select-setting select:focus` | `border-color` | `rgba(171, 186, 234, 0.64)` | `rgba(88, 108, 151, 0.48)` | Keep exact pair/unique override; no proposed token.  |
| R169 / G005 | `theme.css:174` | `S .select-setting select:focus` | `background-color` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R170 / G024 | `theme.css:174` | `S .select-setting select:focus` | `box-shadow` | `0 0 0 4px rgba(122, 145, 220, 0.08)` | `0 0 0 4px rgba(44, 61, 104, 0.02)` | Keep shadow effect.  |
| R171 / G025 | `theme.css:177` | `S .specialty-chip` | `border` | `2px solid rgba(143, 158, 207, 0.18)` | `2px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R172 / G002 | `theme.css:177` | `S .specialty-chip` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R173 / G039 | `theme.css:177` | `S .specialty-chip` | `background` | `rgba(8, 13, 32, 0.1)` | `rgba(245, 248, 255, 0.1)` | Keep exact pair/unique override; no proposed token.  |
| R174 / G025 | `theme.css:177` | `S .specialty-add` | `border` | `2px solid rgba(143, 158, 207, 0.18)` | `2px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R175 / G002 | `theme.css:177` | `S .specialty-add` | `color` | `rgba(238, 238, 238, 0.57)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R176 / G039 | `theme.css:177` | `S .specialty-add` | `background` | `rgba(8, 13, 32, 0.1)` | `rgba(245, 248, 255, 0.1)` | Keep exact pair/unique override; no proposed token.  |
| R177 / G001 | `theme.css:179` | `S .specialty-chip:hover` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R178 / G040 | `theme.css:179` | `S .specialty-chip:hover` | `border-color` | `rgba(171, 186, 234, 0.42)` | `rgba(88, 108, 151, 0.315)` | Keep exact pair/unique override; no proposed token.  |
| R179 / G017 | `theme.css:179` | `S .specialty-chip:hover` | `background` | `rgba(255, 255, 255, 0.04)` | `rgba(89, 110, 167, 0.04)` | Candidate --fill-soft.  |
| R180 / G041 | `theme.css:179` | `S .specialty-chip:hover` | `box-shadow` | `0 10px 20px rgba(3, 7, 21, 0.18)` | `0 10px 20px rgba(44, 61, 104, 0.045)` | Keep shadow effect.  |
| R181 / G001 | `theme.css:179` | `S .specialty-add:hover` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R182 / G040 | `theme.css:179` | `S .specialty-add:hover` | `border-color` | `rgba(171, 186, 234, 0.42)` | `rgba(88, 108, 151, 0.315)` | Keep exact pair/unique override; no proposed token.  |
| R183 / G017 | `theme.css:179` | `S .specialty-add:hover` | `background` | `rgba(255, 255, 255, 0.04)` | `rgba(89, 110, 167, 0.04)` | Candidate --fill-soft.  |
| R184 / G041 | `theme.css:179` | `S .specialty-add:hover` | `box-shadow` | `0 10px 20px rgba(3, 7, 21, 0.18)` | `0 10px 20px rgba(44, 61, 104, 0.045)` | Keep shadow effect.  |
| R185 / G095 | `theme.css:181` | `S .general-divider` | `background` | `rgba(153, 168, 214, 0.1)` | `rgba(225, 233, 250, 0.1)` | Keep exact pair/unique override; no proposed token.  |
| R186 / G096 | `theme.css:182` | `S .upload-box` | `border` | `4px dashed rgba(238, 238, 238, 0.35)` | `4px dashed rgba(88, 108, 151, 0.262)` | Keep exact pair/unique override; no proposed token.  |
| R187 / G038 | `theme.css:182` | `S .upload-box` | `color` | `rgba(238, 238, 238, 0.58)` | `var(--light-muted)` (`#5e6a82`) | Keep exact pair/unique override; no proposed token.  |
| R188 / G097 | `theme.css:182` | `S .upload-box` | `background` | `rgba(8, 13, 32, 0.08)` | `rgba(245, 248, 255, 0.08)` | Keep exact pair/unique override; no proposed token.  |
| R189 / G098 | `theme.css:183` | `S .upload-box:hover` | `color` | `rgba(238, 238, 238, 0.84)` | `var(--light-text)` (`#20283d`) | Keep exact pair/unique override; no proposed token.  |
| R190 / G099 | `theme.css:183` | `S .upload-box:hover` | `border-color` | `rgba(171, 186, 234, 0.58)` | `rgba(88, 108, 151, 0.435)` | Keep exact pair/unique override; no proposed token.  |
| R191 / G005 | `theme.css:183` | `S .upload-box:hover` | `background` | `rgba(255, 255, 255, 0.025)` | `rgba(89, 110, 167, 0.025)` | Candidate --fill-faint.  |
| R192 / G100 | `theme.css:183` | `S .upload-box:hover` | `box-shadow` | `0 15px 30px rgba(3, 7, 21, 0.16)` | `0 15px 30px rgba(44, 61, 104, 0.04)` | Keep shadow effect.  |
| R193 / G004 | `theme.css:184` | `S .upload-icon img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R194 / G008 | `theme.css:185` | `S .toggle-line > span` | `color` | `rgba(238, 238, 238, 0.55)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-muted.  |
| R195 / G101 | `theme.css:186` | `S .toggle::before` | `background` | `rgba(238, 238, 238, 0.72)` | `rgba(88, 108, 151, 0.32)` | Keep exact pair/unique override; no proposed token.  |
| R196 / G102 | `theme.css:187` | `S .toggle-knob` | `background` | `#d04a4a` | `#b34e64` | Keep exact pair/unique override; no proposed token.  |
| R197 / G103 | `theme.css:187` | `S .toggle-knob` | `box-shadow` | `0 6px 16px rgba(0, 0, 0, 0.18)` | `0 6px 16px rgba(44, 61, 104, 0.12)` | Keep shadow effect.  |
| R198 / G104 | `theme.css:188` | `S .toggle.is-on .toggle-knob` | `background` | `#00c675` | `#338b67` | Keep exact pair/unique override; no proposed token.  |
| R199 / G105 | `theme.css:189` | `S .toggle:hover .toggle-knob` | `box-shadow` | `0 8px 18px rgba(0, 0, 0, 0.24)` | `0 8px 18px rgba(44, 61, 104, 0.06)` | Keep shadow effect.  |
| R200 / G106 | `theme.css:190` | `S .select-setting select` | `background-image` | `linear-gradient(45deg, transparent 50%, rgba(238, 238, 238, 0.5) 50%), linear-gradient(135deg, rgba(238, 238, 238, 0.5) 50%, transparent 50%)` | `linear-gradient(45deg, transparent 50%, rgba(255, 255, 255, 0.5) 50%), linear-gradient(135deg, rgba(255, 255, 255, 0.5) 50%, transparent 50%)` | Keep exact pair/unique override; no proposed token.  |
| R201 / G025 | `theme.css:192` | `S .theme-switch` | `border` | `2px solid rgba(143, 158, 207, 0.18)` | `2px solid rgba(88, 108, 151, 0.16)` | Keep exact pair/unique override; no proposed token.  |
| R202 / G107 | `theme.css:192` | `S .theme-switch` | `background` | `rgba(8, 13, 32, 0.12)` | `rgba(245, 248, 255, 0.12)` | Keep exact pair/unique override; no proposed token.  |
| R203 / G108 | `theme.css:193` | `S .theme-highlight` | `background` | `#00c675` | `#ccd8ef` | Keep exact pair/unique override; no proposed token.  |
| R204 / G033 | `theme.css:194` | `S .theme-option` | `color` | `rgba(238, 238, 238, 0.6)` | `var(--light-muted)` (`#5e6a82`) | Candidate --text-secondary.  |
| R205 / G001 | `theme.css:195` | `S .theme-option:hover` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R206 / G004 | `theme.css:196` | `S .theme-option img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R207 / G001 | `theme.css:197` | `S .theme-option.active` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R208 / G012 | `theme.css:198` | `S .reset-password` | `border` | `1px solid rgba(171, 186, 234, 0.4)` | `1px solid rgba(88, 108, 151, 0.3)` | Keep exact pair/unique override; no proposed token.  |
| R209 / G009 | `theme.css:198` | `S .reset-password` | `color` | `#eeeeee` | `var(--light-button-text)` (`#ffffff`) | Keep exact pair/unique override; no proposed token.  |
| R210 / G109 | `theme.css:198` | `S .reset-password` | `background-color` | `#244db6` | `#e1e9fa` | Keep exact pair/unique override; no proposed token.  |
| R211 / G110 | `theme.css:198` | `S .reset-password` | `background-image` | `url("../../assets/special-bg.png")` | `var(--light-button-image)` (`url("../../assets/special-bg-light.jpg?v=20261019-button-image")`) | Keep exact pair/unique override; no proposed token.  |
| R212 / G111 | `theme.css:198` | `S .reset-password` | `box-shadow` | `0 12px 24px rgba(5, 16, 62, 0.24)` | `0 12px 24px rgba(44, 61, 104, 0.06)` | Keep shadow effect.  |
| R213 / G112 | `theme.css:198` | `S .reset-password` | `text-shadow` | `none` | `var(--light-button-text-shadow)` (`0 1px 2px rgba(13, 27, 65, 0.5)`) | Keep shadow effect.  |
| R214 / G113 | `theme.css:198` | `S .reset-password` | `--light-icon-filter` | `(unset)` | `brightness(0) invert(1)` | Keep exact pair/unique override; no proposed token. No dark definition in the pilot sheets; existing light-only variable. |
| R215 / G028 | `theme.css:198` | `S .reset-password` | `--light-text` | `(unset)` | `var(--light-button-text)` (`#ffffff`) | Keep exact pair/unique override; no proposed token. No dark definition in the pilot sheets; existing light-only variable. |
| R216 / G114 | `theme.css:199` | `S .reset-password:hover` | `border-color` | `rgba(238, 238, 238, 0.62)` | `rgba(88, 108, 151, 0.465)` | Keep exact pair/unique override; no proposed token.  |
| R217 / G115 | `theme.css:199` | `S .reset-password:hover` | `box-shadow` | `0 16px 30px rgba(5, 16, 62, 0.34)` | `0 16px 30px rgba(44, 61, 104, 0.085)` | Keep shadow effect.  |
| R218 / G116 | `theme.css:200` | `S .settings-dialog` | `border` | `1px solid rgba(150, 163, 209, .4)` | `1px solid rgba(88, 108, 151, 0.3)` | Keep exact pair/unique override; no proposed token.  |
| R219 / G030 | `theme.css:200` | `S .settings-dialog` | `background` | `#101732` | `var(--light-canvas)` (`#f3f5fa`) | Candidate --surface-base.  |
| R220 / G001 | `theme.css:200` | `S .settings-dialog` | `color` | `#eee` | `var(--light-text)` (`#20283d`) | Candidate --text-primary.  |
| R221 / G117 | `theme.css:200` | `S .settings-dialog` | `--dialog-control` | `#182244` | `#f5f8ff` | Keep exact pair/unique override; no proposed token.  |
| R222 / G118 | `theme.css:200` | `S .settings-dialog` | `--dialog-hover` | `rgba(171, 186, 234, .12)` | `#eaf0fb` | Keep exact pair/unique override; no proposed token.  |
| R223 / G119 | `theme.css:200` | `S .settings-dialog` | `--dialog-focus` | `#1c294b` | `#fff` | Keep exact pair/unique override; no proposed token.  |
| R224 / G007 | `theme.css:200` | `S .settings-dialog` | `--dialog-accent` | `#abbaea` | `#586ca7` | Candidate --accent.  |
| R225 / G120 | `theme.css:200` | `S .settings-dialog` | `--dialog-glow` | `rgba(122, 145, 220, .13)` | `rgba(44,61,104,.08)` | Keep shadow effect.  |
| R226 / G121 | `theme.css:200` | `S .settings-dialog` | `--dialog-danger` | `#ff9eae` | `#a92f4c` | Keep exact pair/unique override; no proposed token.  |
| R227 / G122 | `theme.css:200` | `S .settings-dialog` | `--dialog-danger-line` | `rgba(244, 63, 94, .4)` | `rgba(173,49,76,.3)` | Keep exact pair/unique override; no proposed token.  |
| R228 / G123 | `theme.css:200` | `S .settings-dialog` | `--dialog-danger-bg` | `rgba(244, 63, 94, .12)` | `rgba(173,49,76,.08)` | Keep exact pair/unique override; no proposed token.  |
| R229 / G124 | `theme.css:200` | `S .settings-dialog` | `--dialog-danger-hover` | `rgba(244, 63, 94, .2)` | `rgba(173,49,76,.15)` | Keep exact pair/unique override; no proposed token.  |
| R230 / G125 | `theme.css:207` | `S .settings-dialog::backdrop` | `background` | `rgba(0, 0, 0, .55)` | `rgba(245, 248, 255, 0.55)` | Keep exact pair/unique override; no proposed token.  |

### theme.css — common/global rules

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R009 / G027 | `theme.css:3` | `L` | `--light-canvas` | `(unset)` | `#f3f5fa` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R010 / G049 | `theme.css:3` | `L` | `--light-text` | `(unset)` | `#20283d` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R011 / G050 | `theme.css:3` | `L` | `--light-muted` | `(unset)` | `#5e6a82` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R012 / G051 | `theme.css:3` | `L` | `--light-accent` | `(unset)` | `#586ca7` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R013 / G052 | `theme.css:3` | `L` | `--light-link-highlight` | `(unset)` | `#315ac4` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R014 / G028 | `theme.css:3` | `L` | `--light-button-text` | `(unset)` | `#ffffff` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R015 / G053 | `theme.css:3` | `L` | `--light-button-text-shadow` | `(unset)` | `0 1px 2px rgba(13, 27, 65, 0.5)` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R016 / G054 | `theme.css:3` | `L` | `--light-icon-filter` | `(unset)` | `F` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R017 / G055 | `theme.css:3` | `L` | `--light-button-image` | `(unset)` | `url("../../assets/special-bg-light.jpg?v=20261019-button-image")` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R018 / G029 | `theme.css:3` | `L` | `color-scheme` | `dark` | `light` | Keep global appearance/palette/exception rule in pilot.  |
| R019 / G056 | `theme.css:3` | `L` | `color` | `CanvasText (computed rgb(255, 255, 255))` | `var(--light-text)` (`#20283d`) | Keep global appearance/palette/exception rule in pilot. Dark html inherits the UA initial color; body declares its own text color. |
| R020 / G057 | `theme.css:27` | `L .appearance-switch` | `background` | `rgba(16,23,50,.38)` | `rgba(245,248,255,.65)` | Keep global appearance/palette/exception rule in pilot.  |
| R021 / G058 | `theme.css:27` | `L .appearance-switch` | `border-color` | `rgba(171,186,234,.3)` | `rgba(88,108,151,.22)` | Keep global appearance/palette/exception rule in pilot.  |
| R022 / G001 | `theme.css:28` | `L .appearance-switch button` | `color` | `#eeeeee` | `var(--light-text)` (`#20283d`) | Keep global appearance/palette/exception rule in pilot.  |
| R023 / G004 | `theme.css:29` | `L .appearance-switch img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Keep global appearance/palette/exception rule in pilot.  |
| R024 / G059 | `theme.css:30` | `L .appearance-switch button[aria-pressed="true"]` | `background` | `rgba(171,186,234,.18)` | `rgba(88,108,167,.14)` | Keep global appearance/palette/exception rule in pilot.  |
| R025 / G060 | `theme.css:30` | `L .appearance-switch button[aria-pressed="true"]` | `border-color` | `rgba(171,186,234,.32)` | `rgba(88,108,151,.3)` | Keep global appearance/palette/exception rule in pilot.  |
| R026 / G007 | `theme.css:31` | `L .appearance-switch button:focus-visible` | `outline-color` | `#abbaea` | `var(--light-accent)` (`#586ca7`) | Keep global appearance/palette/exception rule in pilot.  |
| R231 / G027 | `theme.css:1389` | `L` | `--schematic-surface` | `(unset)` | `#f3f5fa` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R232 / G126 | `theme.css:1390` | `L :is(.profile-button[data-profile-photo] img, img[data-profile-photo], .upload-box[data-has-photo], .document-page img, .document-image, .survey-access-qr img)` | `filter` | `none for the pilot photo/upload elements` | `none` | Keep global appearance/palette/exception rule in pilot. Photo exception stays !important; other selector branches belong to later pages. |
| R233 / G010 | `theme.css:1391` | `L .sidebar` | `background` | `rgba(16, 23, 50, 0.38)` | `rgba(245,248,255,.38)` | Keep global appearance/palette/exception rule in pilot.  |
| R234 / G031 | `theme.css:1392` | `L .app-shell.menu-open .sidebar` | `background` | `rgba(12, 17, 38, 0.65)` | `rgba(245,248,255,.65)` | Keep global appearance/palette/exception rule in pilot. Desktop dark value shown. Phone dark is #111934 (responsive.css:76); this page-scoped light rule still wins on phones, keeping rgba(245,248,255,.65). |
| R235 / G127 | `theme.css:1393` | `L .route-flow` | `stroke` | `not rendered on either pilot page` | `#586ca7` | Keep global appearance/palette/exception rule in pilot. Global map exception; no matching pilot element or loaded map rule. |
| R236 / G128 | `theme.css:1394` | `L .osm-dark-tiles` | `filter` | `not rendered on either pilot page` | `none` | Keep global appearance/palette/exception rule in pilot. Global map exception; no matching pilot element or loaded map rule. |
| R242 / G130 | `theme.css:1413` | `L` | `--password-strength-0` | `(unset)` | `#ab3450` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R243 / G131 | `theme.css:1413` | `L` | `--password-strength-1` | `(unset)` | `#805d15` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R244 / G132 | `theme.css:1413` | `L` | `--password-strength-2` | `(unset)` | `#536f21` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |
| R245 / G133 | `theme.css:1413` | `L` | `--password-strength-3` | `(unset)` | `#246c50` | Keep global appearance/palette/exception rule in pilot. No dark definition in the pilot sheets; existing light-only variable. |

### Settings sheet — embedded light/state rules

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R001 / G044 | `settings.css:642` | `.theme-switch[data-theme="light"] .theme-highlight` | `transform` | `none` | `translateX(78px)` | Keep layout/native-control state. Theme highlight position/shape; preserve layout and motion. |
| R002 / G045 | `settings.css:642` | `.theme-switch[data-theme="light"] .theme-highlight` | `clip-path` | `polygon(0 0, 100% 0, 84% 100%, 0 100%)` | `polygon(16% 0, 100% 0, 100% 100%, 0 100%)` | Keep layout/native-control state. Theme highlight position/shape; preserve layout and motion. |
| R003 / G046 | `settings.css:1007` | `S .settings-dialog-actions .save-button` | `border-color` | `#6689ef` | `#6689ef` | Keep exact pair/unique override; no proposed token.  |
| R004 / G047 | `settings.css:1007` | `S .settings-dialog-actions .save-button` | `background` | `#4b6dde url('../../assets/special-bg.png') center / cover` | `#4b6dde url('../../assets/special-bg-light.jpg') center / cover` | Keep exact pair/unique override; no proposed token.  |
| R005 / G048 | `settings.css:1007` | `S .settings-dialog-actions .save-button` | `color` | `#fff` | `#fff` | Keep exact pair/unique override; no proposed token.  |
| R006 / G004 | `settings.css:1008` | `S .settings-dialog .icon-button img` | `filter` | `brightness(0) invert(1)` | `var(--light-icon-filter)` (`F`) | Candidate --icon-filter.  |
| R007 / G006 | `settings.css:1009` | `L .settings-profile-wrapper .profile-menu-item:is(:hover,:focus-visible)` | `background` | `rgba(171,186,234,.12)` | `rgba(89,110,167,.12)` | Keep exact pair/unique override; no proposed token.  |
| R008 / G007 | `settings.css:1010` | `L .settings-profile-wrapper .profile-menu-item:focus-visible` | `outline-color` | `#abbaea` | `var(--light-accent)` (`#586ca7`) | Candidate --accent.  |

### Shared dropdown light rules

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R246 / G134 | `dropdowns.css:43` | `L .filter-trigger` | `border-color` | `rgba(150,163,209,.28)` | `rgba(88,108,151,.3)` | Keep shared dropdown/mobile rule in pilot.  |
| R247 / G135 | `dropdowns.css:43` | `L .filter-trigger` | `background` | `rgba(255,255,255,.04)` | `rgba(255,255,255,.38)` | Keep shared dropdown/mobile rule in pilot.  |
| R248 / G001 | `dropdowns.css:43` | `L .filter-trigger` | `color` | `#eee` | `var(--light-text,#273149)` (`#20283d`) | Keep shared dropdown/mobile rule in pilot.  |
| R249 / G042 | `dropdowns.css:44` | `L .filter-trigger:hover:not(:disabled)` | `border-color` | `rgba(171,186,234,.62)` | `rgba(88,108,151,.465)` | Keep shared dropdown/mobile rule in pilot.  |
| R250 / G006 | `dropdowns.css:44` | `L .filter-trigger:hover:not(:disabled)` | `background` | `rgba(171,186,234,.12)` | `rgba(89,110,167,.12)` | Keep shared dropdown/mobile rule in pilot.  |
| R251 / G042 | `dropdowns.css:44` | `L .filter-trigger[aria-expanded="true"]` | `border-color` | `rgba(171,186,234,.62)` | `rgba(88,108,151,.465)` | Keep shared dropdown/mobile rule in pilot.  |
| R252 / G006 | `dropdowns.css:44` | `L .filter-trigger[aria-expanded="true"]` | `background` | `rgba(171,186,234,.12)` | `rgba(89,110,167,.12)` | Keep shared dropdown/mobile rule in pilot.  |
| R253 / G003 | `dropdowns.css:45` | `L .filter-chevron` | `border-right-color` | `#abbaea` | `rgba(88,108,151,.65)` | Keep shared dropdown/mobile rule in pilot.  |
| R254 / G003 | `dropdowns.css:45` | `L .filter-chevron` | `border-bottom-color` | `#abbaea` | `rgba(88,108,151,.65)` | Keep shared dropdown/mobile rule in pilot.  |
| R255 / G136 | `dropdowns.css:46` | `L .filter-menu` | `border-color` | `rgba(171,186,234,.28)` | `rgba(88,108,151,.21)` | Keep shared dropdown/mobile rule in pilot.  |
| R256 / G018 | `dropdowns.css:46` | `L .filter-menu` | `background` | `#111934` | `var(--light-canvas,#f2f4fa)` (`#f3f5fa`) | Keep shared dropdown/mobile rule in pilot.  |
| R257 / G137 | `dropdowns.css:46` | `L .filter-menu` | `box-shadow` | `0 14px 30px rgba(0,0,0,.34)` | `0 14px 30px rgba(44,61,104,.085)` | Keep shared dropdown/mobile rule in pilot.  |
| R258 / G001 | `dropdowns.css:47` | `L .filter-menu button` | `color` | `#eee` | `var(--light-text,#273149)` (`#20283d`) | Keep shared dropdown/mobile rule in pilot.  |
| R259 / G026 | `dropdowns.css:48` | `L .filter-menu button:hover:not(:disabled)` | `background` | `rgba(171,186,234,.16)` | `rgba(89,110,167,.16)` | Keep shared dropdown/mobile rule in pilot.  |
| R260 / G043 | `dropdowns.css:48` | `L .filter-menu button:hover:not(:disabled)` | `color` | `#fff` | `var(--light-text,#273149)` (`#20283d`) | Keep shared dropdown/mobile rule in pilot.  |
| R261 / G026 | `dropdowns.css:48` | `L .filter-menu button[aria-pressed="true"]` | `background` | `rgba(171,186,234,.16)` | `rgba(89,110,167,.16)` | Keep shared dropdown/mobile rule in pilot.  |
| R262 / G043 | `dropdowns.css:48` | `L .filter-menu button[aria-pressed="true"]` | `color` | `#fff` | `var(--light-text,#273149)` (`#20283d`) | Keep shared dropdown/mobile rule in pilot.  |
| R263 / G026 | `dropdowns.css:53` | `L .hospital-select-menu button[aria-selected="true"]` | `background` | `rgba(171,186,234,.16)` | `rgba(89,110,167,.16)` | Keep shared dropdown/mobile rule in pilot.  |

### Shared mobile/coarse-pointer light rules

| Row / group | Source | Selector | Property | Dark value | Light value | Pilot handling / cascade note |
| --- | --- | --- | --- | --- | --- | --- |
| R264 / G018 | `responsive.css:80` | `L .app-shell.menu-open .sidebar` | `background` | `#111934` | `var(--light-canvas,#f2f4fa)` (`#f3f5fa`) | Keep shared dropdown/mobile rule in pilot. This light declaration loses to the more-specific main-menu scoped rule at theme.css:40; the effective light phone drawer stays rgba(245,248,255,.65). Context: @media (max-width: 770px), (max-width: 1000px) and (max-height: 500px) and (pointer: coarse). |
| R265 / G138 | `responsive.css:81` | `L .mobile-nav-backdrop` | `background` | `rgba(3,8,24,.58)` | `rgba(44,61,104,.3)` | Keep shared dropdown/mobile rule in pilot.  Context: @media (max-width: 770px), (max-width: 1000px) and (max-height: 500px) and (pointer: coarse). |

## Exact-pair groups

All 138 groups are listed, including retained shadow/layout/global pairs. Counts use the expanded table, so comma-list rows are visible rather than hidden in a total. “Candidate” is a proposal, not a completed edit.

| Group | Dark → light pair | Rows | Proposed handling |
| --- | --- | --- | --- |
| G001 | `rgb(238, 238, 238)` → `rgb(32, 40, 61)` | R022, R029, R042, R047, R048, R053, R061, R063, R079, R088, R117, R131, R177, R181, R205, R207, R220, R237, R239, R240, R241, R248, R258 | Candidate `--text-primary`; retain any global/shared exceptions listed above. |
| G002 | `rgba(238, 238, 238, 0.57)` → `rgb(94, 106, 130)` | R138, R139, R140, R141, R142, R143, R144, R145, R155, R172, R175 | Keep current pair; superseded rows are labeled above. |
| G003 | `rgb(171, 186, 234)` → `rgba(88, 108, 151, 0.65)` | R122, R123, R124, R125, R126, R127, R128, R129, R253, R254 | Keep current pair; superseded rows are labeled above. |
| G004 | `brightness(0) invert(1)` → `F` | R006, R023, R041, R049, R072, R136, R193, R206 | Candidate `--icon-filter`; retain any global/shared exceptions listed above. |
| G005 | `rgba(255, 255, 255, 0.025)` → `rgba(89, 110, 167, 0.025)` | R081, R091, R133, R163, R166, R169, R191 | Candidate `--fill-faint`; retain any global/shared exceptions listed above. |
| G006 | `rgba(171, 186, 234, 0.12)` → `rgba(89, 110, 167, 0.12)` | R007, R065, R067, R069, R250, R252 | Keep current pair; superseded rows are labeled above. |
| G007 | `rgb(171, 186, 234)` → `rgb(88, 108, 167)` | R008, R026, R043, R062, R074, R224 | Candidate `--accent`; retain any global/shared exceptions listed above. |
| G008 | `rgba(238, 238, 238, 0.55)` → `rgb(94, 106, 130)` | R073, R080, R089, R110, R118, R194 | Candidate `--text-muted`; retain any global/shared exceptions listed above. |
| G009 | `rgb(238, 238, 238)` → `rgb(255, 255, 255)` | R036, R092, R103, R104, R209 | Keep current pair; superseded rows are labeled above. |
| G010 | `rgba(16, 23, 50, 0.38)` → `rgba(245, 248, 255, 0.38)` | R031, R046, R112, R233 | Candidate `--surface-raised`; retain any global/shared exceptions listed above. |
| G011 | `rgba(238, 238, 238, 0.18)` → `rgba(88, 108, 151, 0.16)` | R045, R058, R095, R111 | Candidate `--line-default`; retain any global/shared exceptions listed above. |
| G012 | `rgba(171, 186, 234, 0.4)` → `rgba(88, 108, 151, 0.3)` | R156, R158, R160, R208 | Keep current pair; superseded rows are labeled above. |
| G013 | `rgba(238, 238, 238, 0.14)` → `rgba(88, 108, 151, 0.16)` | R030, R051, R114 | Candidate `--line-soft`; retain any global/shared exceptions listed above. |
| G014 | `rgba(255, 255, 255, 0.06)` → `rgba(89, 110, 167, 0.06)` | R034, R038, R135 | Candidate `--fill-hover`; retain any global/shared exceptions listed above. |
| G015 | `brightness(0) saturate(100%) invert(93%) sepia(2%) saturate(0%) hue-rotate(180deg) brightness(104%) contrast(96%)` → `F` | R056, R087, R098 | Keep current pair; superseded rows are labeled above. |
| G016 | `rgba(171, 186, 234, 0.26)` → `rgba(88, 108, 151, 0.195)` | R064, R066, R068 | Keep current pair; superseded rows are labeled above. |
| G017 | `rgba(255, 255, 255, 0.04)` → `rgba(89, 110, 167, 0.04)` | R071, R179, R183 | Candidate `--fill-soft`; retain any global/shared exceptions listed above. |
| G018 | `rgb(17, 25, 52)` → `rgb(243, 245, 250)` | R107, R256, R264 | Keep current pair; superseded rows are labeled above. |
| G019 | `rgba(143, 158, 207, 0.24)` → `rgba(88, 108, 151, 0.18)` | R146, R149, R152 | Keep current pair; superseded rows are labeled above. |
| G020 | `rgba(8, 13, 32, 0.13)` → `rgba(245, 248, 255, 0.13)` | R147, R150, R153 | Keep current pair; superseded rows are labeled above. |
| G021 | `rgba(238, 238, 238, 0.74)` → `rgb(94, 106, 130)` | R148, R151, R154 | Keep current pair; superseded rows are labeled above. |
| G022 | `rgba(255, 255, 255, 0.018)` → `rgba(89, 110, 167, 0.018)` | R157, R159, R161 | Keep current pair; superseded rows are labeled above. |
| G023 | `rgba(171, 186, 234, 0.64)` → `rgba(88, 108, 151, 0.48)` | R162, R165, R168 | Keep current pair; superseded rows are labeled above. |
| G024 | `0 0 0 4px rgba(122, 145, 220, 0.08)` → `0 0 0 4px rgba(44, 61, 104, 0.02)` | R164, R167, R170 | Keep current pair; superseded rows are labeled above. |
| G025 | `rgba(143, 158, 207, 0.18)` → `rgba(88, 108, 151, 0.16)` | R171, R174, R201 | Keep current pair; superseded rows are labeled above. |
| G026 | `rgba(171, 186, 234, 0.16)` → `rgba(89, 110, 167, 0.16)` | R259, R261, R263 | Keep current pair; superseded rows are labeled above. |
| G027 | `(unset)` → `#f3f5fa` | R009, R231 | Keep current pair; superseded rows are labeled above. |
| G028 | `(unset)` → `#ffffff` | R014, R215 | Keep current pair; superseded rows are labeled above. |
| G029 | `dark` → `light` | R018, R027 | Keep current pair; superseded rows are labeled above. |
| G030 | `rgb(16, 23, 50)` → `rgb(243, 245, 250)` | R028, R219 | Candidate `--surface-base`; retain any global/shared exceptions listed above. |
| G031 | `rgba(12, 17, 38, 0.65)` → `rgba(245, 248, 255, 0.65)` | R032, R234 | Keep current pair; superseded rows are labeled above. |
| G032 | `rgba(238, 238, 238, 0.28)` → `rgba(88, 108, 151, 0.21)` | R035, R094 | Keep current pair; superseded rows are labeled above. |
| G033 | `rgba(238, 238, 238, 0.6)` → `rgb(94, 106, 130)` | R040, R204 | Candidate `--text-secondary`; retain any global/shared exceptions listed above. |
| G034 | `inset 0 2px 8px rgba(0, 0, 0, 0.25)` → `inset 0 2px 8px rgba(44, 61, 104, 0.0625)` | R052, R116 | Keep current pair; superseded rows are labeled above. |
| G035 | `rgba(238, 238, 238, 0.12)` → `rgba(88, 108, 151, 0.16)` | R070, R086 | Keep current pair; superseded rows are labeled above. |
| G036 | `rgba(238, 238, 238, 0.65)` → `rgb(94, 106, 130)` | R075, R076 | Keep current pair; superseded rows are labeled above. |
| G037 | `rgba(255, 255, 255, 0.08)` → `rgba(89, 110, 167, 0.08)` | R105, R106 | Keep current pair; superseded rows are labeled above. |
| G038 | `rgba(238, 238, 238, 0.58)` → `rgb(94, 106, 130)` | R130, R187 | Keep current pair; superseded rows are labeled above. |
| G039 | `rgba(8, 13, 32, 0.1)` → `rgba(245, 248, 255, 0.1)` | R173, R176 | Keep current pair; superseded rows are labeled above. |
| G040 | `rgba(171, 186, 234, 0.42)` → `rgba(88, 108, 151, 0.315)` | R178, R182 | Keep current pair; superseded rows are labeled above. |
| G041 | `0 10px 20px rgba(3, 7, 21, 0.18)` → `0 10px 20px rgba(44, 61, 104, 0.045)` | R180, R184 | Keep current pair; superseded rows are labeled above. |
| G042 | `rgba(171, 186, 234, 0.62)` → `rgba(88, 108, 151, 0.465)` | R249, R251 | Keep current pair; superseded rows are labeled above. |
| G043 | `rgb(255, 255, 255)` → `rgb(32, 40, 61)` | R260, R262 | Keep current pair; superseded rows are labeled above. |
| G044 | `none` → `translateX(78px)` | R001 | Keep current pair; superseded rows are labeled above. |
| G045 | `polygon(0 0, 100% 0, 84% 100%, 0 100%)` → `polygon(16% 0, 100% 0, 100% 100%, 0 100%)` | R002 | Keep current pair; superseded rows are labeled above. |
| G046 | `rgb(102, 137, 239)` → `rgb(102, 137, 239)` | R003 | Keep current pair; superseded rows are labeled above. |
| G047 | `#4b6dde url('../../assets/special-bg.png') center / cover` → `#4b6dde url('../../assets/special-bg-light.jpg') center / cover` | R004 | Keep current pair; superseded rows are labeled above. |
| G048 | `rgb(255, 255, 255)` → `rgb(255, 255, 255)` | R005 | Keep current pair; superseded rows are labeled above. |
| G049 | `(unset)` → `#20283d` | R010 | Keep current pair; superseded rows are labeled above. |
| G050 | `(unset)` → `#5e6a82` | R011 | Keep current pair; superseded rows are labeled above. |
| G051 | `(unset)` → `#586ca7` | R012 | Keep current pair; superseded rows are labeled above. |
| G052 | `(unset)` → `#315ac4` | R013 | Keep current pair; superseded rows are labeled above. |
| G053 | `(unset)` → `0 1px 2px rgba(13, 27, 65, 0.5)` | R015 | Keep current pair; superseded rows are labeled above. |
| G054 | `(unset)` → `F` | R016 | Keep current pair; superseded rows are labeled above. |
| G055 | `(unset)` → `url("../../assets/special-bg-light.jpg?v=20261019-button-image")` | R017 | Keep current pair; superseded rows are labeled above. |
| G056 | `CanvasText (computed rgb(255, 255, 255))` → `#20283d` | R019 | Keep current pair; superseded rows are labeled above. |
| G057 | `rgba(16, 23, 50, 0.38)` → `rgba(245, 248, 255, 0.65)` | R020 | Keep current pair; superseded rows are labeled above. |
| G058 | `rgba(171, 186, 234, 0.3)` → `rgba(88, 108, 151, 0.22)` | R021 | Keep current pair; superseded rows are labeled above. |
| G059 | `rgba(171, 186, 234, 0.18)` → `rgba(88, 108, 167, 0.14)` | R024 | Keep current pair; superseded rows are labeled above. |
| G060 | `rgba(171, 186, 234, 0.32)` → `rgba(88, 108, 151, 0.3)` | R025 | Keep current pair; superseded rows are labeled above. |
| G061 | `0 20px 50px rgba(0, 0, 0, 0.3)` → `0 20px 50px rgba(44, 61, 104, 0.075)` | R033 | Keep current pair; superseded rows are labeled above. |
| G062 | `rgb(196, 67, 67)` → `rgb(163, 52, 80)` | R037 | Keep current pair; superseded rows are labeled above. |
| G063 | `rgba(255, 255, 255, 0.1)` → `rgba(88, 108, 151, 0.16)` | R039 | Keep current pair; superseded rows are labeled above. |
| G064 | `brightness(0) saturate(100%) invert(77%) sepia(11%) saturate(1478%) hue-rotate(189deg) brightness(97%) contrast(96%)` → `F` | R044 | Keep current pair; superseded rows are labeled above. |
| G065 | `rgba(8, 12, 28, 0.55)` → `rgba(245, 248, 255, 0.55)` | R050 | Keep current pair; superseded rows are labeled above. |
| G066 | `rgba(238, 238, 238, 0.45)` → `rgb(94, 106, 130)` | R054 | Keep current pair; superseded rows are labeled above. |
| G067 | `rgba(255, 255, 255, 0.05)` → `rgba(89, 110, 167, 0.05)` | R055 | Keep current pair; superseded rows are labeled above. |
| G068 | `rgba(171, 186, 234, 0.7)` → `rgba(88, 108, 151, 0.525)` | R057 | Keep current pair; superseded rows are labeled above. |
| G069 | `rgba(16, 23, 50, 0.97)` → `rgba(245, 248, 255, 0.97)` | R059 | Keep current pair; superseded rows are labeled above. |
| G070 | `0 26px 65px rgba(0, 0, 0, 0.42)` → `0 26px 65px rgba(44, 61, 104, 0.105)` | R060 | Keep current pair; superseded rows are labeled above. |
| G071 | `rgba(238, 238, 238, 0.1)` → `rgba(88, 108, 151, 0.16)` | R077 | Keep current pair; superseded rows are labeled above. |
| G072 | `rgba(238, 238, 238, 0.4)` → `rgb(94, 106, 130)` | R078 | Keep current pair; superseded rows are labeled above. |
| G073 | `rgba(238, 238, 238, 0.13)` → `rgba(88, 108, 151, 0.16)` | R082 | Keep current pair; superseded rows are labeled above. |
| G074 | `0 20px 50px rgba(0, 0, 0, 0.08)` → `0 20px 50px rgba(44, 61, 104, 0.02)` | R083 | Keep current pair; superseded rows are labeled above. |
| G075 | `rgba(255, 255, 255, 0.035)` → `rgba(89, 110, 167, 0.035)` | R084 | Keep current pair; superseded rows are labeled above. |
| G076 | `rgba(255, 255, 255, 0.015)` → `rgba(89, 110, 167, 0.015)` | R085 | Keep current pair; superseded rows are labeled above. |
| G077 | `rgba(238, 238, 238, 0.17)` → `rgba(88, 108, 151, 0.16)` | R090 | Keep current pair; superseded rows are labeled above. |
| G078 | `rgba(255, 255, 255, 0.07)` → `rgba(89, 110, 167, 0.07)` | R093 | Keep current pair; superseded rows are labeled above. |
| G079 | `rgba(16, 23, 50, 0.55)` → `rgba(245, 248, 255, 0.55)` | R096 | Keep current pair; superseded rows are labeled above. |
| G080 | `0 15px 35px rgba(0, 0, 0, 0.3)` → `0 15px 35px rgba(44, 61, 104, 0.075)` | R097 | Keep current pair; superseded rows are labeled above. |
| G081 | `rgba(238, 238, 238, 0.15)` → `rgba(89, 110, 167, 0.15)` | R099 | Keep current pair; superseded rows are labeled above. |
| G082 | `rgba(238, 238, 238, 0.55)` → `rgba(88, 108, 151, 0.413)` | R100 | Keep current pair; superseded rows are labeled above. |
| G083 | `rgba(16, 23, 50, 0.45)` → `rgba(245, 248, 255, 0.45)` | R101 | Keep current pair; superseded rows are labeled above. |
| G084 | `0 20px 45px rgba(0, 0, 0, 0.22)` → `0 20px 45px rgba(44, 61, 104, 0.055)` | R102 | Keep current pair; superseded rows are labeled above. |
| G085 | `rgba(18, 26, 55, 0.68)` → `rgba(245, 248, 255, 0.68)` | R108 | Keep current pair; superseded rows are labeled above. |
| G086 | `rgba(150, 163, 209, 0.24)` → `rgba(88, 108, 151, 0.18)` | R109 | Keep current pair; superseded rows are labeled above. |
| G087 | `rgba(171, 186, 234, 0.35) transparent` → `rgba(88, 108, 151, 0.262) transparent` | R113 | Keep current pair; superseded rows are labeled above. |
| G088 | `rgba(8, 12, 28, 0.85)` → `rgba(245, 248, 255, 0.85)` | R115 | Keep current pair; superseded rows are labeled above. |
| G089 | `rgba(238, 238, 238, 0.86)` → `rgb(32, 40, 61)` | R119 | Keep current pair; superseded rows are labeled above. |
| G090 | `rgba(171, 186, 234, 0.62)` → `rgb(88, 108, 167)` | R120 | Keep current pair; superseded rows are labeled above. |
| G091 | `0 0 18px rgba(123, 145, 223, 0.12)` → `0 0 18px rgba(44, 61, 104, 0.03)` | R121 | Keep current pair; superseded rows are labeled above. |
| G092 | `rgba(238, 238, 238, 0.45)` → `rgba(88, 108, 151, 0.338)` | R132 | Keep current pair; superseded rows are labeled above. |
| G093 | `rgba(238, 238, 238, 0.72)` → `rgba(88, 108, 151, 0.54)` | R134 | Keep current pair; superseded rows are labeled above. |
| G094 | `rgba(238, 238, 238, 0.75)` → `rgb(94, 106, 130)` | R137 | Keep current pair; superseded rows are labeled above. |
| G095 | `rgba(153, 168, 214, 0.1)` → `rgba(225, 233, 250, 0.1)` | R185 | Keep current pair; superseded rows are labeled above. |
| G096 | `rgba(238, 238, 238, 0.35)` → `rgba(88, 108, 151, 0.262)` | R186 | Keep current pair; superseded rows are labeled above. |
| G097 | `rgba(8, 13, 32, 0.08)` → `rgba(245, 248, 255, 0.08)` | R188 | Keep current pair; superseded rows are labeled above. |
| G098 | `rgba(238, 238, 238, 0.84)` → `rgb(32, 40, 61)` | R189 | Keep current pair; superseded rows are labeled above. |
| G099 | `rgba(171, 186, 234, 0.58)` → `rgba(88, 108, 151, 0.435)` | R190 | Keep current pair; superseded rows are labeled above. |
| G100 | `0 15px 30px rgba(3, 7, 21, 0.16)` → `0 15px 30px rgba(44, 61, 104, 0.04)` | R192 | Keep current pair; superseded rows are labeled above. |
| G101 | `rgba(238, 238, 238, 0.72)` → `rgba(88, 108, 151, 0.32)` | R195 | Keep current pair; superseded rows are labeled above. |
| G102 | `rgb(208, 74, 74)` → `rgb(179, 78, 100)` | R196 | Keep current pair; superseded rows are labeled above. |
| G103 | `0 6px 16px rgba(0, 0, 0, 0.18)` → `0 6px 16px rgba(44, 61, 104, 0.12)` | R197 | Keep current pair; superseded rows are labeled above. |
| G104 | `rgb(0, 198, 117)` → `rgb(51, 139, 103)` | R198 | Keep current pair; superseded rows are labeled above. |
| G105 | `0 8px 18px rgba(0, 0, 0, 0.24)` → `0 8px 18px rgba(44, 61, 104, 0.06)` | R199 | Keep current pair; superseded rows are labeled above. |
| G106 | `linear-gradient(45deg, transparent 50%, rgba(238, 238, 238, 0.5) 50%), linear-gradient(135deg, rgba(238, 238, 238, 0.5) 50%, transparent 50%)` → `linear-gradient(45deg, transparent 50%, rgba(255, 255, 255, 0.5) 50%), linear-gradient(135deg, rgba(255, 255, 255, 0.5) 50%, transparent 50%)` | R200 | Keep current pair; superseded rows are labeled above. |
| G107 | `rgba(8, 13, 32, 0.12)` → `rgba(245, 248, 255, 0.12)` | R202 | Keep current pair; superseded rows are labeled above. |
| G108 | `rgb(0, 198, 117)` → `rgb(204, 216, 239)` | R203 | Keep current pair; superseded rows are labeled above. |
| G109 | `rgb(36, 77, 182)` → `rgb(225, 233, 250)` | R210 | Keep current pair; superseded rows are labeled above. |
| G110 | `url("../../assets/special-bg.png")` → `url("../../assets/special-bg-light.jpg?v=20261019-button-image")` | R211 | Keep current pair; superseded rows are labeled above. |
| G111 | `0 12px 24px rgba(5, 16, 62, 0.24)` → `0 12px 24px rgba(44, 61, 104, 0.06)` | R212 | Keep current pair; superseded rows are labeled above. |
| G112 | `none` → `0 1px 2px rgba(13, 27, 65, 0.5)` | R213 | Keep current pair; superseded rows are labeled above. |
| G113 | `(unset)` → `brightness(0) invert(1)` | R214 | Keep current pair; superseded rows are labeled above. |
| G114 | `rgba(238, 238, 238, 0.62)` → `rgba(88, 108, 151, 0.465)` | R216 | Keep current pair; superseded rows are labeled above. |
| G115 | `0 16px 30px rgba(5, 16, 62, 0.34)` → `0 16px 30px rgba(44, 61, 104, 0.085)` | R217 | Keep current pair; superseded rows are labeled above. |
| G116 | `rgba(150, 163, 209, 0.4)` → `rgba(88, 108, 151, 0.3)` | R218 | Keep current pair; superseded rows are labeled above. |
| G117 | `rgb(24, 34, 68)` → `rgb(245, 248, 255)` | R221 | Keep current pair; superseded rows are labeled above. |
| G118 | `rgba(171, 186, 234, 0.12)` → `rgb(234, 240, 251)` | R222 | Keep current pair; superseded rows are labeled above. |
| G119 | `rgb(28, 41, 75)` → `rgb(255, 255, 255)` | R223 | Keep current pair; superseded rows are labeled above. |
| G120 | `rgba(122, 145, 220, 0.13)` → `rgba(44, 61, 104, 0.08)` | R225 | Keep current pair; superseded rows are labeled above. |
| G121 | `rgb(255, 158, 174)` → `rgb(169, 47, 76)` | R226 | Keep current pair; superseded rows are labeled above. |
| G122 | `rgba(244, 63, 94, 0.4)` → `rgba(173, 49, 76, 0.3)` | R227 | Keep current pair; superseded rows are labeled above. |
| G123 | `rgba(244, 63, 94, 0.12)` → `rgba(173, 49, 76, 0.08)` | R228 | Keep current pair; superseded rows are labeled above. |
| G124 | `rgba(244, 63, 94, 0.2)` → `rgba(173, 49, 76, 0.15)` | R229 | Keep current pair; superseded rows are labeled above. |
| G125 | `rgba(0, 0, 0, 0.55)` → `rgba(245, 248, 255, 0.55)` | R230 | Keep current pair; superseded rows are labeled above. |
| G126 | `none for the pilot photo/upload elements` → `none` | R232 | Keep current pair; superseded rows are labeled above. |
| G127 | `not rendered on either pilot page` → `#586ca7` | R235 | Keep current pair; superseded rows are labeled above. |
| G128 | `not rendered on either pilot page` → `none` | R236 | Keep current pair; superseded rows are labeled above. |
| G129 | `currentColor (initial border color; inherits the button text color)` → `#586ca7` | R238 | Keep current pair; superseded rows are labeled above. |
| G130 | `(unset)` → `#ab3450` | R242 | Keep current pair; superseded rows are labeled above. |
| G131 | `(unset)` → `#805d15` | R243 | Keep current pair; superseded rows are labeled above. |
| G132 | `(unset)` → `#536f21` | R244 | Keep current pair; superseded rows are labeled above. |
| G133 | `(unset)` → `#246c50` | R245 | Keep current pair; superseded rows are labeled above. |
| G134 | `rgba(150, 163, 209, 0.28)` → `rgba(88, 108, 151, 0.3)` | R246 | Keep current pair; superseded rows are labeled above. |
| G135 | `rgba(255, 255, 255, 0.04)` → `rgba(255, 255, 255, 0.38)` | R247 | Keep current pair; superseded rows are labeled above. |
| G136 | `rgba(171, 186, 234, 0.28)` → `rgba(88, 108, 151, 0.21)` | R255 | Keep current pair; superseded rows are labeled above. |
| G137 | `0 14px 30px rgba(0,0,0,.34)` → `0 14px 30px rgba(44,61,104,.085)` | R257 | Keep current pair; superseded rows are labeled above. |
| G138 | `rgba(3, 8, 24, 0.58)` → `rgba(44, 61, 104, 0.3)` | R265 | Keep current pair; superseded rows are labeled above. |

## Exceptions and owner list

Keep all shadow-only declarations, blur, animations, layout/native-control state and element-specific pairs in place. Do not move the generic dropdown/mobile/appearance rules in the pilot. Preserve existing dialog variables whose values do not match a proposed pair. An exact duplicate dark value is insufficient when its light value differs.

| Family requiring an owner decision before any merging | Current distinct values / behavior | Pilot decision |
| --- | --- | --- |
| Muted text opacity | `rgba(238,238,238,0.55)`, `.57`, `.58`, `.6`, `.65`, `.74`, `.75` all appear in the pilot and often become `#5e6a82` in light mode. | Keep each dark opacity. Only `.55` and `.6` get their own justified variables. |
| Pale border opacity | `rgba(238,238,238,.12/.13/.14/.17/.18)` often become the same `rgba(88,108,151,.16)`. | `.14` and `.18` remain separate variables (`--line-soft` / `--line-default`); other pairs stay inline. |
| Subtle fills | White `.015/.018/.025/.035/.04/.05/.06/.07/.08` map to correspondingly distinct blue opacities. | Never round or merge; propose only recurring `.025`, `.04`, `.06` pairs. |
| Nearby light border alpha | Source values include `.195`, `.21`, `.262`, `.3`, `.315`, `.338`, `.413`, `.435`, `.465`, `.48`, `.525`, `.54`. | Preserve the source decimals even if Chromium serializes some differently. |
| Requested opacity example | The owner’s `.375` / `.38` example is not a shared pilot pair. Documents has a light selected-row border with `.375`; the pilot has `.38` surfaces with a different RGB color and role. | Do not infer interchangeability; the full cross-page owner list belongs to Step 4. |
| Same green, different roles | Dark `#00c675` → light `#338b67` for on-toggle; dark `#00c675` → light `#ccd8ef` for theme highlight. | Keep separate; no shared success token here. |
| Same white fill, different components | Dark `rgba(255,255,255,.04)` → light blue `.04` in the proposed fill pair, but → white `.38` for the generic dropdown button. | Apply by exact pair, never by searching the dark literal alone. |
| Icon filters | White `brightness(0) invert(1)`, the longer pale filter, and the active-nav filter are different dark filter strings but share a light filter. | Tokenize only the first pair. Preserve both longer filters unchanged. |

These are decisions to consider later, not prerequisites for preserving the current design. The pilot proposal merges none of them.

## Planning estimate and verification

- The proposed pairs appear to fully cover **37 / 97 scoped theme rules (38.1%)**, and **67 / 209 expanded scoped declarations**. This is source-inventory coverage, not a completed removal claim. The pilot must still meet the owner’s roughly 25% rule and achieve zero computed-style/pixel differences before proceeding.
- `theme.css`: **191,899 → 191,899 bytes** (saving **0**, no CSS edits). Total first-party CSS: **698,489 bytes**. Existing baseline counts: **3,310 hardcoded colors / 1,560 var() uses**. No simplification is claimed at this inventory checkpoint.
- All **268 application-file SHA-256 hashes are unchanged** from the starting commit. `tests/source_equivalence.py` passes **91 PHP token streams / 35 JS ASTs / 0 intentional changes**; every file status is `unchanged`.
- The read-only source parser was cross-checked against Chromium CSSOM for **27 sheets**. Counts agree after accounting for three Firefox-only progress rules that Chromium rejects, all outside the pilot scopes.
- A saved-HTML replay with current stylesheet order and disabled scripts/motion confirms shared nav text pairs on Home/Settings/Documents and the different map palette. This is inventory evidence, not a claim that the runtime/browser suites or visual gates were rerun.
- No visual/runtime/API/SQL/install/translation suite was run for this no-code checkpoint. For the pilot, compare non-custom computed properties and pixels on both themes at 1440/390, resting and open menus/dialogs; require zero differences, parsed scripts and no page-console errors. Custom properties may differ as explicitly allowed by the revised plan.
- The full 636-state matrix, theme/i18n/dropdown suites and metrics-script run remain scheduled once at the end of Phase 2. No screenshot gallery was created.

**Pause:** Step 1 is complete. Review this table and the 12-variable proposal before Step 2 edits. No rollout or commit has been made for application code.
