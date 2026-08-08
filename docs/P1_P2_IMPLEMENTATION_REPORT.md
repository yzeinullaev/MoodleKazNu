# P1/P2 IMPLEMENTATION REPORT

**Date:** 2026-08-06  
**Production changed:** **NO**  
**Live Proctopus / faculty enforcement:** **OFF**

---

## FACULTY

| Item | Result |
|------|--------|
| Numeric faculty field | **Not confirmed** on live Univer sample; client accepts `facultyId`/aliases when present; **not** faculty name |
| Feature flag | `univer_faculty_filter_enabled` = **0** (default) |
| Policy | `local_kaznu\local\faculty_access_policy` + `login_service` gate |
| Settings | ids=`28`, allowlist=`oxikbayev.yernur` |
| Allowlist | trim+lowercase; credentials required; no rate-limit/conflict bypass |
| Hard fail | `FACULTY_DENIED`, profile unavailable after Univer auth, mapping_conflict — **no** Moodle fallback |
| Soft fallback | BAD_CREDENTIALS; NOT_STUDENT only if local allowlisted auth user exists |
| CLI | `cli/faculty_access_audit.php --dry-run` / `--apply` (apply gated; not run on prod) |
| Tests | `tests/faculty_access_policy_test.php` + client facultyId cases prepared |

---

## PROCTOPUS

| Item | Result |
|------|--------|
| Plugins | `local_proctopus` (0.2.0-legacy-p2), `quizaccess_proctopus` (+ packages/) |
| Legacy adapter | `classes/api/legacy_proctopus_client.php` — GET `?token=` only in legacy mode |
| Settings | base `https://farabi-proctoring.kaznu.kz`, account `farabi_business`, start path default, callback Farabi URL, JWT secret empty, alg **HS256** (legacy assumption) |
| Feature flags | `enabled=0`, `legacy_mode=1`, `live_calls_enabled=0` |
| JWT | Legacy payload builder from **Moodle DB only**; iat/nbf/exp/jti/iss; no username as thirdName; duration from quiz/rule/default; no JWT in DB/logs |
| Start request | Host allowlist; HTTPS; no VERBOSE; token redacted in stub logs; live only if both flags on |
| Quiz flow | Rule blocks free start → `launch.php` POST+sesskey → session+JWT → stub/live start → `return.php` signed state → **startattempt.php** |
| Signed return | `state_token` HMAC + TTL + userid/cmid/session match |
| Callback | `callback.php` POST/size/JSON; default **disabled verifier**; stub HMAC for tests (`PROCTOPUS_CALLBACK_STUB=1`) |
| Tables | session + callback (payload_hash, unique event id) |
| Capabilities / events | Unchanged set from scaffold |

Docs: `docs/LEGACY_CONTRACT.md`, `docs/TEST_QUIZ_STUB.md`, `docs/REWRITE.md`

---

## TEST QUIZ

| Item | Result |
|------|--------|
| Setup instructions | `local_proctopus/docs/TEST_QUIZ_STUB.md` |
| Stub result | Launch redirects to local return when `live_calls=0` |
| Attempt creation | Only via Moodle `startattempt.php` — **no** hand insert |
| Duplicate protection | Active session reuse; admitted short-circuits relaunch |

---

## SECURITY

| Secret in code | **No** (chat secrets not used) |
| Secret in logs | **No** |
| JWT in logs | **No** (safe_log outcome/HTTP only) |
| Raw callback | **No** (hash only) |
| Replay | state expire + callback unique event_id |
| IDOR | return checks userid/cmid/session |
| CSRF | launch state-change POST+sesskey |
| Open redirect | host allowlist + local_url sanitizer |

---

## TESTS

| PHPUnit prepared | Faculty + `local_proctopus/tests/p2_legacy_test.php` |
| PHPUnit executed | **No** (no Moodle core tree) |
| Smoke | `cli/p2_smoke.php` offline |
| Real HTTP | **None** |

---

## CORE MODIFIED: **NO**  
## PRODUCTION CHANGED: **NO**

## KNOWN LIMITATIONS

1. facultyId path unconfirmed → keep filter OFF.  
2. HS256 is **legacy assumption**, not provider-signed specification.  
3. Live start response schema may vary — stub path is default.  
4. Callback trust OFF until provider signature contract.  
5. JWT temporarily encoded with offline stub secret only when secret empty and live=0 (never for production live).  

## APPROVAL REQUIRED

Before:

- faculty filter ON  
- production audit `--apply`  
- deploy plugins / rewrite  
- configure real secret  
- `proctopus_live_calls_enabled=1`  
- real Quiz proctoring on prod  

**Also required for live Proctopus:** new rotated secret + test Quiz on staging + separate APPROVE.

**Stop — no live enable.**
