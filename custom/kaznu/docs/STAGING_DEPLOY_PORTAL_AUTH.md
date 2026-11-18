# Phase 6 — Staging package (APPROVE WITH CHANGES)

**Version stamp:** `2026080703` / `1.0.0-portal-auth-staging` / MATURITY_RC  
**Production:** **NO-GO** until separate production-final stamp + APPROVE.

| Package | Version | Release |
|---------|---------|---------|
| local_kaznu | 2026080703 | 1.0.0-portal-auth-staging |
| theme_farabi | 2026080703 | 1.0.0-portal-auth-staging |
| auth_univer | 2026080703 | 1.0.0-portal-auth-staging |

## Critical changes vs prior RC

1. **auth_univer does not auto-enable** on install/upgrade.
2. Kill switch `local_kaznu/univer_unified_login_enabled` default **0**.
3. CLI: `php local/kaznu/cli/enable_unified_login.php --enable|--disable|--status`
4. Portal Variant A: `farabi_portal_user` + `farabi_student`; caps `viewportal` / `viewuniverdata`
5. Fallback methods setting default `manual,email`
6. Refresh: confirm → logout → unified login → wantsurl with section
7. Deprecated form/hook marked; hooks empty

## Staging deploy order

1. Backup DB, plugins, `$CFG->auth`, theme
2. Deploy `theme/farabi`, `local/kaznu`, `auth/univer` files
3. `php admin/cli/upgrade.php`
4. Confirm: `php local/kaznu/cli/enable_unified_login.php --status` → flag=0, auth_univer not in $CFG->auth
5. Purge caches
6. Pre-enable smoke (login still legacy until flag)
7. Ensure `univer_enabled=1` for API if testing Univer
8. `php local/kaznu/cli/enable_unified_login.php --enable --migrate-nologin`
9. Purge caches
10. Post-enable smoke + browser matrix + PHPUnit
11. Rollback rehearsal (`--disable`, optional theme restore)

## Auth success contract

```
loginpage_hook POST
  flag off? → return (core only)
  authenticate()
  OK → wipe password → complete_user_login once → redirect (core abandoned)
  HARD → wipe → notify → redirect login (no core)
  UNAVAILABLE && !local_fallback → wipe → notify → redirect
  else if local_fallback → KEEP password → return (core tries)
  else → wipe → invalidlogin → redirect
```

## Role policy

- Ordinary non-staff (auth manual|email|nologin|univer) → portal_user
- Linked → farabi_student (+ portal_user)
- Teaching roles / admin → not portal

## Cleanup next version

- Delete `univer_login_form.php`, `login_button.php`
- Remove CTA lang strings
- Drop legacy `portalstudent`-only paths after migration period

## Production later

Use new stamp e.g. `20260807XX` / `1.0.0` / MATURITY_STABLE — **do not** ship this staging number as final.
