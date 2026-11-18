# LIVE PROCTOPUS TEST REPORT

**Site:** https://farabischool.kz  
**Date (server UTC):** 2026-08-06 ~19:45–19:55  
**Scope:** single controlled live test — Quiz 22 / CMID 201 only  
**Outcome:** **STOPPED after provider JWT rejection** (per APPROVE: stop if JWT rejected; no algorithm fishing; no second live attempt)

---

## Configuration
| Item | Result |
|------|--------|
| Secret configured | YES (during test), then **cleared** on rollback |
| Secret storage | Moodle plugin config `local_proctopus / proctopus_jwt_secret` (not in source, not in docs, not in report) |
| Secret length (diagnostic only) | 8 chars (from owner `Code.php` `$key` seed process) |
| Quiz scope | **quizid=22 / cmid=201 only** live allowlist |
| Other quizzes affected | **NO** — only Quiz 22 has `proctopusrequired=1` |
| `proctopus_enabled` (during test) | 1 |
| `proctopus_live_calls_enabled` (during test) | 1 |
| `proctopus_legacy_mode` | 1 |
| `proctopus_baseurl` | `https://farabi-proctoring.kaznu.kz` |
| `proctopus_account_name` | `farabi_business` |
| `proctopus_start_path` | `/integration/simple/farabi_business/start/` |
| `proctopus_callback_url` | `https://farabischool.kz/proctoring/proctopus/result` |
| `proctopus_jwt_algorithm` | HS256 (legacy only; not auto-cycled) |
| Live CMID gate | Live launch denied unless cmid in allowlist (default **201**) |
| Backup | `/var/backups/proctopus_live_20260806_194116` (DB gzip, plugins, vhost, settings snapshots) |

---

## Connectivity

| Item | Result |
|------|--------|
| DNS `farabi-proctoring.kaznu.kz` | OK → `212.154.154.215` |
| TLS | OK — CN `*.kaznu.kz`, valid window observed (through Nov 2026) |
| Port 443 | OK |
| System time / NTP | OK — `System clock synchronized: yes` (UTC) |
| Start endpoint **without** token | HTTP **200**, short body |
| TLS verify on Moodle curl | ON (no bypass) |
| `CURLOPT_VERBOSE` | Not used |
| Old host `dl.kaznu.edu.kz` | Not used in live client path |
| `accountName` | `farabi_business` |

### Live start call (server-side then browser)

| Item | Result |
|------|--------|
| HTTP status (server-side start probe / live client) | **200** (HTML / plain text response; not JSON redirect) |
| Response type | **text/HTML fragment** — browser-entry path used (redirect user to allowlisted start URL) |
| Redirect target host | `farabi-proctoring.kaznu.kz` only |
| Server-side session after launch | `status=started`, `result_summary=redirected` |

**Code defect found and fixed during test (pre-browser):**  
`Class "local_proctopus\api\moodle_curl_transport" not found` — missing `use local_proctopus\http\moodle_curl_transport` in `legacy_proctopus_client.php`. Fixed and redeployed; live launch then succeeded.

---

## JWT

| Item | Result |
|------|--------|
| Algorithm | **HS256** |
| Claims observed (structure only) | `iat`, `nbf`, `exp`, `jti`, `iss`, `userId`, `lastName`, `firstName`, `thirdName` (empty), `language`, `accountName=farabi_business`, `examId=201`, `examName`, `duration`, `schedule`, `proctoring`, `examDesc`, `rules`, `startDate`, `endDate`, `sessionId`, `sessionUrl`, `redirectUrl` |
| Accepted by provider | **NO** |
| Provider response body (observed) | **«Неправильный токен!»** |
| JWT in Moodle safe logs | **NO** (correlation / status / ms only) |
| Secret in Moodle / PHP / CLI report output | **NO** |
| Action on reject | **STOP** — no other algorithms tried; no new secret requested |

---

## Provider (browser E2E)

Student: `proctopus_test_student` (password not logged).

| Step | Result |
|------|--------|
| Login | YES (existing session) |
| Open `mod/quiz/view.php?id=201` | YES (after `viewhiddencourses` on hidden course 24 for student role) |
| Honest pre-admit UI | YES — «Для этого теста требуется прокторинг» / CTA «Начать проверку и перейти к тесту» |
| Live UI not showing stub label | **PARTIAL** — quiz intro still contains stale text *“Stub proctoring path test…”* in quiz description; status badge path uses “requires proctoring”, not “Тестовый режим интеграции” |
| Provider UI opened | **YES** — domain `farabi-proctoring.kaznu.kz` |
| Camera permission requested | **NO / NOT OBSERVED** |
| Camera preview | **NO / NOT OBSERVED** |
| Microphone permission requested | **NO / NOT OBSERVED** |
| Screen share requested | **NO / NOT OBSERVED** |
| Face verification | **NO / NOT OBSERVED** |
| Browser check | **NO / NOT OBSERVED** |
| Environment check | **NO / NOT OBSERVED** |
| Admission confirmed | **NO** — blocked on invalid token |
| Returned to Moodle (provider flow) | **NO** |

---

## Moodle

| Item | Result |
|------|--------|
| Return after admission | N/A — not reached |
| Signed / admitted state | **NO** |
| Session status (live launch) | `started` / `redirected` → marked **`failed`** (`live_test_jwt_rejected`) on rollback |
| Attempt created by this live browser test | **NO** |
| Attempt count (pre-existing on quiz 22) | 3 completed (from prior stub/UI work; not this live admission path) |
| Duplicate protection | Reuse of non-expired `started` sessions present; full browser re-launch after admit **not tested** |
| Quiz completed (this live path) | **NO** |
| Grade (this live path) | **NO** |

---

## Callback

| Item | Result |
|------|--------|
| Rewrite `/proctoring/proctopus/result` → `/local/proctopus/callback.php` | **YES** (vhost `farabischool-le-ssl.conf`; `apachectl configtest` Syntax OK; reload OK) |
| GET callback URL | HTTP **405** (expected) |
| Received during test | **NO** |
| HTTP method / content-type / payload shape | **NOT OBSERVED** |
| Authentication observed | N/A |
| Status | `CALLBACK_ROWS=0` |
| Duplicate handling | N/A |
| Raw payload stored | N/A — none received |

---

## Security

| Item | Result |
|------|--------|
| Apache logs | **`farabischool_access.log` contained `token=` lines** (and residual `eyJ` JWT-looking query entries). **Emergency action taken:** scrub active log; quarantine under root-only backup path; insert `SetEnvIf` markers for `local/proctopus` and `proctoring/proctopus` before CustomLog (reload OK). Residual after scrub: token=0 (and eyJ re-scrubbed). |
| PHP logs | No secret values printed in agent probes |
| Moodle logs | Safe outcomes only; no intentional JWT dump |
| Token leakage | **YES in Apache access log** (query strings) — mitigated by scrub + quarantine + log-marking step; **browser address bar** also had full start URL with token (expected for GET start contract; not stored by us) |
| PII leakage in our safe logs | Not observed as dump of IIN/password/cookies |
| Errors | Provider “Неправильный токен!”; earlier launch fatal class-not-found (fixed) |

**Before any future live retry:** change CustomLog so sensitive URIs log **path only** (not full query), rotate/restrict quarantine files, and re-confirm JWT secret **with Proctopus owner** for `farabi_business` (current seed was rejected).

---

## Rollback

| Item | Result |
|------|--------|
| Flags after test | `proctopus_enabled=0`, `proctopus_live_calls_enabled=0` |
| Secret after test | **cleared** (`SECRET_LEN=0`) |
| Rule on Quiz 22 | Left in place (`proctopusrequired=1`) on **hidden** test course only |
| Course 24 hidden | **YES** (`visible=0`; re-applied after one flip) |
| Rewrite | Left in place (GET→405; no production impact observed) |
| Other quizzes normal | Unaffected sample quizzes remain without required proctopus rule |
| Production status | **Live Proctopus OFF**; test course hidden; no open live sessions |
| Remaining gaps | Provider rejects HS256 JWT with configured secret seed; full admit→quiz E2E not reached; Apache CombinedLog still risks query tokens on any URL (policy partially improved, full path-only log for sensitive routes still recommended); stale quiz description still says “stub”; landing may surface hidden course cards to enrolled users with catalog quirks |
| **APPROVAL REQUIRED** | **YES** before any second live attempt, secret rotation coordination, other quizzes, or production enable |

---

## Verdict

**Live network path works** (DNS/TLS/allowlist/start GET/redirect to real Proctopus host).  
**Provider does not accept the JWT** (`Неправильный токен!`).  
Per controlled APPROVE: **one test stop**, flags off, secret cleared, logs scrubbed, **no expansion**.
