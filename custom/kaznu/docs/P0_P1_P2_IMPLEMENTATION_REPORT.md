# P0/P1/P2 IMPLEMENTATION REPORT

**Date:** 2026-08-06  
**Scope:** Faculty filter (flag OFF) + Proctopus offline scaffold  
**Production:** **NOT changed**  
**P3 / sandbox / prod enable:** **not started**

---

## FACULTY DISCOVERY

| Item | Result |
|------|--------|
| Endpoints checked | `/user/login`, `/student/GetStudentInfo`, `/student/profile/{culture}` (code-level) |
| Real numeric field found | **No** — not confirmed on live BS student JSON |
| JSON path | Candidates implemented: `data.facultyId` (+ aliases); **unconfirmed** |
| Validated accounts | 0 live |
| Fallback if absent | Filter default **OFF**; do not enable fail-closed sitewide; request field from Univer owners |

Details: `docs/P0_FACULTY_DISCOVERY.md`

---

## FACULTY FILTER

| Item | Result |
|------|--------|
| Feature flag | `univer_faculty_filter_enabled` default **0** |
| Settings | `univer_allowed_faculty_ids=28`, `univer_login_allowlist=oxikbayev.yernur` |
| Policy | `classes/local/faculty_access_policy.php` |
| Allowlist | trim+lowercase; still needs credentials + STUDENT; no UI disclosure; no rate-limit/conflict bypass |
| Hard-fail outcomes | `FACULTY_DENIED` (`denied_faculty` / `missing_faculty`); `PROFILE_FAILED` (`profile_unavailable`); `ACCOUNT_REVIEW` (`mapping_conflict`) — no Moodle fallback |
| Soft fallback | `BAD_CREDENTIALS` / `NOT_STUDENT` (only if `local_fallback_allowed`) / `UNAVAILABLE` when allowed |
| DB | `local_kaznu_univer_user.faculty_id` nullable (upgrade `2026080801`) |
| CLI | `cli/faculty_access_audit.php --dry-run` / `--apply` (apply requires `LOCAL_KAZNU_AUDIT_APPLY=1`; **not run** on prod) |
| Tests | `tests/faculty_access_policy_test.php` + client facultyId tests prepared |
| Smoke | Not run against production |

---

## CODE.PHP REVIEW

| Item | Result |
|------|--------|
| Line-level findings | Documented in `docs/CODE_PHP_LINE_REVIEW.md` (file missing from workspace) |
| Reusable concepts | Session/JWT/callback ideas only |
| Rejected | Manual attempts, secrets-in-code, GET state, legacy account/host |
| Legacy values | `kaznu_moodle`, `dl.kaznu`, fixed uniqueid/attempt |
| Security issues | Critical secret, AuthZ, attempt integrity, callback none |

---

## PROCTOPUS SCAFFOLD

| Item | Result |
|------|--------|
| Plugins | `local_proctopus`, `quizaccess_proctopus` (sibling trees + `kaznu/packages/*`) |
| Settings | enabled=0, baseurl empty, account=`farabi_business`, callback URL Farabi, secret empty (password + env), api login/pass empty |
| Tables | `local_proctopus_session`, `local_proctopus_callback` (hash only) |
| Interfaces | transport, jwt_encoder, callback_verifier, session_repository (+ replay_guard) |
| Quiz access rule | Per-quiz fields; `make()` null when disabled; no attempt SQL; neutral error if required but not configured |
| Callback | `/local/proctopus/callback.php` POST, size/type checks, disabled responses; rewrite **documented only** |
| JWT | Explicit algorithm **setting** (empty until provider); iat/nbf/exp/jti/accountName/sessionId; no secret in logs |
| Stub transport | Offline only |
| Capabilities | configure, viewstatus, viewreports, retry, managecallbacks |
| Events | 5 event classes (created/started/callback/completed/failed) |

Provider questions: `local_proctopus/docs/PROVIDER_QUESTIONS.md`  
Rewrite: `local_proctopus/docs/REWRITE.md`

---

## PROVIDER CONTRACT

| Known | account `farabi_business`; callback URL desired; enabled default off |
| Unknown | start URL/method, JWT transport/alg/claims, response, callback auth/schema, codes, retries, IDs, IPs, sandbox |
| Questions sent | Document prepared; **not emailed by this agent** |
| Answers received | **None** |

---

## SECURITY

| Secret stored | Settings empty; support env `PROCTOPUS_JWT_SECRET`; **chat secret not used** |
| Secret logged | No |
| JWT logged | No |
| Raw callback stored | No (payload_hash only) |
| Replay | Interface + unique `external_event_id` |
| Idempotency | Callback unique event id |
| IDOR | Session map by external_session_id; status caps planned |
| Open redirect | `local_proctopus_local_url` host-bound |

---

## TESTS

| PHPUnit prepared | Faculty matrix + proctopus `scaffold_test.php` |
| PHPUnit executed | **No** (no Moodle tree in workspace) |
| Smoke | `local_proctopus/cli/offline_smoke.php` offline |
| Real HTTP calls | **None** |

---

## CORE MODIFIED

**NO**

## PRODUCTION CHANGED

**NO**

## KNOWN LIMITATIONS

1. Numeric facultyId not production-confirmed — filter must stay OFF.  
2. `Code.php` binary not in workspace for exact line numbers.  
3. Proctopus quiz start (P3) not implemented.  
4. Callback verifier deliberately disabled until provider schema.  
5. PHPUnit not executed in this environment.  
6. Packages not committed to git unless requested.

## APPROVAL REQUIRED

**YES** for:

- enabling `univer_faculty_filter_enabled`  
- running audit `--apply` on production  
- deploying Proctopus plugins / rewrite  
- P3 sandbox / provider live tests  

**Stop — no P3.**
