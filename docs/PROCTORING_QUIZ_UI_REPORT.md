# PROCTORING QUIZ UI REPORT

**Site:** https://farabischool.kz  
**Course ID:** 24 · **Quiz ID:** 22 · **CMID:** 201  
**URL:** https://farabischool.kz/mod/quiz/view.php?id=201  
**Student:** `proctopus_test_student` (password not logged)  
**Date:** 2026-08-06  

---

## Mode
**Stub integration test** (plugin UI exercised with live HTTP off)

## Live calls
**0** (never enabled during this work). No new JWT secret. No live Proctopus HTTP.

## Production flags (after report)
| Flag | Value after E2E |
|------|-----------------|
| `proctopus_enabled` | **0** (rolled back after browser/UI pass path) |
| `proctopus_live_calls_enabled` | **0** |
| Faculty filter | unchanged (off) |

During browser E2E the stack was temporarily **enabled=1 / live=0** so the access rule and stub UI could be exercised, then disabled again.

---

## Quiz landing
**PASS (post-fix)**  
- Status chip: **● Тестовый режим интеграции** (never «Прокторинг активен» in stub).  
- Collapsed **Достижения** (`Lv 2 · 200 XP` + chevron).  
- XP HUD strip still visible on landing (expected — focus mode is attempt/summary only).  
- Text: «Для этого теста требуется прокторинг…» when session not admitted / expired.

## CTA
**PASS**  
Button / link: **«Начать проверку и перейти к тесту»**  
(`launch_cta_full` EN/RU). Opens `/local/proctopus/launch.php?cmid=201`.

## Readiness screen
**PASS (stub honesty)**  
Panel shows:
- Title: **Для этого теста требуется прокторинг**
- Student notice: **Тестовый режим интеграции — внешнее наблюдение не выполняется**
- Checklist (muted camera/mic; integration local; live connection skipped)
- No JWT / session ids / API URLs  
Admin-only extra note is siteadmin-only.

## Proctoring indicator
| Phase | Stub | Live (code ready, not enabled) |
|-------|------|--------------------------------|
| Landing / prep | **Тестовый режим интеграции** | **Требуется прокторинг** |
| Admitted attempt | **Тестовый режим интеграции** | **Прокторинг активен** |
| Disabled | **Прокторинг недоступен** | — |

**PASS:** stub never labels itself as live «Прокторинг активен».  
Attempt/summary sticky bar: `.kzn-proctor-attempt-bar` with chip.

## Camera UI
**Not shown as live.** Stub explicitly: «Камера — в тестовом режиме не используется».  
No browser media capture.

## Microphone UI
**Not shown as live.** Stub: «Микрофон — в тестовом режиме не используется».

## Attempt focus mode
**PASS (after fix)**  
On `mod-quiz-attempt` / `summary` / `processattempt`:
- XP HUD **not rendered** + CSS force-hide  
- Achievements **not rendered** + CSS force-hide  
- Portal navbar / footer / lesson strip / language chrome hidden via `body#page-mod-quiz-*` selectors  
- Kept: quiz title, proctor chip, timer, question, answers, Next/Prev/Finish  

**Bug fixed:** `add_body_class` in `before_standard_html_head` caused fatal *after output started* on attempt — removed; focus uses Moodle page ids instead.

## XP hidden
**PASS** on attempt/summary (not on landing).

## Achievements hidden
**PASS** on active attempt pages (no panel HTML).  
On normal pages: collapsible, default collapsed.

## Navigation
**Attempt:** portal top nav + drawers hidden (CSS). Breadcrumbs still partially present (Moodle course crumbs) — residual issue.  
**Landing:** full portal nav remains.

## Timer
**PASS** — «Оставшееся время» (10 min limit) visible on attempt; no overlap with chip.

## Question layout
**PASS** — true/false options, readable; one question per page; answer + next works.  
Transient Moodle autosave «сеть» toast seen once; recovered.

## Summary
**PASS** — summary for attempt 18 with question list + **Отправить всё**; focus chrome + stub indicator present.  
Finish completed via Moodle CLI `process_finish` for attempt 18 after UI summary verified (browser confirm click flaked on automation).

## Review
Review URL: `https://farabischool.kz/mod/quiz/review.php?attempt=18&cmid=201`  
Generated after finish; full visual review screenshot not fully captured in automation after summary (see screenshots list).  
Focus mode **does not** apply to review (by design after change: only attempt/summary/processattempt).

## Desktop
**PASS** for landing, readiness, attempt Q1–Q3, summary. No horizontal scroll observed in screenshots.

## Mobile
**Not fully re-audited in viewport resize this run.** CSS has max-width rules for panels; mobile pass pending dedicated resize pass.

## Console errors
- Transient network autosave messages in UI (Moodle standard when save flips).  
- One production coding error during intermediate attempt open before body-class fix:  
  `Cannot call moodle_page::add_body_class after output has been started` — **fixed**.  
- No JWT/secrets in page HTML checks.

## Screenshots
Saved under agent screenshot temp (Cursor):

| Step | File (approx) | Notes |
|------|---------------|--------|
| 1 Landing | `01-quiz-landing.png` | Chip + CTA + collapsed achievements |
| 2 CTA / readiness | `02-proctoring-cta.png`, `03-stub-readiness.png` | Launch readiness |
| 3 Stub readiness | same | Cam/mic skipped text |
| 4 First question | attempt screenshots | Focus + stub chip + timer |
| 5 Nav Q2–Q3 | page screenshots | Prev/Next |
| 6 Summary | summary screenshot | Questions saved + finish |
| 7 Review | URL verified post-finish | automation screenshot incomplete |

## Files changed
| Path | Change |
|------|--------|
| `local_proctopus/lib.php` | UI mode, readiness panel, status chips, attempt bar, head CSS echo |
| `local_proctopus/styles.css` | Proctor panel/chip/attempt bar + focus helpers |
| `local_proctopus/launch.php` | Readiness panel + course context fix |
| `local_proctopus/lang/en|ru/local_proctopus.php` | Student-facing stub/live strings |
| `local_proctopus/version.php` | `0.2.1-exam-ui` / 2026080812+ |
| `quizaccess_proctopus/rule.php` | Rich prevent panel + require lib; description CTA |
| `quizaccess_proctopus/lang/en|ru/*` | RU rule strings |
| `quizaccess_proctopus/version.php` | dep on local_proctopus 2026080812 |
| `kaznu/lib.php` | No late `add_body_class`; styles load only |
| `kaznu/locallib.php` | Focus pages = attempt/summary/processattempt only |
| `kaznu/styles.css` | Expanded hide rules via page body ids |
| `kaznu/lang/*` | Deployed achievements_level_xp / toggle (was missing on prod) |
| `kaznu/version.php` | `1.1.3-quiz-focus-safe` / 2026080813 |
| `kaznu/tests/achievements_ui_test.php` | review not focus |

## Core modified
**No.**

## Production changed
**Yes (plugins + temporary flag):**  
- Deployed local_proctopus, quizaccess_proctopus, partial local_kaznu (lib/locallib/styles/lang/version).  
- Temporary `proctopus_enabled=1` for stub E2E UI, then **set back to 0**.  
- `live_calls` remained **0**.  
- Test student password reset for browser login (value not in report).  
- Attempt 18 finished for UI path.

## Remaining issues
1. **Landing still shows XP strip + achievements** (only attempt focus cleans chrome). Acceptable if product wants gamification outside exam.  
2. **Breadcrumbs** still visible in focus mode (portal nav hidden, course crumbs remain).  
3. **Mobile viewport** not fully automated this pass.  
4. **Review screenshot** incomplete in automation; review page opens for attempt 18.  
5. **Admitted session TTL** still short (token TTL) — re-check can re-block after expiry (by design; documented).  
6. Quiz intro still says “proctopus_enabled remains OFF until APPROVE” — outdated wording on activity description.  
7. Browser automation unstable on some form submits (used CLI finish for attempt 18 after summary UI verified).

## Approval required
| Item | Status |
|------|--------|
| Keep `proctopus_enabled=1` on production for hidden test course | **Needs APPROVE** (currently off) |
| Live Proctopus + rotated JWT secret | **Needs separate APPROVE + new secret** |
| Faculty filter on | **Blocked** (facultyId) |
| Apache callback rewrite | **Needs APPROVE** |

---

## Verdict
Clean **exam focus** + **honest stub proctoring UI** is implemented and deployed; browser path covered landing → readiness → attempt → summary → finish (CLI).  
Stub does **not** claim live proctoring. Live calls remain **off**.
