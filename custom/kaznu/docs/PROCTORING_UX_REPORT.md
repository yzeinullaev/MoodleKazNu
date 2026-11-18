# PROCTORING UX REPORT

Date: 2026-08-07  
Site: https://farabischool.kz  
Plugin: `local_proctopus` **0.3.1-prep-ux** (`2026081002`)  
Access rule: `quizaccess_proctopus` **0.3.1-prep-ux**  
Support: `local_kaznu` **1.1.5** (hide trophy on prep/launch)

---

## UI flow

```
Quiz landing
  → «Подготовиться к тесту» → /local/proctopus/check.php?cmid=…
  → «Проверить оборудование» (local checks only)
  → «Перейти к прокторингу» → POST launch.php (real Farabi Proctoring)
  → admission return → quiz «Начать попытку»
  → attempt (compact «Прокторинг активен»)
  → summary / submit
  → complete.php («Тест завершён») + course / кабинет
```

Proctopus is **not** started from the plain «Начать тест» gate anymore; students go through `check.php` first.

---

## Delivered

| Item | Status |
|------|--------|
| Preparation page | `/local/proctopus/check.php?cmid=201` |
| Stepper | Check → Proctoring → Test → Finish |
| Camera card | FA video + status (pending / checking / ready / action / fail) |
| Microphone card | FA mic + local browser permission honesty |
| Screen card | capability only (no forced share dialog) |
| Connection card | online + same-origin ping |
| Extension card | honest “full check in Farabi Proctoring after continue” (not faked as active) |
| Check button | «Проверить оборудование» |
| Provider button | «Перейти к прокторингу» only after local pass |
| Start test button | Moodle attempt after admission (unchanged, after return) |
| Attempt indicator | compact shield + «Прокторинг активен» |
| Summary | focus-safe; no full readiness panel |
| Completion page | success icon + checklist + course / dashboard |
| Error states | provider / camera / mic / HTTPS / browser / connection (lang) |
| Mobile | stacked cards, full-width CTA, compact stepper (CSS) |
| Accessibility | FA icons + text; `aria-live` banner; status text not colour-only; button roles |
| RU / KK / EN | student strings |
| Achievements hidden | prep + launch (+ attempt/summary via existing focus); **complete shows trophy again** |
| Core modified | **No** |
| JWT / secret / start | **unchanged** (live flags preserved) |

---

## Files changed

- `local_proctopus/check.php` (new)
- `local_proctopus/check.js` (new)
- `local_proctopus/launch.php` (GET → check; POST after prep)
- `local_proctopus/complete.php` (finish UX polish)
- `local_proctopus/lib.php` (stepper, gate panel, chips)
- `local_proctopus/styles.css` (prep cards/stepper/mobile)
- `local_proctopus/lang/en|ru|kk/local_proctopus.php`
- `quizaccess_proctopus/rule.php` + lang en/ru/kk
- `local_kaznu/locallib.php` + `styles.css` + `version.php`

---

## Browser QA

| Step | Result |
|------|--------|
| Login as test student | ok (session already active) |
| Preparation page | title/subtitle, 5 cards, stepper current «Проверка» |
| Equipment check | camera/mic/screen/connection **Готово**; extension «Требуется действие» (honest) |
| CTA after pass | **«Перейти к прокторингу»** + «Повторить проверку» |
| Quiz landing | **«Подготовиться к тесту»** gate panel (not Launch/JWT/stub labels) |
| Complete page | «Тест завершён», checklist, course/cabinet; **Достижения** visible |
| Screenshots | captured prep (pending + ready mobile stack) + complete |
| Full provider launch E2E | not re-run in this task (CTA only posts to existing launch) |

Screenshots (agent captures under Cursor temp screenshots for this session):

- prep ready / mobile stack with «Перейти к прокторингу»
- complete page structure validated via a11y snapshot

---

## Remaining issues

1. Automated browser permission environment may differ from student Chrome with extension installed — still pass local checks here.
2. Quiz module intro still contains legacy admin note («Stub proctoring…») — course content, out of this UI scope.
3. Student has no free attempt left on quiz 22 — full new attempt + live attempt-bar screenshot deferred until next attempt available or admin raises attempts.
4. Optional: after `attempt_submitted`, soft-link to `complete.php` when Moodle review is closed (finish-flow already covers sessions).

---

## Production

- Deployed: backup `/var/backups/proctopus_ux_20260807_074059`
- `enabled=1` `live=1` secret length 17
- Allowlist CMID **201** only

**FINAL UX STATUS: PASS** (prep + complete + lang + gate routing verified live)

Full camera/extension in Farabi Proctoring remains provider-side after «Перейти к прокторингу».
