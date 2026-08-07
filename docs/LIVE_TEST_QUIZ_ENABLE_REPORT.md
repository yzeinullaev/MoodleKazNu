# LIVE TEST QUIZ ENABLE REPORT

**When:** 2026-08-07  
**Site:** https://farabischool.kz  
**Action:** Enable live Proctopus for test quiz only — **no automation**, await human manual Chrome test  
**Backup:** `/var/backups/proctopus_live_20260806_194116/settings_enable_manual_20260807_080027.json`

---

## LIVE TEST QUIZ ENABLE REPORT

| Item | Value |
|------|--------|
| **proctopus_enabled** | **1** |
| **live_calls** | **1** |
| **legacy_mode** | **1** |
| **secret configured** | **YES** (Moodle plugin config; not printed) |
| **accountName** | `farabi_business` |
| **start endpoint** | `https://farabi-proctoring.kaznu.kz/integration/simple/farabi_business/start/` |
| **callback** | `https://farabischool.kz/proctoring/proctopus/result` |
| **CMID allowlist** | **201 only** (202 denied by gate) |
| **Quiz 22 rule** | `proctopusrequired=1` |
| **Other quizzes affected** | **NO** (only quiz 22 has required rule) |
| **Course 24 hidden** | **YES** (`visible=0`) |
| **JWT algorithm** | HS256 (payload/structure unchanged) |
| **JWT logging** | Safe log path only (correlation / status / ms); no full URL+token in designed logging |
| **Secret logging** | Not emitted by enable procedure; not in Git/docs |
| **CURLOPT_VERBOSE** | Present as key, set **`false`** (not enabled) |
| **Apache rewrite** | Active (`proctoring/proctopus` in SSL vhost) |
| **Callback GET** | HTTP **405** (expected) |
| **Production status** | Live ON for **hidden test quiz CMID 201 only**; secret retained for manual test |

---

## MANUAL TEST URL

https://farabischool.kz/mod/quiz/view.php?id=201

User: `proctopus_test_student`  
CTA: «Начать проверку и перейти к тесту» → real Proctopus start (allowlist-gated live).

**Stopped:** no browser automation, flags **not** auto-disabled, secret **not** cleared.
