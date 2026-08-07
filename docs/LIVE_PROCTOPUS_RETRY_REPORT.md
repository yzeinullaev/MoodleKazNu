# LIVE PROCTOPUS CONTROLLED RETRY REPORT

**Site:** https://farabischool.kz  
**When (server window):** 2026-08-07 ~04:50–04:55 UTC  
**Scope:** CMID **201** / Quiz **22** only  
**Code version:** `local_proctopus` 0.2.3-codephp-payload (`2026080902`)

---

## Config fixes applied

| Item | Value |
|------|--------|
| Secret | Farabi Business key (len **17**); **not** `PotomDam`; stored in Moodle config only during test |
| accountName | `farabi_business` |
| Start path | `/integration/simple/farabi_business/start/` |
| Base URL | `https://farabi-proctoring.kaznu.kz` |
| Algorithm | **HS256** |
| JWT body | Code.php fields only — **no** `iat` / `nbf` / `exp` / `jti` / `iss` |
| Encode | once via `hs_token_service::encode` |
| Live gate | CMID allowlist **201** only |
| Other quizzes | unchanged (`proctopusrequired=1` only quiz 22) |

**Preflight (CLI):** account OK · path contains `farabi_business` · secret ≠ PotomDam · payload has no `kaznu_moodle` · banned claims none · CMID 202 denied.

---

## Results

| Check | Result |
|-------|--------|
| **Provider response** | HTTP **200** server-side → browser entry to start host; browser then landed on **Chrome Web Store** page for extension **“Farabi Proctoring System”** (`chromewebstore.google.com/.../farabi-proctoring-system/...`). No «Неправильный токен!» |
| **JWT accepted** | **YES** (token rejected response **not** shown; flow advanced past start into extension gate) |
| **Proctopus UI opened** | **PARTIAL** — extension install page / store, not full exam readiness UI |
| **Camera** | **NO / NOT OBSERVED** (blocked without Chrome extension) |
| **Microphone** | **NO / NOT OBSERVED** |
| **Admission** | **NO** |
| **Return to Moodle** | **NO** (stopped at extension store) |
| **Attempt created** | **NO** (quiz 22 attempt count still **3** pre-existing) |
| **Token leakage (Apache)** | **NO** residual `token=` / `eyJ` after check (`TOKEN_LEAK_IN_APACHE=NO`) |
| **Flags after rollback** | `proctopus_enabled=0`, `proctopus_live_calls_enabled=0`, **secret cleared** (`SECRET_LEN=0`), course 24 **hidden** |

---

## Sessions (retry)

- Live sessions reached `started` / `result_summary=redirected` (server accepted redirect path).
- Open sessions closed on rollback as `expired` / `retry_stopped_ext_gate`.

---

## Blocking gap for full E2E

Farabi Proctoring requires the **Chrome extension**. Cursor IDE browser cannot install it (`Add to Chrome` disabled / “Switch to Chrome”).

**Next APPROVE needed for:** manual student chrome path with extension installed to observe camera/mic/admission/return/attempt.

---

## Production status

Live Proctopus **OFF**. Secret empty. Rule only on hidden test quiz 22. Account/path remain `farabi_business` (safe, no secret).
