# Farabi School — `local_kaznu`

Moodle plugin for **Farabi School / Farabi IBS** (КазНУ): landing, courses, XP, and **Univer integration** with a unified student portal.

- **Component:** `local_kaznu`
- **Install path:** `moodle/local/kaznu`
- **Moodle:** 4.5+ (requires `2024100700`)
- **Package stamp (portal auth):** `2026080704` / RC  
- **Does not modify Moodle core**

Companion packages (same release train) live under [`packages/`](packages/):

| Package | Deploy path | Role |
|---------|-------------|------|
| [`packages/theme_farabi`](packages/theme_farabi) | `theme/farabi` | Login + branding (Boost child) |
| [`packages/auth_univer`](packages/auth_univer) | `auth/univer` | Univer-first unified login |

---

## What you get

### Student portal (Phase 6)
- Unified cabinet: [`/local/kaznu/my.php`](my.php)
  - **Everyone (ordinary users):** Overview, My courses  
  - **Univer-linked only:** Schedule, Grades, Attendance  
- Primary nav for portal users: **Мой кабинет** only (no Home / Dashboard / My courses clutter)
- Server-side access guard (calendar, private files, grade overview → cabinet)
- Roles (Variant A): `farabi_portal_user`, `farabi_student` + caps `viewportal` / `viewuniverdata`

### Auth (Phase 6B — optional enable)
One form on `/login/index.php`:

```
Farabi School
Образовательная платформа
Login · Password · [Sign in]
```

Backend: **Univer API first** → on success sync user/subjects/cabinet → `complete_user_login`.  
Fallback to local Moodle only for allowlisted methods (`manual,email` by default) when safe.

> Kill switch: `local_kaznu/univer_unified_login_enabled` (default **0**).  
> `auth_univer` install does **not** auto-enable itself.

### Univer integration (Phases 2–5)
- Read-only Univer API client (HTTPS, rate limit, cookie jar cleared after login)
- JIT users: username = Univer login, technical email `univer_{id}@users.invalid`
- Subjects → enrol into existing Moodle courses (`UNIVER:{subjectId}:{year}:{semester}`)
- Cabinet caches: schedule, exams, midterms, assessments, attendance
- Gradebook is **never** written by Univer data

### Landing / marketing / XP (earlier work)
- Brand landing [`landing.php`](landing.php), course hub, payment token flow
- XP / quiz awards (local tables + tasks)

---

## Repository layout

```
.
├── README.md                 ← this file
├── version.php
├── lib.php                   # hooks: access guard, navigation
├── my.php                    # unified cabinet
├── login_univer.php          # redirect → /login/index.php only
├── classes/
│   ├── access/               # portal_policy
│   ├── api/                  # Univer HTTP client
│   ├── service/              # login, user, subject, cabinet, …
│   ├── hook/                 # primary_nav, (deprecated) login_button
│   └── local/                # access_guard, portal_role, rate limit
├── cli/
│   ├── enable_unified_login.php
│   ├── phase*_smoke.php
│   └── …
├── docs/                     # design + RC + deploy notes
├── tests/                    # PHPUnit
└── packages/
    ├── theme_farabi/
    └── auth_univer/
```

---

## Install (fresh)

```bash
# 1) Plugin
cd /var/www/moodle/local
sudo rm -rf kaznu
sudo git clone https://github.com/yzeinullaev/MoodleKazNu.git kaznu
sudo chown -R www-data:www-data kaznu

# 2) Theme (optional but recommended)
sudo rsync -a packages/theme_farabi/ /var/www/moodle/theme/farabi/
# or from a checkout of this repo:
# sudo rsync -a /path/to/MoodleKazNu/packages/theme_farabi/ /var/www/moodle/theme/farabi/
sudo chown -R www-data:www-data /var/www/moodle/theme/farabi

# 3) Auth plugin files only — do NOT enable yet
sudo rsync -a packages/auth_univer/ /var/www/moodle/auth/univer/
sudo chown -R www-data:www-data /var/www/moodle/auth/univer

# 4) Upgrade + caches
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

Set theme to **farabi** in Site administration → Appearance (or keep existing).

### Enable unified login (explicit)

```bash
# Status (flag should be 0 right after install of auth plugin)
sudo -u www-data php /var/www/moodle/local/kaznu/cli/enable_unified_login.php --status

# After smoke tests:
sudo -u www-data php /var/www/moodle/local/kaznu/cli/enable_unified_login.php --enable --migrate-nologin
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php

# Disable
sudo -u www-data php /var/www/moodle/local/kaznu/cli/enable_unified_login.php --disable
```

Settings (`local_kaznu`):

| Setting | Default | Meaning |
|---------|---------|---------|
| `univer_enabled` | 0 | API client on/off |
| `univer_unified_login_enabled` | 0 | Kill switch for Univer-first flow |
| `univer_fallback_auth_methods` | `manual,email` | Local auth allowlist on soft fail / limited outage |
| `univer_baseurl` | HTTPS Univer API base | Must be HTTPS |

---

## Update from git

```bash
cd /var/www/moodle/local/kaznu
sudo git pull
sudo rsync -a packages/theme_farabi/ /var/www/moodle/theme/farabi/
sudo rsync -a packages/auth_univer/ /var/www/moodle/auth/univer/
sudo chown -R www-data:www-data .
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```

---

## Smoke (CLI)

```bash
sudo -u www-data php local/kaznu/cli/phase6_rc_smoke.php
sudo -u www-data php local/kaznu/cli/phase6_portal_smoke.php
sudo -u www-data php local/kaznu/cli/phase6b_unified_login_smoke.php
# earlier phases:
# phase2_smoke.php … phase5_smoke.php
```

PHPUnit (on a full Moodle tree with plugins linked):

```
local_kaznu: tests/*_test.php
auth_univer: packages/auth_univer/tests/auth_univer_test.php
```

---

## Auth flow (short)

See [`docs/auth_login_contract.md`](docs/auth_login_contract.md).

```
POST /login/index.php
  → auth_univer::loginpage_hook  (if flag + plugin + API on)
  → login_service::authenticate (Univer)
       OK + STUDENT → sync → complete_user_login once → redirect cabinet
       BAD_CREDENTIALS / NOT_STUDENT → Moodle fallback only if local user
                                        allowlisted and not Univer-bound
       conflict / rate / disabled → fail closed (no local fallback)
```

---

## Demo course (legacy helper)

```bash
sudo -u www-data php /var/www/moodle/local/kaznu/cli/setup_demo.php
```

| | |
|--|--|
| Course | `SUMMER2026` |
| Student | `demo_student` / `Student2026!` |
| Teacher | `demo_teacher` / `Teacher2026!` |

Reset: `setup_demo.php --reset`

---

## Docs

| Doc | Topic |
|-----|--------|
| [`docs/STAGING_DEPLOY_PORTAL_AUTH.md`](docs/STAGING_DEPLOY_PORTAL_AUTH.md) | Staging order, enable flags |
| [`docs/auth_login_contract.md`](docs/auth_login_contract.md) | loginpage_hook / complete_login |
| [`docs/phase6_combined_release_review.md`](docs/phase6_combined_release_review.md) | Full RC review |
| [`docs/phase6b_auth_univer.md`](docs/phase6b_auth_univer.md) | Unified login design |
| [`docs/PHASE6_RC_MANIFEST.md`](docs/PHASE6_RC_MANIFEST.md) | Version matrix |

---

## Security notes

- Student passwords are not stored for Univer accounts (random Moodle hash + `auth=univer` / `prevent_local_passwords`)
- Univer session cookies never kept in Moodle session
- Login rate limits IP + login; HTTPS gate on credential handling
- Cabinet is session-user only (no `userid` query = no IDOR for foreign cabinets)

---

## License

GPLv3 (Moodle ecosystem). Branding and copy: Farabi School / Farabi IBS.

## Maintainer

GitHub: [yzeinullaev/MoodleKazNu](https://github.com/yzeinullaev/MoodleKazNu)
