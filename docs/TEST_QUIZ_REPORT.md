# TEST QUIZ REPORT

**Date:** 2026-08-06  
**Site:** https://farabischool.kz  
**Scope:** Isolated hidden test course/quiz + disabled Proctopus plugins  
**Site-wide proctoring:** **OFF** (`proctopus_enabled=0`)

---

## IDs

| Item | Value |
|------|--------|
| **Course ID** | **24** |
| **Course shortname** | `PROCTOPUS_TEST` |
| **Course fullname** | PROCTOPUS TEST |
| **Course visible** | **0** (hidden) |
| **Quiz ID** | **22** |
| **Quiz name** | Proctoring Test |
| **CMID** | **201** |
| **Quiz URL** | https://farabischool.kz/mod/quiz/view.php?id=201 |
| **Course URL** | https://farabischool.kz/course/view.php?id=24 |

---

## Access

| Who | Role on course |
|-----|----------------|
| **admin** (id=2) | editingteacher + student |
| **proctopus_test_student** (id=40) | student |

Password for test student is set **on create only** via CLI constant (not reprinted here).  
(Rotate if previously shared outside ops.)

---

## Rule / flags

| Item | Status |
|------|--------|
| **Rule attached** | **Yes** — `quizaccess_proctopus.proctopusrequired=1` for quiz **22 only** |
| **Other quizzes with rule** | **0** |
| **Enabled (site)** | **`proctopus_enabled=0`** |
| **Live calls** | **`proctopus_live_calls_enabled=0`** |
| **JWT secret** | empty |
| **Plugins installed** | `local_proctopus` + `quizaccess_proctopus` **2026080802** (required for rule table; left disabled) |

While site enable is **0**, access rule `make()` returns null → Quiz uses **normal** Moodle free start (rule present but inactive).

---

## Verification (with enable still OFF)

| Check | Result |
|-------|--------|
| **JWT generated** | **Yes (offline)** — HS256 encode + claims OK |
| **Session created** | **No** (launch requires enable=1; sessions count=0) |
| **Redirect / signed return** | **Offline stub path OK** (p2 smoke: stub start + state token verify) |
| **Moodle start attempt via flow** | **Not exercised** — needs enable=1 + student UI |
| **Duplicate protection** | Code path present; **not live-exercised** |
| **Questions** | **3** true/false in slots |
| **Attempts** | **0** |
| **Real HTTP to Proctopus** | **None** |
| **p2_smoke** | **DONE** (enabled=0, live=0, REAL_HTTP=0) |

---

## Enable / rollback instructions

**Path on server:** `/var/www/moodle/local/proctopus/docs/TEST_QUIZ_ENABLE_ROLLBACK.md`  
**Repo:** `local_proctopus/docs/TEST_QUIZ_ENABLE_ROLLBACK.md`

### Quick enable (stub only — needs separate APPROVE for production enable=1)

1. Site admin → Local plugins → Proctopus: `proctopus_enabled=1`, **live=0**, secret empty, debug=0  
2. Confirm only this Quiz has Require proctoring = Yes  
3. Login as `proctopus_test_student` → open quiz → launch CTA → stub return → attempt  
4. After test: set `proctopus_enabled=0` again  

### Rollback

1. `proctopus_enabled=0`, `live_calls=0`  
2. Quiz: Require proctoring = No  
3. Optional delete/hide course 24  

---

## Errors

| Issue | Handling |
|-------|----------|
| First setup: question category insert failed | Fixed via `add_test_quiz_questions.php` — 3 slots created |
| First upgrade: quizaccess XMLDB missing COMMENT | Fixed install.xml; plugin installed |
| p2_smoke context_module on synthetic cm | Hardened `legacy_payload_builder` fallback for smoke |
| sendmail missing on enrol emails | Non-fatal warning only |

---

## APPROVAL REQUIRED (next)

1. Set **`proctopus_enabled=1`** on production for stub E2E on this quiz only (live remains 0)  
2. Live Proctopus + rotated secret  
3. Callback rewrite / faculty filter ON  

**Stopped.** Site proctoring remains **disabled**. Live calls remain **off**.
