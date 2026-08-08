# INTEGRATION ANALYSIS REPORT

**Site:** https://farabischool.kz  
**Moodle:** 4.5.11+  
**Plugins in scope:** `local_kaznu`, `auth_univer`, `theme_farabi`  
**Date (plan):** 2026-08-06  
**Production changed:** **NO**  
**Approval required:** **YES**

---

## Executive status

| Workstream | Status after audit | Blocking gaps |
|------------|-------------------|---------------|
| Univer faculty filter | Design complete; implementable after field discovery | **No `facultyId` in current DTO/cache** — only faculty **name** string |
| Proctopus | Architecture complete; contract incomplete | `Code.php` **not in workspace**; provider API details unknown; JWT secret **exposed in chat → rotate** |
| Deploy | Not started | Explicit APPROVE required |

---

# UNIVER ACCESS FILTER

## Faculty field source (current code)

| Source | Path / code | What is captured today |
|--------|-------------|------------------------|
| Login | `GET /user/login` → `data.userType`, `userId` | `STUDENT` check yes; **no faculty** |
| Profile primary | `GET /student/GetStudentInfo?culture=…` | names, `course`, `specialityOkpd`, contacts; **IIN stripped**; **no faculty ID extraction** |
| Profile secondary | `GET /student/profile/{culture}` | label/value pairs → `extract_profile_pair(..., 'faculty')` → **string name** (e.g. after stripping `Факультет:`) |
| DTO | `local_kaznu\api\dto\student_profile::$faculty` | `string` only |
| Cache | `local_kaznu_univer_user.faculty` | `CHAR(255)` name only — **no `faculty_id` column** |
| Gate | **Missing** | After credentials+STUDENT+profile, code always proceeds to `user_service::upsert_from_univer` |

**Conclusion:** Production policy `facultyId == 28` **cannot be enforced correctly** until the client maps a **numeric faculty id** from the real API payload. Name-based matching is **out of scope** (explicit requirement).

### Proposed field discovery (P0, non-prod / controlled sample)

Without inventing the contract, implement parse candidates (first positive integer wins), log **only field name presence**, never values of PII:

Candidate keys (order tentative until real payload confirmed):

- `facultyId`, `FacultyId`, `faculty_id`
- Nested: `faculty.id`, `Faculty.Id`, `departmentId`, `facultyCode` (only if documented as id)

If after parse result is empty/`0`/non-numeric → **fail closed** (`missing_faculty`).

Store:

- `student_profile::$facultyid` (`?int`, null if missing) — gate source of truth  
- Keep `$faculty` string for display only  
- DB: add `faculty_id` INT NULL to `local_kaznu_univer_user` on upgrade (empty for historical rows)

## Allowed faculty settings

| Setting | Component | Default | Format | Notes |
|---------|-----------|---------|--------|-------|
| `univer_allowed_faculty_ids` | `local_kaznu` | `28` | comma-separated integers | Parser: trim, cast `(int)`, drop `<=0`, drop non-numeric tokens silently |
| Admin path | Site admin → local_kaznu | same | — | Capability site config only |

**Not** hard-coded inside `login_service` body — inject via:

`local_kaznu\local\faculty_access_policy` (or `service\access_eligibility`)

```
parse_allowed_ids(get_config(...))
is_username_allowlisted(normalized_login)
decide(userType, facultyId, login): outcome enum
```

## Allowlist settings

| Setting | Default (this project) | Normalization | Audit |
|---------|------------------------|---------------|-------|
| `univer_login_allowlist` | `oxikbayev.yernur` | trim + `core_text::strtolower` for **comparison only** | Config log / config changes via Moodle admin; usernames only — **no passwords/PII** |

Allowlist **overrides faculty** after successful STUDENT + profile fetch (profile still required so link can be upserted for legitimate testers).  
If profile fails for allowlisted user → still **fail closed** (`profile_unavailable`) — no half-session.

## Decision matrix

| # | Preconditions | Outcome (internal) | login_result status (proposed) | UI | create/update user | portal roles | subjects/cabinet sync | complete_login | Moodle fallback |
|---|---------------|--------------------|--------------------------------|----|--------------------|--------------|----------------------|----------------|-----------------|
| 1 | Flag/API disabled | (existing) | DISABLED / etc. | existing | no | no | no | no | policy existing |
| 2 | Bad credentials | bad_credentials | BAD_CREDENTIALS | generic invalid | no | no | no | no | only if `local_fallback_allowed` |
| 3 | userType ≠ STUDENT | **not_student** | NOT_STUDENT (hard for gate set) | business_school msg **or** invalid (choose: faculty product prefers same BS message for non-students? **recommend BS message only for faculty denials; non-student keep generic**) | no | no | no | no | **no** after this work (stricter than today for NOT_STUDENT when we reclassify; see below) |
| 4 | Profile throw / empty userid | **profile_unavailable** | PROFILE_FAILED | neutral existing | no | no | no | no | **no** (already hard) |
| 5 | faculty missing / bad format | **missing_faculty** | **FACULTY_DENIED** (new) | BS message | no | no | no | no | **no** |
| 6 | faculty parse ok, id ∉ allow list | **denied_faculty** | FACULTY_DENIED | BS message | no | no | no | no | **no** |
| 7 | faculty ∈ allow list | **success_business_school** | OK | — | yes | yes | yes | yes | n/a |
| 8 | login ∈ allowlist (any faculty) | **success_allowlisted_user** | OK | — | yes | yes | yes | yes | n/a |
| 9 | username conflict | **mapping_conflict** | ACCOUNT_REVIEW | existing review | no | no | no | no | **no** |

**Pipeline (insert gate after profile validation, before semester/upsert):**

```
login API OK
→ is_student?
→ get_profile OK + userid OK
→ faculty_access_policy::evaluate(login, usertype, facultyId)
     → DENY / ALLOW / ALLOW_ALLOWLIST
→ only then semester + upsert + sync + emit success_*
```

### Fallback policy (critical)

**Current** `auth_univer::loginpage_hook` soft-falls for `BAD_CREDENTIALS` | `NOT_STUDENT` when `local_fallback_allowed()`.

**Required change:** `FACULTY_DENIED` (and optionally reaffirm `NOT_STUDENT`) are **HARD FAIL**:

- wipe password materials  
- show **«Доступ к платформе предоставляется студентам Бизнес-школы.»** (new lang string, all languages)  
- `redirect(/login/index.php)`  
- **never** keep password for core  
- **never** treat as BAD_CREDENTIALS  

Rationale: valid Univer user with non-28 faculty + matching Moodle `manual` account must not bypass faculty gate.

`UNAVAILABLE` (network) remains separate: local fallback only for allowlisted local auth; **does not** create Univer path bypass of faculty (because Univer path never succeeded).

## Existing users dry-run

### Scope

Table: `mdl_local_kaznu_univer_user` ⨝ `mdl_user` ⨝ role assignments (`farabi_portal_user` / `farabi_student`).

### Output columns (safe)

| Column | Source |
|--------|--------|
| moodle_userid | link.userid |
| univer_userid | link.univer_userid |
| univer_login_norm | lower(trim(link.univer_login)) |
| faculty_name_cache | link.faculty (display only) |
| faculty_id_cache | link.faculty_id **(new; else empty)** |
| classification | allowed / denied / **unknown** |
| reason | allowlist / faculty_ok / faculty_denied / id_missing / name_only_legacy |
| auth | user.auth |
| suspended | user.suspended |
| roles | shortnames at system context |

**Exclude:** IIN, password, cookies, full profile, emails if not needed.

### Current limitation (pre-implementation)

Without `faculty_id` in cache, production dry-run **today** can only classify:

- **allowed** if normalized login ∈ allowlist  
- **unknown** for everyone else (name strings are not criteria)  

**Plan does not run production queries** in this audit turn. Post-APPROVE:

```
php local/kaznu/cli/faculty_access_report.php --dry-run
```

Blocking / suspend / role strip **only** via separate CLI:

```
php local/kaznu/cli/faculty_access_enforce.php --dry-run
php local/kaznu/cli/faculty_access_enforce.php --apply
```

**Upgrade must not auto-block.**

Allowlist user `oxikbayev.yernur` always **allowed**.

## Files to change (faculty)

| File | Change |
|------|--------|
| `settings.php` | `univer_allowed_faculty_ids`, `univer_login_allowlist` |
| `lang/*` | settings + UI BS message + event strings |
| `classes/api/dto/student_profile.php` | `?int $facultyid` |
| `classes/api/univer_client.php` | extract faculty id (fail-closed when used) |
| `classes/local/login_result.php` | `FACULTY_DENIED` (+ keep mapping) |
| `classes/local/faculty_access_policy.php` | **new** |
| `classes/service/login_service.php` | call policy before upsert; outcomes |
| `classes/service/user_service.php` | persist `faculty_id` |
| `classes/repository/univer_user_repository.php` | field |
| `db/install.xml` + `db/upgrade.php` | `faculty_id` |
| `auth_univer/auth.php` (+ packages copy) | hard-fail FACULTY_DENIED; UI string |
| `docs/auth_login_contract.md` | document hard fail |
| `cli/faculty_access_report.php` | **new** dry-run report |
| `cli/faculty_access_enforce.php` | **new** explicit apply |
| `tests/login_service_test.php` + new policy tests | matrix 1–15 |
| `tests/univer_client_test.php` | faculty id parse fixtures |
| `version.php` | stamp bump on ship |

## Tests (faculty) — map to requirement list

1. STUDENT + facultyId=28 → OK / `success_business_school`  
2. STUDENT + facultyId `"28"` → OK  
3. Other facultyId → FACULTY_DENIED, no user create  
4. Missing faculty → FACULTY_DENIED / missing_faculty  
5. Profile unavailable → PROFILE_FAILED  
6. non-STUDENT → NOT_STUDENT  
7. allowlist + other faculty → OK / `success_allowlisted_user`  
8. Allowlist case-insensitive  
9. auth_univer: FACULTY_DENIED → no fallback (password wiped)  
10–12. No user / no roles / no subject sync spies  
13. Event `other` has correlation+outcome only  
14. Multi-id setting `28,99`  
15. Garbage tokens ignored; empty allowed list after parse → fail closed all non-allowlist  

## Risks (faculty)

| Risk | Severity | Mitigation |
|------|----------|------------|
| API has no numeric faculty id / only name | **High** | Provider/API capture on staging sample; fail closed until mapped |
| Existing non-BS linked users keep auth=univer | Medium | Report + explicit enforce CLI; no silent upgrade wipe |
| String "28" inside faculty **name** false positive | Low | Never use name for allow |
| Soft fallback left attached to wrong status | **High** | Explicit hard list in `auth_univer` unit test |
| Allowlist grows / forgotten | Medium | Admin description “temporary”; periodic review |

## Rollback (faculty)

1. Clear `univer_allowed_faculty_ids`? → empty parse fail-closed (avoid). Prefer set to previous.  
2. Set allowlist wide **or** temporary second faculty id.  
3. **Kill switch:** code path: if setting `univer_faculty_filter_enabled` = 0 (add as deploy safety, default **1** after enable) — reverts gate; recommended.  
4. Plugin code revert + purge caches.  
5. No DB drop of faculty_id column required for rollback.

---

# PROCTOPUS CODE REVIEW

## Artefact status

`Code.php` was **not present** in the workspace / git tree at audit time.  
Review below is against: (1) the **mandatory problem checklist** supplied with the task, (2) patterns common to legacy KazNU Proctopus scripts, (3) **do-not-copy** constraints.

When `Code.php` is attached, re-run line-level review against this checklist before any implementation APPROVE for proctoring.

## Reusable parts (concept only)

- Idea of JWT-carrier launch payload (user identity lite + exam metadata + session/redirect URLs)  
- Idea of external proctoring session before Moodle attempt continues  
- Account name concept for multi-tenant isolation (`farabi_business`, not `kaznu_moodle`)  
- Callback URL for result webhook (target host `farabischool.kz`)

## Unsafe parts (must not reuse)

| Issue | Why |
|-------|-----|
| Hardcoded JWT secret | Credential in source; already **chat-exposed → rotate** |
| Full JWT in query (`?token=`) | Logs (Apache/proxy access), browser history, Referer |
| CURLOPT_VERBOSE / URL dumps | Secret leakage |
| Manual `INSERT quiz_attempts` | Breaks question attempt engine, layout, uniqueid, grades |
| Fixed `uniqueid` / attempt / layout | Corrupt multi-attempt, collisions |
| Raw `$_GET` | Injection / type chaos; bypass PARAM_* |
| State-changing GET | CSRF, cache, prefetch |
| Trust browser for exam name/open/close | Tamper |
| `require vendor/autoload` in web root script | Path control, packaging |
| No callback signature verification | Spoofed results |
| No replay / idempotency | Double process / replay fraud |
| Implicit JWT alg / none | Algorithm confusion |
| Unvalidated HTML in examDesc | Stored XSS if rendered elsewhere |

## Moodle API violations

1. Bypassing `quiz_prepare_and_start_new_attempt` / standard startattempt WS/lib  
2. Ignoring `quiz_access_manager` and other access rules  
3. Not using `require_login` + `require_capability` + module context for student start  
4. Not tying session to `cmid` + enrollment + availability checks  
5. Standalone PHP outside plugin (if deployed in web root) — not upgrade-safe, not capability-scoped  

## Hardcoded legacy values (expected in Code.php)

| Legacy | Target |
|--------|--------|
| `accountName = kaznu_moodle` | `farabi_business` |
| `dl.kaznu.edu.kz` (or similar) | `https://farabischool.kz` (+ `$CFG->wwwroot`) |
| Hardcoded attempt=`1` | Real attempt id after API start or intermediate signed return |
| In-repo secret | env / password config |

## Security findings (aggregate)

- **Secret management:** fail — hardcoded + chat exposure.  
- **AuthZ:** fail — cmid in query treated as trust.  
- **Integrity of quiz lifecycle:** fail — manual attempts.  
- **Callback:** fail — no verify/replay.  
- **PII:** risk of over-share (IIN, email, HTML names).  
- **Logging:** high risk of token URLs.

---

# TARGET ARCHITECTURE

## Plugin type: **split (recommended)**

| Plugin | Why |
|--------|-----|
| **`quizaccess_proctopus`** | Official Quiz Access Rule: settings per quiz, `prevent_access` / preflight UI, blocks “normal” start when required, integrates with `quiz_access_manager` |
| **`local_proctopus`** | Independent of quiz form lifecycle: JWT, HTTP client, callback endpoint, tables, admin, events, healthcheck, return/state verification |

**Reject single local-only:** cannot store per-quiz access rule cleanly without fighting form API.  
**Reject quizaccess-only:** callback + long-lived config + sessions better as `local_*`; keeps rule plugin thin.

**Core modified:** **NO**

Rewrite (infra):

```
https://farabischool.kz/proctoring/proctopus/result
  → alias/rewrite to
https://farabischool.kz/local/proctopus/callback.php
```

(Document exact Apache/Nginx stanza in deploy plan — no random root PHP.)

## Components

```
local_proctopus/
  settings.php
  version.php, db/install.xml, upgrade.php, access.php, events.php, services.php?
  callback.php                    # no require_login; verify signature
  return.php                      # student return; sesskey/state + start attempt
  classes/
    jwt/token_service.php
    http/proctopus_client.php     # Moodle curl, HTTPS allowlist
    service/session_service.php
    service/callback_service.php
    service/attempt_bridge.php    # ONLY Moodle quiz APIs
    local/url_signer.php
    event/*
  cli/health_check.php, smoke.php

quizaccess_proctopus/
  rule.php                        # extends quiz_access_rule_base
  settings.php (per-quiz form)
  lang/*
  version.php (requires local_proctopus)
```

## Quiz flow (target)

```
Student «Attempt quiz»
  → mod_quiz view + quiz_access_manager (all rules)
  → quizaccess_proctopus: if !global_enabled || !rule.required → pass-through
  → if required && no valid local session “admitted”:
        prevent free start; CTA «Начать тест с прокторингом» (POST+sesskey)
  → local_proctopus.session_service:
        verify enrol/cap/time/attempts
        create local_proctopus_session (status=created)
        build JWT claims (after provider confirm)
        server-side start call OR redirect with token (prefer POST body / header)
  → external Proctopus checks equipment
  → redirect to local return.php?state=… (signed, TTL, one-time)
  → return.php verifies state + maps session → user/cmid
  → attempt_bridge: quiz API start or resume **in-progress** attempt
  → mark session admitted / attemptid set
  → redirect to standard attempt.php

On Proctopus outage: session failed event; **no orphan quiz_attempt**
```

## Callback flow

```
Provider POST (TBD method) → /proctoring/proctopus/result
  → method allowlist, size limit, content-type
  → verify JWT/HMAC/Basic (TBD provider)
  → resolve external_session_id → local session (not raw userId trust)
  → replay: external_event_id unique / payload_hash
  → insert local_proctopus_callback; update session status/result_code
  → events (safe other data)
  → HTTP 200/202 documented; 401/403/409/422 as per matrix
  → result independent of gradebook
```

## Tables

### `local_proctopus_session`

As specified: userid, courseid, cmid, quizid, attemptid null, external_session_id, status, proctoring_mode, started_at, expires_at, completed_at, last_callback_at, result_code, result_summary, correlation_id, timecreated, timemodified.

Indexes: userid+cmid+status, external_session_id UNIQUE, correlation_id.

### `local_proctopus_callback`

As specified; **no full JSON by default**. Optional encrypted raw table only if `store_raw_callbacks=1` + retention task.

## Capabilities

| Cap | Context | Who |
|-----|---------|-----|
| `local/proctopus:configure` | system | admin |
| `local/proctopus:viewstatus` | course/module | teacher+student own |
| `local/proctopus:viewreports` | course | teacher |
| `local/proctopus:retry` | course | teacher/admin |
| `local/proctopus:managecallbacks` | system | admin |
| rule: `quizaccess/proctopus:…` if needed for reports | module | |

## Settings (`local_proctopus`)

| Key | Default |
|-----|---------|
| `proctopus_enabled` | **0** |
| `proctopus_baseurl` | empty (set later) |
| `proctopus_account_name` | `farabi_business` |
| `proctopus_callback_url` | `https://farabischool.kz/proctoring/proctopus/result` |
| `proctopus_jwt_secret` | password field; prefer env override `PROCTOPUS_JWT_SECRET` |
| `proctopus_api_login` / `proctopus_api_password` | empty |
| timeouts, jwt_ttl, clock_skew, language, default mode/rules | sensible safe defaults |
| `proctopus_debug` | **0** |

Quiz rule settings: required, websites allowed, look away, move away, voices, duration override, mode.

## Events

- `proctoring_session_created`  
- `proctoring_session_started`  
- `proctoring_callback_received`  
- `proctoring_session_completed`  
- `proctoring_session_failed`  

`other`: correlation_id, session id (local), safe status codes — **never** JWT/secret/raw payload.

---

# PROCTOPUS CONTRACT

## Known

| Item | Value |
|------|-------|
| Account name | `farabi_business` |
| Public callback URL (desired) | `https://farabischool.kz/proctoring/proctopus/result` |
| Host brand | farabischool.kz (not dl.kaznu) |
| JWT secret | Provided via chat historically → **treated as compromised** |
| Provider login/password | **Not received** — leave empty settings |
| Enable default | off |

## Unknown (block production enable)

Start endpoint, method, JWT transport, alg confirmation, required claims/audience, start response schema, browser redirect URL, callback auth & schema, codes, retries, idempotency key, IP allowlist, whether API login required alongside JWT, sandbox vs prod base URLs, sessionUrl/redirectUrl rules, PII retention duties, Moodle userid as external id legality.

## Questions to provider (send as-is)

1. Exact start endpoint URL (sandbox + production).  
2. HTTP method for start.  
3. JWT transport: query / POST body / Authorization header (prefer not query).  
4. Algorithm (HS256?).  
5. Required claims list (iat/nbf/exp/jti/iss/aud/sessionId + payload fields).  
6. Audience / issuer exact strings.  
7. Start response schema (fields + redirect URL location).  
8. Who opens URL (302 vs HTML).  
9. Callback HTTP method.  
10. Callback authentication (shared secret JWT, HMAC body, mutual TLS, IP).  
11. Callback JSON schema + Content-Type.  
12. Status / result codes enum + meaning for pass/fail/pending.  
13. Callback retry policy (timeouts, max retries).  
14. Idempotency / event ID field name.  
15. Source IP allowlist for callbacks.  
16. Are API login/password required in addition to JWT?  
17. Sandbox base URL.  
18. Production base URL.  
19. Requirements for `sessionUrl` and `redirectUrl` (HTTPS, query params, one-time).  
20. PII retention, video storage, data processing agreement notes.  

**Do not invent answers in code.**

---

# IMPLEMENTATION PLAN

## Phase P0 — Discovery & safety (no production enable)

- Capture anonymized sample of Univer GetStudentInfo + profile for a BS student → confirm `facultyId` path.  
- Attach real `Code.php` for line review if different from checklist.  
- Send Proctopus questionnaire.  
- Provision **rotated** JWT secret offline (not in git/chat).  
- Feature flags default off.

## Phase P1 — Univer faculty filter (local_kaznu + auth_univer)

- Settings + policy class + DTO faculty_id + client parse.  
- login_service gate + outcomes.  
- auth hard-fail + lang.  
- DB faculty_id.  
- PHPUnit matrix.  
- CLI dry-run report (staging then prod **read-only**).  
- Staging enable; production only on APPROVE.

## Phase P2 — Proctopus skeleton (disabled)

- Create `local_proctopus` + `quizaccess_proctopus` plugins (in monorepo `packages/` or sibling dirs).  
- Settings masked secret; tables; empty client stub.  
- Rewrite rule stub for callback path (document nginx).  
- PHPUnit with stub transport; smoke offline.

## Phase P3 — Quiz bridge (sandbox flag)

- Access rule UI; session create; signed return; **official Quiz start API only**.  
- No real grades alteration.  
- Provider sandbox end-to-end with flag `proctopus_live=0|1`.

## Phase P4 — Callback productionize + ops

- Full verify/replay/idempotency.  
- Teacher status UI (minimal).  
- Events, health CLI, retention task.  
- Load/failure tests; go-live APPROVE separate.

---

# SECURITY

| Area | Design |
|------|--------|
| **Secret storage** | Env `PROCTOPUS_JWT_SECRET` preferred; else `admin_setting_configpasswordunmask` / password masked; never default in code; never lang/docs/smoke |
| **JWT** | Explicit alg HS256 (confirm); iat/nbf/exp/jti; short TTL; optional iss/aud when confirmed |
| **Callback verification** | Shared secret validate before DB; accountName must match config |
| **Replay** | external_event_id unique; jti black/white list TTL; reject stale timestamps (skew config) |
| **IDOR** | session ownership + capability on status pages; callback maps by external_session_id only |
| **CSRF** | Student start/return: sesskey or one-time state token; callback server-to-server exempt but signed |
| **Open redirect** | return URLs built only from `$CFG->wwwroot` + fixed plugin paths |
| **Logs** | correlation_id; redacted paths; never JWT, never full callback, no CURLOPT_VERBOSE |
| **PII** | No IIN/email unless provider required; names minimal; no username as thirdName |

**Exposed secret:** JWT key shared in chat is **compromised**. **Rotate before any production proctopus enable.** Do not write the value into this document.

---

# TEST PLAN

## PHPUnit

**Faculty:** 15 cases listed above.  
**Proctopus:** 20 cases from brief (JWT claims, no secret logs, no legacy hosts, IDOR, callback reject/replay, no INSERT quiz_attempts, disabled path, XSS escape).

## Smoke (CLI)

Faculty: disabled filter override; allowed; denied.  
Proctopus: `proctopus_enabled=0`; stub session; callback valid/invalid/duplicate; cleanup. **No live provider** without `PROCTOPUS_LIVE_SMOKE=1`.

## Browser

Faculty: BS student login; non-BS denied message; allowlist; no dual CTA regression.  
Proctopus (staging): start rule CTA; return; attempt opens; disable path = vanilla quiz.

## Provider sandbox

After answers to Q1–20; separate APPROVE.

---

# FILES TO CREATE

### Faculty

- `classes/local/faculty_access_policy.php`  
- `cli/faculty_access_report.php`  
- `cli/faculty_access_enforce.php`  
- optional `tests/faculty_access_policy_test.php`  
- this plan file (done)

### Proctopus (new plugins)

```
local/proctopus/**           (full tree)
mod/quiz/accessrule/proctopus/**   # quizaccess_proctopus
(or packages/local_proctopus, packages/quizaccess_proctopus in MoodleKazNu monorepo)
```

Web rewrite config snippet (ops, not Moodle core).

# FILES TO CHANGE

- local_kaznu: settings, login_service, univer_client, student_profile, user_service, install/upgrade, lang, auth contract docs, version  
- auth_univer: loginpage_hook hard-fail set, lang  
- theme_farabi: **likely none** (login message via notifications)  
- **Moodle core: none**

# CORE MODIFIED

**NO**

---

# DEPLOY PLAN (after APPROVE only)

### Faculty filter

1. Backup DB + plugins.  
2. Deploy `local_kaznu` + `auth_univer`.  
3. `php admin/cli/upgrade.php`.  
4. Confirm settings defaults: faculty=`28`, allowlist=`oxikbayev.yernur`.  
5. Staging: PHPUnit + smoke + browser matrix.  
6. Prod: deploy, upgrade, **run dry-run report**, review unknowns.  
7. Do **not** run enforce --apply until second APPROVE.  
8. Monitor `univer_login_attempted` outcomes.

### Proctopus (later, separate APPROVE)

1. Install plugins disabled.  
2. Configure rewrite; secrets via env.  
3. Sandbox e2e.  
4. Enable per quiz, not site-wide first.  
5. Callback canary.

# ROLLBACK PLAN

**Faculty:** set `univer_faculty_filter_enabled=0` (if shipped) or revert plugin versions; caches purged; users not mass-modified by filter alone.  

**Proctopus:** `proctopus_enabled=0` + rule off per quiz → vanilla Moodle quiz; leave session tables intact for audit.

---

# DECISION GATES (stop conditions)

| Gate | Required before coding / before prod |
|------|--------------------------------------|
| G1 | Confirm `facultyId` JSON path on Univer | before P1 ship to prod |
| G2 | Attach `Code.php` for final line review | before P2 code parity assumptions |
| G3 | Provider answers ≥ critical set (1–12,16–19) | before P3 live |
| G4 | Rotated JWT secret provisioned | before P4 prod enable |
| G5 | Explicit human **APPROVE** message | before any production change |

---

## PRODUCTION CHANGED

**NO**

## APPROVAL REQUIRED

**YES** — stop here pending explicit APPROVE for implementation (and separate APPROVE for production deploy).
