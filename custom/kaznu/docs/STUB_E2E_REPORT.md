# STUB E2E REPORT

**Date:** 2026-08-06  
**Site:** https://farabischool.kz  
**Scope:** Test quiz only (`PROCTOPUS_TEST` / quiz 22 / cmid 201)  
**Live Proctopus HTTP:** **none** (`proctopus_live_calls_enabled=0` throughout)

---

## Flags

| Step | Value |
|------|--------|
| During E2E | `proctopus_enabled=1`, `live_calls=0` |
| After E2E (rollback) | **`proctopus_enabled=0`**, **`live_calls=0`**, secret empty |

---

## Results

| Field | Status |
|--------|--------|
| **CTA** | **PASS** — `prevent_access` blocks free start; message includes `/local/proctopus/launch.php?cmid=201` |
| **Launch** | **PASS** — stub launch as `proctopus_test_student` |
| **JWT** | **PASS** — encoded in memory with offline stub secret; **not** in session DB row; no JWT column |
| **Session** | **PASS** — local session id=3 (final run) status → `started` → `admitted` |
| **Redirect** | **PASS** — `https://farabischool.kz/local/proctopus/return.php?state=[redacted]` (own host / allowlisted) |
| **Return** | **PASS** — signed state verified; session **admitted** |
| **Attempt** | **PASS** — Moodle API `quiz_prepare_and_start_new_attempt` → attempt **id=13**; **no** hand `quiz_attempts` insert |
| **Grade** | **PASS** — attempt finished, `sumgrades=3.00000`, best grade **10**/10 |
| **Duplicate attempt** | **PASS** — relaunch admitted path; still **1 session**, **1 attempt** |
| **Logs** | **PASS** — no JWT pattern in recent `logstore_standard_log` for local_proctopus / eyJ |
| **Errors** | **none** (final run EXIT=0) |
| **Rollback** | **PASS** — `enabled=0`, `live=0` verified post-run |

---

## Fixes applied during E2E (kept)

1. **`quizaccess_proctopus/rule.php`** — Moodle 4.5 base class API (`mod_quiz\local\access_rule_base`, `$this->quizobj->get_cmid()`).  
2. Rebuilt test questions with valid **`truefalse`** qtypes (prior empty `qtype` caused missingtype).  
3. E2E CLI: `local/proctopus/cli/stub_e2e_test_quiz.php`.

---

## Artifacts

- Final attempt: quiz=22, userid=40, attempt=13, state=finished  
- Final proctor session: id=3, status=admitted, attemptid NULL (bridge does not require bind for grade)  
- Only quiz with `proctopusrequired=1`: **22**

---

## APPROVAL still required for

- Live `proctopus_live_calls_enabled=1` + rotated secret  
- Apache callback rewrite  
- Faculty filter ON  
- Keeping `proctopus_enabled=1` on production beyond stub tests  

**Production:** proctoring **disabled** again after E2E.
