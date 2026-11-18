# PROCTOPUS CHROME E2E REPORT

**Site:** https://farabischool.kz  
**Scope:** Course 24 / Quiz 22 / **CMID 201 only**  
**When:** 2026-08-07 (controlled live window)  
**Chrome:** 150.0.7871.125  

---

## Chrome

| Item | Result |
|------|--------|
| Extension installed (on disk / Web Store id package) | **YES** — Farabi Proctoring System **1.0.4** |
| Extension enabled (user Default profile data present) | **YES** (package + Secure Preferences present on workstation) |
| Extension version | **1.0.4** |
| Extension detected by provider during automated E2E session | **NO** — after JWT success, provider redirected to **Chrome Web Store** install page |

Automation note: E2E used system Chrome + session cookies for `proctopus_test_student`, launch CTA, real start URL. Extension loaded via `--load-extension` and via **cloned Default profile** with extension files. Provider still treated extension as **missing** and opened Web Store. JWT/secret/algorithm were **not** changed after that (per APPROVE).

---

## Proctopus path

| Item | Result |
|------|--------|
| JWT accepted | **YES** (title **Farabi Proctoring**, no «Неправильный токен!») |
| Provider UI | **PARTIAL** — brief Farabi Proctoring page → Web Store |
| Extension detected (by provider admission path) | **NO** (redirect to store) |
| Camera permission | **NO / NOT OBSERVED** |
| Camera preview | **NO / NOT OBSERVED** |
| Microphone permission | **NO / NOT OBSERVED** |
| Microphone | **NO / NOT OBSERVED** |
| Screen sharing | **NO / NOT OBSERVED** |
| Face check | **NO / NOT OBSERVED** |
| Browser check | **NO / NOT OBSERVED** |
| Environment check | **NO / NOT OBSERVED** |
| Admission | **NO** |

Permissions API mid-session: `camera=prompt`, `microphone=prompt` (no grant/deny UI completed).

---

## Moodle

| Item | Result |
|------|--------|
| Moodle return | **NO** |
| Signed state / «Прокторинг активен» | **NO** |
| Session | launch rows `started`/`redirected` → closed `expired` (`chrome_e2e_stop`) |
| Attempt created | **NO** (count still **3** pre-existing stub attempts) |
| Attempt count | 3 |
| Duplicate protection | N/A (no admit) |
| Timer / focus mode / quiz complete / grade / review | **NOT REACHED** |

---

## Callback

| Item | Result |
|------|--------|
| Received | **NOT OBSERVED** (`local_proctopus_callback` rows = 0) |
| Status | N/A |

---

## Logs

| Item | Result |
|------|--------|
| JWT leakage in active Apache access (`token=` / `eyJ`) | **NO** residual after check |
| Secret leakage in probe outputs | **NO** (len only) |
| Errors | Provider: extension gate / Web Store redirect; no JWT reject |

---

## Flags after rollback

| Item | Result |
|------|--------|
| `proctopus_enabled` | **0** |
| `proctopus_live_calls_enabled` | **0** |
| JWT secret | **cleared** (`SECRET_LEN=0`) |
| Course 24 | **hidden** |
| Quiz 22 rule | kept (`proctopusrequired=1`) |
| Production status | Live OFF; other quizzes unchanged |
| Remaining issue | Provider still routes to **Chrome Web Store** even when extension package is present in automated Chrome profile — full camera/admission E2E needs **manual run in the user’s interactive Chrome profile** (where the store extension is already installed and active) **or** vendor confirmation of detection for farabi_business domain |
| **APPROVAL REQUIRED** | Manual student Chrome E2E (human, non-automated profile) before treating admission as verified; no auto JWT/algorithm changes without APPROVE |

---

## Observed automation flow (safe hosts)

1. Login OK (cookie session)  
2. `mod/quiz/view.php?id=201` OK  
3. Launch CTA → `farabi-proctoring.kaznu.kz/.../farabi_business/start/?token=[redacted]`  
4. Redirect → `chromewebstore.google.com/.../farabi-proctoring-system/...`  

**Stop condition met:** extension installed but provider does not admit / does not open checks — no JWT reconfiguration.
