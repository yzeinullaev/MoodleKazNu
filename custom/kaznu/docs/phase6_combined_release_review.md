# PHASE 6 COMBINED RELEASE REVIEW

**Document status:** Release Candidate review for human approval  
**Date:** 2026-08-05  
**Workspace only — PRODUCTION NOT CHANGED**  
**Self-approval:** **not granted** (GO/NO-GO is recommendatory only)

---

## RELEASE CANDIDATE

| Field | Value |
|-------|--------|
| **local_kaznu version** | `2026080702` / release **`1.0.0-portal-auth`** / MATURITY_RC |
| **theme_farabi version** | `2026080702` / release **`1.0.0-portal-auth`** / MATURITY_RC |
| **auth_univer version** | `2026080702` / release **`1.0.0-portal-auth`** / MATURITY_RC |
| **Moodle compatibility** | Requires `2024100700` (Moodle **4.5+**; also compatible with 5.x API surface used) |
| **Dependency chain** | `auth_univer` → `local_kaznu >= 2026080702`; `theme_farabi` → `theme_boost >= 2024100700` |
| **Manifest** | `docs/PHASE6_RC_MANIFEST.md` |

Phases packed:

1. **6A** Theme + navigation + access guard + role  
2. **6B** Unified authentication (`auth_univer`)  
3. **6C** Unified portal dashboard  

---

## FILES

### Created

| Path | Role |
|------|------|
| `auth_univer/auth.php` | Univer-first `loginpage_hook` + `user_login` |
| `auth_univer/version.php`, `settings.php`, `db/install.php`, `db/upgrade.php` | Plugin meta + nologin→univer (linked only) |
| `auth_univer/lang/{en,ru,kk}/auth_univer.php` | Neutral login errors (admin-facing description may name backends) |
| `auth_univer/tests/auth_univer_test.php` | Policy unit tests |
| `theme_farabi/**` | Boost child: login layout, loginform override, SCSS, student usermenu |
| `kaznu/classes/access/portal_policy.php` | Portal student detection |
| `kaznu/classes/local/portal_role.php` | farabi_student idempotent role |
| `kaznu/classes/local/access_guard.php` | Server-side redirects |
| `kaznu/docs/phase6b_auth_univer.md` | Auth design |
| `kaznu/docs/PHASE6_RC_MANIFEST.md` | RC packaging |
| `kaznu/docs/phase6_combined_release_review.md` | This report |
| `kaznu/cli/phase6_portal_smoke.php` | Portal smoke |
| `kaznu/cli/phase6b_unified_login_smoke.php` | Auth packaging smoke |
| `kaznu/cli/phase6_rc_smoke.php` | Combined RC smoke |

### Changed (material)

| Path | Change |
|------|--------|
| `kaznu/version.php` | RC 1.0.0-portal-auth |
| `kaznu/db/upgrade.php` | Portal policy + RC savepoints |
| `kaznu/db/hooks.php` | Empty callbacks (no CTA) |
| `kaznu/login_univer.php` | Redirect-only → `/login/index.php` |
| `kaznu/classes/hook/login_button.php` | No-op |
| `kaznu/classes/service/login_service.php` | Orchestration (sync + complete_login) |
| `kaznu/classes/service/user_service.php` | Create with `auth=univer` when plugin present |
| `kaznu/my.php` | Tabs: overview/courses + linked schedule/grades/attendance |
| `kaznu/classes/service/student_dashboard_service.php` | grades/courses section aliases |
| `kaznu/lib.php` | `extend_navigation` single key + active on cabinet |
| `kaznu/lang/*` | Tab labels, settings copy |
| `theme_farabi` login templates/SCSS/lang | Single form UI |

### Removed (runtime)

- Login CTA hook registration  
- Dual-entry “Войти через Univer” UI  
- Functional separate Univer login page (kept URL as redirect)

### Deprecated (kept on disk)

| Asset | Notes |
|-------|--------|
| `classes/form/univer_login_form.php` | Unused by login_univer |
| Lang strings `univer_login_button`, student/admin CTA labels | Not injected |
| Phase 3 dual-login CLI probes | Stale relative to 6B (not blocked) |

### Core modified

**None.**

---

## AUTH FLOW

| Topic | Implementation |
|-------|----------------|
| **Univer-first** | `auth_plugin_univer::loginpage_hook` on POST → `login_service::authenticate` before core auth |
| **Moodle fallback** | On `BAD_CREDENTIALS` / `NOT_STUDENT` (and outage+allowlisted local account): keep `$frm->password` → core `authenticate_user_login` |
| **Admin fallback** | `auth=manual` + siteadmin: Univer rejects/not student → Moodle password |
| **Manual user** | Same path; works when password matches after Univer rejection |
| **Linked student** | API STUDENT OK → upsert, subjects, cabinet modules, `auth=univer`, `complete_user_login` once, redirect cabinet |
| **Unlinked user** | Local auth only after Univer fail; can open `/local/kaznu/my.php` (common tabs) |
| **auth=univer policy** | `prevent_local_passwords`, no password change/reset; hash not usable for local form |
| **auth=nologin policy** | Legacy: blocked for form hash; install migrates **linked only** → `univer` |
| **Conflict policy** | `ACCOUNT_REVIEW` / sync/profile fail → **fail closed**, no Moodle fallback, generic UI message |
| **Outage policy** | `UNAVAILABLE` → local only if existing `manual`/`email`/`ldap`/`db` user; else operational error |
| **Rate limit** | `login_rate_limiter` in `login_service` |
| **Enumeration** | Unified invalid login for dual failure; mapping does not fall through; no username leak in errors |
| **Session flow** | Success path: clear password from `$frm`/POST → `complete_login` → redirect; failed soft path does not call complete |
| **Password reset** | Blocked for `auth_univer` |
| **login_univer compatibility** | Redirect only; bookmarks with local `wantsurl` preserved |

**Double login / double sync risk:** Success path does not fall through to core auth (redirect after complete). Soft-fail leaves password for core only (no second Univer call in same hook). Core never re-enters hook success (static `$processing`). Sync runs once inside `authenticate` before session complete.

---

## PORTAL

| Topic | Status |
|-------|--------|
| **Unified dashboard** | `/local/kaznu/my.php` only (session `$USER->id`; no `userid` param) |
| **Common tabs** | `overview`, `courses` |
| **Conditional tabs** | `schedule`, `grades`, `attendance` **iff** `local_kaznu_univer_user.userid = $USER->id` |
| **Linked user** | Full tabs + refresh→re-auth via unified login; Univer cache data |
| **Unlinked user** | Overview + courses only; warning if no link |
| **Admin behavior** | Not portal_student; no access_guard redirects; standard `/my/` remains |
| **Courses** | Enrolments in **courses** tab (`enrol_get_my_courses`); subjects list in overview when linked |
| **Univer data** | Loaded by **link table + profile cache**, not by “logged in via Univer” flag |
| **Old cards** | No dual Moodle home for portal students (guarded `/my/`) |
| **Navigation** | Portal: single **Мой кабинет**; non-portal still landing + cabinet + pay |
| **Active state** | Nav node `local_kaznu_my_cabinet` `make_active` when path is `my.php` (any section) |
| **Redirects** | `/my/`, `/my/courses.php` → cabinet for portal students |

---

## ACCESS

| Gate | Behavior |
|------|----------|
| **Calendar** | → `?section=schedule` |
| **Private files** | → cabinet + info notice |
| **Grades** | overview/mygrades/user report → `?section=grades` |
| **Assignment upload** | pluginfile/draftfile/repository/ajax **skipped** by guard |
| **AJAX / WS / CLI** | skipped |
| **Admin bypass** | siteadmin never portal_student |
| **Manager/staff** | system config / course:create / approvecourse exclude from portal |
| **BEHAT** | guard skipped when `BEHAT_SITE_RUNNING` |

---

## ROLE

| Topic | Status |
|-------|--------|
| **farabi_student** | Created idempotently by `portal_role::ensure_role` |
| **Assignment** | Linked users only (`assign_all_linked` + on provision) |
| **Idempotency** | `role_assign` safe re-run |
| **Admin exclusion** | skip siteadmin on assign |
| **Future users** | `user_service` calls role assign after link |

**Note:** Access policy is **role/cap**, not the link table alone. Unlinked manual students without role are **not** force-redirected from `/my/` (known limitation for “all ordinary users”).

---

## MIGRATION

| Topic | Detail |
|-------|--------|
| **nologin→univer** | SQL join on `local_kaznu_univer_user` only, `deleted=0`, skip siteadmin |
| **Scope** | Install + upgrade of `auth_univer`; **not all nologin users** |
| **Idempotent** | Re-run only affects remaining `nologin` linked rows |
| **Local password** | Hash stays random; not activated; reset disabled |
| **Rollback** | Documented below; does not auto-run |

---

## THEME

| Topic | Status |
|-------|--------|
| **Login UI** | Brand + subtitle + login/password/submit only (override mustache) |
| **CTA removed** | hooks empty + no-op class + CSS hide |
| **Guest removed** | guestloginbutton=0 + CSS/template omit |
| **Moodle branding removed** | No `loginto {sitename}` heading in override; CSS hides leftovers |
| **Contrast** | Cream body, charcoal navbar, light dropdowns |
| **Mobile** | Login container padding / full-bleed small screens |
| **Languages** | theme EN/RU/KK; plugin EN/RU/KK (+ZH partially) |
| **Admin pages** | Boost parent; portal usermenu only for portal students |
| **CSS conflicts** | `local_kaznu/styles.css` still injects IBS shells; may overlap debrand — low risk, not critical CSS collision with login |

---

## CONFLICT ANALYSIS

### 1
- **Component:** Auth dual CTA vs `auth_univer`  
- **Conflict:** Old CTA + `login_univer` form  
- **Resolution:** Hook empty; form unused; URL redirect; theme hide  
- **Risk:** Low (bookmark still works)

### 2
- **Component:** Portal home `/my/` vs cabinet  
- **Conflict:** Moodle default my page  
- **Resolution:** `access_guard` redirect for portal students; nav single node  
- **Risk:** Medium if user loses role but stays linked

### 3
- **Component:** Grades tab name `assessments` vs product `grades`  
- **Conflict:** URL section mismatch  
- **Resolution:** RC uses `grades`; alias maps for data + old param  
- **Risk:** Low

### 4
- **Component:** Refresh re-auth  
- **Conflict:** Old POST to login_univer with refresh flags discarded  
- **Resolution:** Link to unified `/login/index.php?wantsurl=...` (re-login re-syncs)  
- **Risk:** Low/medium UX (full re-login required)

### 5
- **Component:** XP / arena footer vs portal  
- **Conflict:** Arena targets moodle my-index  
- **Resolution:** Portal rarely lands there; XP largely unused for portal students  
- **Risk:** Low product gap, not security

### 6
- **Component:** Unlinked ordinary users without farabi_student  
- **Conflict:** TZ “все обычные → кабинет” vs role-based guard  
- **Resolution:** Partial — cabinet openable via nav; **no** forced redirect  
- **Risk:** Medium product gap (document as limitation)

### 7
- **Component:** auth install enables univer while prod not approved  
- **Conflict:** Accidental enable if plugin installed early  
- **Resolution:** **Do not copy auth_univer to prod without APPROVE**  
- **Risk:** High if ignored

### 8
- **Component:** Site fullname still “Moodle LMS”  
- **Conflict:** Browser title / rare core string  
- **Resolution:** Login body overrides brand; site rename is config (ops)  
- **Risk:** Low

---

## TESTS

| Suite | Prepared | Executed in this session |
|-------|----------|---------------------------|
| `user_service_test.php` | Yes | **No** (no Moodle PHPUnit host) |
| `login_service_test.php` | Yes (auth nologin\|univer) | **No** |
| `subject_service_test.php` | Yes | **No** |
| `cabinet_services_test.php` | Yes | **No** |
| `portal_policy_test.php` | Yes | **No** |
| `auth_univer_test.php` | Yes | **No** |
| `phase6_portal_smoke.php` | Yes | **No** (prod not touched) |
| `phase6b_unified_login_smoke.php` | Yes | **No** |
| `phase6_rc_smoke.php` | Yes | **No** |
| Behat | Not configured | N/A |

### Browser checklist (manual after staging deploy)

1. Login UI: only brand + form fields; no Univer/Moodle CTA  
2. Linked student: login → cabinet; schedule/grades/attendance tabs  
3. Unlinked manual: login → overview/courses only  
4. Admin: local password; `/my/` works  
5. Bad password: single generic error  
6. Assignment file upload in course  
7. Nav: **Мой кабинет** active on all my.php sections  
8. Mobile login  
9. RU/KK strings  
10. No guest button  

### Expected result after staging + smoke PASS

All structural checks green; live login matrix PASS before production APPROVE.

---

## SECURITY

| Area | Assessment |
|------|------------|
| **Passwords** | Not stored for Univer; cleared after success path |
| **Cookies** | Univer jar cleared post-auth; not in session |
| **PII** | Technical email `@users.invalid`; logs use correlation ids |
| **IDOR** | my.php session-bound; no userid query |
| **XSS** | Output mostly `s()` / format_string |
| **CSRF** | Login uses logintoken; sesskey on Moodle forms |
| **Open redirect** | `return_url::sanitize` local host only |
| **User enumeration** | Soft fail unified; hard fail no alternate account |
| **Logs** | Login attempt events without password |

---

## PERFORMANCE

| Topic | Notes |
|-------|--------|
| **Login HTTP** | login + profile + semester + groupList + cabinet modules (expected; not on page view) |
| **Duplicate sync** | Mitigated by single authenticate then redirect |
| **Cabinet page** | Cache/DB only; lazy per-tab queries |
| **N+1** | Subject course resolve batched |
| **Cache** | TTL modules + cleanup task |

---

## ROLLBACK

| Layer | Action |
|-------|--------|
| **Theme** | Restore previous theme from `portal_prev_theme` (e.g. boost); purge caches |
| **Plugin** | Disable `local_kaznu` portal guards via code rollback to backup; or version pin |
| **Auth** | Uninstall/disable `auth_univer`; remove `univer` from `$CFG->auth` |
| **User auth** | Optional: `UPDATE user SET auth='nologin' WHERE auth='univer' AND id IN (SELECT userid FROM local_kaznu_univer_user)` — only if re-enabling dual form |
| **Config** | Restore guestloginbutton, blogs, customusermenuitems from `portal_prev_*` |
| **DB** | No destructive schema in 6B/RC; role can remain inert |

**Pre-prod:** Always snapshot `$CFG->auth`, theme, guest/blogs, user.auth counts.

---

## KNOWN LIMITATIONS

1. PHPUnit / live E2E **not run** in this RC packaging session.  
2. Unlinked users without `farabi_student` are **not** forced off `/my/`.  
3. Cabinet refresh requires full re-login (no silent token refresh).  
4. Non-portal nav still shows Landing + Pay.  
5. XP/arena not redesigned for cabinet.  
6. Production still runs older dual-login until APPROVE.  
7. `MATURITY_RC` reflects packaging; site admin install still requires care.  
8. Site shortname/fullname may still say Moodle in browser chrome until ops rename.  
9. Forgotten password link removed from theme form (local users may need admin reset).  
10. Email auth fallback only after Univer path allows (same as manual).

---

## DEPLOY PLAN (after human APPROVE only)

1. Maintenance window + full filesystem/DB backup  
2. Copy `theme/farabi` → verify theme currently farabi or switch after  
3. Update `local/kaznu` to 2026080702 → `php admin/cli/upgrade.php`  
4. Copy `auth/univer` → upgrade → confirm `univer` in enabled auths  
5. Purge caches / rebuild theme SCSS  
6. Run `phase6_rc_smoke.php`, portal + auth smokes  
7. Live matrix: admin, linked student, unlinked manual, bad password  
8. Assignment upload test  
9. Monitor login errors 24h  
10. Keep dual-path rollback backup available  

**Before APPROVE:** Do **not** install `auth_univer` on production.

---

## GO / NO-GO RECOMMENDATION

**Conditional GO for staging only.**  
**NO-GO for production** until:

- PHPUnit green on full Moodle tree  
- Staging live auth matrix PASS  
- Explicit human **APPROVE**  

---

| Gate | Value |
|------|--------|
| **PRODUCTION CHANGED** | **NO** |
| **APPROVAL REQUIRED** | **YES** (human only) |
| **Auto-APPROVE** | **NOT ISSUED** |

---

*End of Phase 6 Combined Release Review. Stopped pending human decision: APPROVE / APPROVE WITH CHANGES / REJECT.*
