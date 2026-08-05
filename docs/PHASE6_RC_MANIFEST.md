# Phase 6 Combined Release Candidate

**Codename:** `1.0.0-portal-auth`  
**Status:** WORKSPACE ONLY — **not deployed to production**  
**Moodle:** 4.5+ (`requires` 2024100700)

## Package set (must install together)

| Component | Path on deploy | version | release | maturity |
|-----------|----------------|---------|---------|----------|
| local_kaznu | `local/kaznu` | 2026080702 | **1.0.0-portal-auth** | RC |
| theme_farabi | `theme/farabi` | 2026080702 | **1.0.0-portal-auth** | RC |
| auth_univer | `auth/univer` | 2026080702 | **1.0.0-portal-auth** | RC |

**Dependencies**

- `auth_univer` → `local_kaznu` ≥ 2026080702  
- `theme_farabi` → `theme_boost` ≥ 2024100700  
- No Moodle core patches

## Scope included

| Phase | Deliverable |
|-------|-------------|
| **6A** | `theme_farabi`, portal nav, usermenu, access_guard, guest/blogs off, farabi_student |
| **6B** | `auth_univer`, single login form, Univer-first / Moodle fallback, `login_univer` redirect |
| **6C** | Unified cabinet `/local/kaznu/my.php` tabs: overview + courses; linked + schedule/grades/attendance |

## Workspace roots

```
workspace/
  kaznu/          → local/kaznu
  theme_farabi/   → theme/farabi
  auth_univer/    → auth/univer
  kaznu/docs/phase6_combined_release_review.md  ← full review report
```

## Smoke (on a non-prod Moodle after copy)

```bash
php local/kaznu/cli/phase6_rc_smoke.php
php local/kaznu/cli/phase6_portal_smoke.php
php local/kaznu/cli/phase6b_unified_login_smoke.php
```

## PHPUnit matrix

```
local_kaznu: user_service_test, login_service_test, subject_service_test,
             cabinet_services_test, portal_policy_test
auth_univer: auth_univer_test
```

**Not executed in this workspace** (no full Moodle tree / phpunit binary).

## Production

**DO NOT deploy** until human **APPROVE**.  
See rollback in the review report.
