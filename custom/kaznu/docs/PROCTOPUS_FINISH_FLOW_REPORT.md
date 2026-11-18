# PROCTOPUS FINISH FLOW REPORT

Date: 2026-08-07  
Site: https://farabischool.kz  
Scope: Course 24 / Quiz 22 / CMID 201 only  

---

## Analysis of prior manual runs

| Field | Value |
|--------|--------|
| Student | `proctopus_test_student` (userid 40) |
| Latest finished attempt | id=18, state=**finished**, sumgrades=2.0 |
| Older finished attempts | id=16, id=13 |
| Callbacks before fix | **0** |
| Local sessions before fix | mostly `expired` / `failed`; **no completed linking** on finish |
| Browser after finish (prior) | Moodle review; admitted sessions could stay open (admin leftover 17–19) |
| Provider finish API | **Not used** — legacy start only; extension self-closes on normal leave |

Pre-existing finished attempts **not rewritten**.

---

## Implementation

### Moodle attempt event
| Item | Value |
|------|--------|
| Moodle attempt event | `\mod_quiz\event\attempt_submitted` |
| Also | `\mod_quiz\event\attempt_started` (bind attemptid), `\mod_quiz\event\attempt_becameoverdue` (if state finished) |
| Observer | `\local_proctopus\observer` (`db/events.php`) |
| Attempt state gate | only when `quiz_attempts.state = finished` |
| Grade | untouched by Proctopus (Moodle-only) |

### Session close
| Item | Value |
|------|--------|
| Service | `proctoring_session_reconciler` |
| On finish | admitted/started/created/… → `completed` or `completed_pending_result` |
| Fields | `completed_at`, `attemptid`, safe `result_summary` (e.g. `moodle_quiz_finished`) |
| Idempotent | yes (repeat finish / observer safe) |
| Stale duplicates | `expired` / `superseded` (no DELETE) |
| Post-finish active count | **must be 0** for userid+cmid |

### Callback
| Item | Value |
|------|--------|
| Callback received (prior live) | no |
| After finish + later callback | `completed_pending_result` → `completed` + safe code/summary |
| Callback before Moodle finish | store result fields; **do not** auto-finish Moodle attempt |
| Grade from callback | never |

### Provider finish
| Item | Value |
|------|--------|
| Finish API required | **No** |
| Provider close behavior | local session closed; redirect to normal Moodle/Farabi pages; extension uses its own lifecycle |

### Post-finish UX
| Item | Value |
|------|--------|
| Complete page | `/local/proctopus/complete.php?cmid=…` |
| Content | Exam finished; answers sent; attempt saved; proctoring ended; grade if Moodle review marks allow; course / dashboard / optional review |
| Review page | **normal mode** (no proctor bar; not “active proctoring”) |
| Focus / sticky bar | only if **inprogress attempt + admitted session** |
| Achievements | allowed again off attempt pages (kaznu review was already non-focus) |

### Reconcile / admin
| Item | Value |
|------|--------|
| Scheduled task | `\local_proctopus\task\reconcile_sessions` every 20 min (local DB only) |
| CLI dry-run | `php local/proctopus/cli/reconcile_sessions.php --dry-run` |
| CLI apply | `php local/proctopus/cli/reconcile_sessions.php --apply` |
| Filters | `--userid=` `--cmid=` `--older-than=` |
| Events | `proctoring_exam_completed`, `proctoring_result_received` (no JWT / PII / raw body) |

### access rule
- `prevent_access` reconciles first; **no PHP-session admit without live DB admitted**
- Reopen finished quiz does **not** reuse completed session

---

## Live verification (server, 2026-08-07)

| Check | Result |
|--------|--------|
| Plugin version | `0.3.0-finish-flow` / `2026081001` |
| upgrade | OK (local_proctopus + quizaccess_proctopus) |
| Live flags preserved | enabled=1, live=1, secret_len=17 |
| JWT / secret / start path | **unchanged** |
| Stale admin sessions 17–19 (cmid 201) | **expired** via reconcile apply |
| Simulated finish (admitted → finished attempt 18) | `completed_pending_result`, open=0 |
| Duplicate finish | no re-open |
| Callback after finish | `completed` + code=ok |
| Observer finish path | closes session |
| Wrong userid finish | does not close owner session |
| user_has_admitted after finish | **0 / false** |
| Attempt indicator after finish | **empty** |
| Scheduled task registered | **1** |
| Core modified | **No** |
| Other quizzes | allowlist still **201 only** |

### Manual browser acceptance (extension / camera)

Must be confirmed in one real student pass after this deploy:

1. Proctopus → admit → Quiz → questions → «Отправить всё и завершить тест»  
2. attempt=finished, grade saved  
3. session completed / completed_pending_result  
4. active bar & focus gone; header/nav back  
5. extension/camera self-stop (provider)  
6. complete/review; course + cabinet OK  
7. refresh safe; reopen quiz does not reuse old session  

*(Prior automated extension E2E was not re-run in this task; student manual path remains the acceptance gate for #4–7.)*

---

## PHPUnit

| Suite | Location |
|--------|----------|
| finish_flow_test | `local/proctopus/tests/finish_flow_test.php` |
| Cases | finish closes admitted; 0 active; idempotent; wrong user; no reuse completed; stale expire + keep inprogress; callback after finish; no callback UX terminal; dry-run no write; apply expires; history preserved; attempt_started link; observer |

**Browser E2E automation:** not re-run this cycle (leave for one live student attempt).  
**PHPUnit on host:** not executed if site has no full phpunit bootstrap (`NO_PHPUNIT` typical on prod); tests shipped for CI/local Moodle.

---

## Errors / recovery

- First deploy script with CRLF briefly left only empty `cli/`+`docs` under `local/proctopus`  
- **Restored** from `/var/backups/proctopus_finish_20260807_072605` then re-applied plugin  
- Observer called non-existent `local_proctopus_plugin_enabled` → fixed to `local_proctopus_is_enabled`

---

## FINAL STATUS: **PARTIAL**

**PASS** for server finish-flow, reconcile, zero open sessions, session UX gates, deploy, secrets/start intact.  

**PENDING** one full manual student + Proctopus extension finish on CMID 201 to certify camera indicator and post-finish browser chrome in production.

Keep test Quiz 22 / CMID 201 live and ready; **do not** enable other quizzes without separate APPROVE.
