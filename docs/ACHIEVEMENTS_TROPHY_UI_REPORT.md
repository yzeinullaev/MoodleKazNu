# ACHIEVEMENTS TROPHY UI REPORT

**Date:** 2026-08-07  
**Plugin:** `local_kaznu` **1.1.4-achievements-trophy** (`2026080814`)  
**Production:** **deployed** to farabischool.kz (trophy panel code)

---

## Component

`local_kaznu_render_achievement_panel()` + `local_kaznu_achievement_panel_js()` in `locallib.php`  
HUD wiring: `lib.php` (unchanged call sites)  
Styles: `styles.css`

---

## Icon

Moodle `$OUTPUT->pix_icon('i/badge', …)` when available, else Font Awesome `fa-trophy`.  
No emoji.

---

## Collapsed

- Circular **48px** dark FAB only  
- Icon only (no «Достижения», no Lv, no XP text on button)  
- `aria-label` + `title` = localized «Достижения»  
- `aria-expanded=false`  
- Default **collapsed**

Verified in Chrome E2E on quiz landing:

- `collapsed: true`  
- `hasLvInBtn: false`  
- `hasIcon: true`

---

## Expanded

Panel above FAB:

- Title + close (×)  
- `Lv N · X XP`  
- Rank / progress bar / links (body)  
- Close or FAB click collapses  
- `localStorage` key: **`farabi_achievements_open`** = `1|0`  
- Migrates legacy `farabi_achievements_collapsed`

---

## Tooltip / keyboard / a11y

| Item | Status |
|------|--------|
| Tooltip (`title`) | YES — panel label |
| Keyboard | native `<button>` (Enter/Space) |
| Focus | `:focus-visible` coral outline |
| `aria-controls` / `aria-expanded` | YES |
| Close button `aria-label` | YES |

---

## Platform

| Item | Status |
|------|--------|
| Desktop | Fixed bottom-right FAB + expandable card |
| Mobile | Safe-area inset bottom-right |
| Quiz attempt/summary/process | Still **hidden** (no HTML + CSS) |
| Overlap | z-index 1050; pointer-events none on container so non-panel area doesn’t block content |

---

## Files changed

| File | Change |
|------|--------|
| `kaznu/locallib.php` | Trophy FAB + expanded head/close + open key |
| `kaznu/styles.css` | FAB / expanded panel styles |
| `kaznu/lang/{en,ru,kk,zh_cn}/local_kaznu.php` | `achievements_panel_label`, `achievements_close` |
| `kaznu/version.php` | 2026080814 / 1.1.4 |
| `kaznu/tests/achievements_ui_test.php` | asserts icon + open key + no Lv on toggle |

---

## Core / Proctopus

| Item | Result |
|------|--------|
| Moodle core modified | **NO** |
| Proctopus plugins | **NO** (this part) |
| Production changed | **YES** — `local_kaznu` trophy UI deployed |
| Errors | None observed for FAB |

---

## Languages

RU / EN / KK / ZH strings for label + close added.
