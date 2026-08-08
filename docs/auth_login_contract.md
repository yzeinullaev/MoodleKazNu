# Phase 6 — loginpage_hook / core contract

## Success (Univer STUDENT)

| Step | Component | Result |
|------|-----------|--------|
| 1 | Core builds `$frm` from POST | username, password, logintoken |
| 2 | Each enabled auth `loginpage_hook()` | `auth_univer` runs when flag+plugin on |
| 3 | `login_service::authenticate` | sync user/subjects/cabinet |
| 4 | `wipe_password_materials` | password cleared |
| 5 | `complete_login` → `complete_user_login` | **one** session |
| 6 | `redirect(wantsurl\|cabinet)` | **core form login never continues** |

Static `$processing` blocks re-entrant `user_login` during the same request.

## Soft fail → Moodle fallback

Statuses: `BAD_CREDENTIALS`, `NOT_STUDENT` (and `UNAVAILABLE` with allowlist match)

| Condition | Action |
|-----------|--------|
| local user missing | no fallback; generic invalid |
| auth ∈ {univer, nologin} | no fallback |
| row in local_kaznu_univer_user | no fallback |
| local auth ∉ `univer_fallback_auth_methods` | no fallback |
| otherwise | **password kept** on `$frm`; return from hook; core `authenticate_user_login` |

`NOT_STUDENT`: never creates Univer student; local fallback only via the table above.

## Hard fail (never fallback)

`ACCOUNT_REVIEW` (`mapping_conflict`), `SYNC_FAILED`, `PROFILE_FAILED` (`profile_unavailable`),  
`FACULTY_DENIED` (`denied_faculty` / `missing_faculty`), `DISABLED`, `RATE_LIMITED`  
→ wipe password, notify, redirect. No core.

Faculty UI message (no ID / allowlist):  
«Доступ к платформе предоставляется студентам Бизнес-школы.»

Feature flag `univer_faculty_filter_enabled` default **0**. When 0, faculty gate is a no-op.

## user_login (user.auth=univer)

Only called by core when that user's auth type is selected. Returns API success boolean only; local hash never used (`prevent_local_passwords`).

## Feature flag layering

```
univer_unified_login_enabled (default 0)
  AND univer_enabled (API)
  AND auth plugin in $CFG->auth
→ only then loginpage_hook runs Univer-first
```
