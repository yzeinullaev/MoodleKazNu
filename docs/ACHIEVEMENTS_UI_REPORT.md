# ACHIEVEMENTS UI REPORT

**Date:** 2026-08-06  
**Scope:** workspace only — **production not changed**

---

## Component

`local_kaznu_render_achievement_panel()` in `locallib.php`  
Key: `farabi_achievements_collapsed` (`1` collapsed / `0` expanded)

Helper: `local_kaznu_is_quiz_focus_page()` via `$PAGE->pagetype` prefixes:
- `mod-quiz-attempt`
- `mod-quiz-summary`
- `mod-quiz-processattempt`
- `mod-quiz-review`

---

## Files changed

| File | Change |
|------|--------|
| `kaznu/locallib.php` | panel renderer, quiz-focus helper, quiz XP panel uses panel |
| `kaznu/lib.php` | body class, HUD collapsed, skip gamification HTML in quiz focus |
| `kaznu/styles.css` | compact header ~56–64px, expand body, CSS fallback hide |
| `kaznu/lang/{en,ru,kk,zh_cn}/local_kaznu.php` | a11y / Lv XP strings |
| `kaznu/version.php` | **2026080811** / `1.1.1-achievements-collapse` |
| `kaznu/tests/achievements_ui_test.php` | PHPUnit for focus detection + panel a11y |

---

## Default state

| Item | Value |
|------|--------|
| Default | **collapsed** (desktop + mobile) |
| LocalStorage | `farabi_achievements_collapsed` — try/catch; missing → collapsed |
| Accessibility | real `<button>`, `aria-expanded`, `aria-controls`, `aria-label`, chevron, `focus-visible` |
| Desktop / Mobile | same compact fixed HUD when not quiz focus |

---

## QUIZ MODE

| Page | Achievements HTML |
|------|-------------------|
| **Attempt** | **not rendered** |
| **Summary** | **not rendered** |
| **Processattempt** | **not rendered** |
| **Review** | **not rendered** (clean attempt flow; toast after award deferred if still on focus page) |
| **Quiz view (intro)** | collapsible panel allowed (reward rules) |
| CSS fallback | `body.local-kaznu-quiz-focus … display:none` |

Also hidden on focus pages: sticky HUD, lesson strip, lesson activity nav (keep Moodle quiz chrome).

---

## XP logic

| Item | Status |
|------|--------|
| Awarding | **unchanged** |
| Duplicate | still badge `quiz:{id}` path |
| Celebrate toast | skipped while quiz-focus (kept in session for next normal page) |

## Proctopus impact

None — no proctopus files touched. Attempt page free of XP chrome.

---

## Smoke / browser

| Item | Status |
|------|--------|
| PHPUnit prepared | yes (`achievements_ui_test.php`) |
| PHPUnit executed | **no** (no Moodle core in workspace) |
| Browser | not run against production |
| Production changed | **NO** |
| Approval required | deploy `local_kaznu` **2026080811** to production |

---

## After deploy checklist

1. Course / activity page → collapsed “Достижения · Lv · XP”  
2. Toggle expand/collapse + refresh persistence  
3. Keyboard focus on button  
4. Open quiz attempt → no achievements in HTML/DOM  
5. Submit attempt → XP still awards once  
6. Proctopus stub quiz still clean  
