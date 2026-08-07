# Code.php — line-level review (legacy Proctopus reference)

**Source found:** `C:\Users\Yernur\Downloads\Code.php` (6613 bytes, 2026-08-06)  
Also: `C:\Users\Yernur\Downloads\Telegram Desktop\Code.php`  
**Do not copy into production plugins.**

---

## Line-level findings

| Lines | Finding | Severity |
|-------|---------|----------|
| **28–43** | `create_attempt()` — manual `$DB->insert_record('quiz_attempts', $obj)` | Critical — Moodle Quiz API violation |
| **40** | Fixed `layout = "2,0,1,0,3,0"` | Critical |
| **41** | Fixed `attempt = 1` | Critical |
| **42** | Fixed `uniqueid = 90` | Critical — collision / broken question engine |
| **51** | Hardcoded JWT secret `$key = "PotomDam"` | Critical — credential in source |
| **83** | `accountName => 'kaznu_moodle'` | High — wrong tenant (target: `farabi_business`) |
| **103–104, 109, 140** | Domains `dl.kaznu.edu.kz` | High — wrong host |
| **107–109** | JWT in query: `...?token=` + `JWT::encode` **without algorithm arg** | Critical |
| **116** | `CURLOPT_VERBOSE => 1` | High — secret/URL leak via verbose curl |
| **122** | `debugging("Proctoring request to $url, …")` — **full URL with JWT** | Critical |
| **139–142, 146–156** | Raw `$_GET` for `num`/`cmid`/`name`/`t`/`g` | High |
| **139–142** | State-changing redirect driven by **GET** | High — CSRF/prefetch |
| **154–156, 167** | Browser-provided `name`, open/close times trusted; not loaded only from DB | High |
| **entire file** | No callback handler → no signature verify / replay / idempotency | Critical (missing) |
| **81** | `thirdName => $USER->username` | Medium — wrong field semantics / PII shape |
| **86** | Fixed `duration => 90` | Medium |
| **67–73** | Rules from `availability->c[0]->rules` | Medium — unreliable |
| **2–6** | `require_login` on entry but vendor autoload + standalone script outside plugin package | High packaging/security |
| **103** | `sessionUrl` hardcodes `attempt=1` | Critical |
| **89–93** | HTML in `examDesc` without confirmed contract | Medium |
| **108** | Placeholder start URL `…/ВАШ-АПИ/start/` | Incomplete / env-specific |

## Reusable concepts only

- Idea of building a launch payload (user lite + exam meta + session/redirect URLs)
- Using Moodle `curl` class (but options must drop VERBOSE and must not log token URL)
- ISO8601 window dates when taken from **server-side quiz record**, not `$_GET`

## Rejected implementation

All of the above patterns; rebuild only via `local_proctopus` + `quizaccess_proctopus` (already scaffolded offline).

## Legacy values → target

| Code.php | Target Farabi |
|----------|---------------|
| `kaznu_moodle` | `farabi_business` |
| `dl.kaznu.edu.kz` | `https://farabischool.kz` / `$CFG->wwwroot` |
| `PotomDam` secret | env / rotated admin secret — **never this value** |
| `uniqueid=90`, `attempt=1` | Moodle Quiz startattempt API only |
| JWT in query + debug URL | Prefer POST/header; never log full token URL |

## Security issues (summary)

Secrets, quiz integrity, AuthZ (cmid + browser times), callback absence, logging of JWT URL — all **fail**. Secret string in file is **exposed**; treat as compromised and **do not** reuse for Farabi production.
