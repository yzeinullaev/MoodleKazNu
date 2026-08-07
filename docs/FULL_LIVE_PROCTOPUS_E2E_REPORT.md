# FULL LIVE PROCTOPUS E2E REPORT

**Site:** https://farabischool.kz  
**Scope:** CMID **201** / Quiz **22** / Course **24** only  
**When:** 2026-08-07  
**Method:** Real Chrome Default profile + DevTools (no Incognito, no headless, no `--load-extension`, no temp clone)  
**JWT:** secret re-applied for test only; **algorithm/payload/account not changed**  

**Backup:** `/var/backups/proctopus_live_20260806_194116/settings_backup_full_e2e_20260807_075336.json`

---

## Chrome

| Item | Result |
|------|--------|
| Profile | Default (interactive User Data) |
| Chrome version | **151.0.7922.76** |
| Incognito | **NO** |

## Extension

| Item | Result |
|------|--------|
| Extension installed | **NO (current precheck)** — package `…/Extensions/hmagmabgealhgocgpapilfpdfcciekag/1.0.4_0` **missing** from Default profile disk; Secure Preferences has **no settings entry** for extension id (only residual Preferences text mention) |
| Extension version | **NOT PRESENT** (previously 1.0.4; not found on this run) |
| Extension enabled | **NO / NOT OBSERVED** as active install |
| Extension detected (by provider) | **NO** |
| Extension errors | N/A (not installed in profile) |
| Web Store redirect | **YES** |

**Note:** User believed extension installed; workstation precheck before live shows **extension package gone**. Provider behavior matches: install funnel to Chrome Web Store.

---

## JWT / Provider

| Item | Result |
|------|--------|
| JWT accepted | **YES** |
| Provider UI | **YES** briefly (“Farabi Proctoring”) |
| Web Store redirect | **YES** after start |
| Start URL host | `farabi-proctoring.kaznu.kz` account path `farabi_business` |

Path observed:

1. Moodle quiz view id=201  
2. `…/integration/simple/farabi_business/start/?token=[redacted]`  
3. Chrome Web Store Farabi Proctoring System page  

---

## Admission chain

| Item | Result |
|------|--------|
| Camera | **NO / NOT OBSERVED** |
| Microphone | **NO / NOT OBSERVED** |
| Screen sharing | **NO / NOT OBSERVED** |
| Face check | **NO / NOT OBSERVED** |
| Environment check | **NO / NOT OBSERVED** |
| Admission | **NO** |
| Moodle return | **NO** |
| Proctopus active indicator | **NO** (no admit) |

---

## Session / Callback / Attempt

| Item | Result |
|------|--------|
| Session | launch `started`/`redirected` only → expired on fail stop |
| Callback received | **NO** (0 rows) |
| Callback method / schema / verified | **N/A** |
| Attempt created | **NO** |
| Attempt count | **3** (pre-existing; unchanged this run) |
| Quiz completed / grade / review | **NOT REACHED** |
| Duplicate protection | **N/A** (no admit) |

---

## UI (partial)

| Item | Result |
|------|--------|
| Landing CTA | Reachable; proctor required panel present |
| Exam focus / XP hide / timer / questions | **NOT REACHED** (no attempt) |
| Desktop | Launch path exercised |
| Mobile | Not re-run for live admission |

---

## Security

| Item | Result |
|------|--------|
| Console errors | **NOT OBSERVED** (none captured) |
| PHP fatal in probe | **NONE** noted for this flow |
| Token leakage (`token=` / `eyJ` residual access log) | **NO** residual hits after checks |
| Secret leakage in reports | **NO** (length only) |
| JWT not shown | **YES** |

---

## Scope

| Item | Result |
|------|--------|
| Other quizzes affected | **NO** — only quiz 22 has `proctopusrequired=1` |
| Live allowlist | **201 only** |
| Course 24 hidden after test | **YES** (`visible=0`) |

---

## Flags after test

| Item | Result |
|------|--------|
| `proctopus_enabled` | **0** |
| `proctopus_live_calls_enabled` | **0** |
| JWT secret | **cleared** |
| Allowlist retained | **201** (config value; live off) |
| Production after fail | Live **OFF** |

---

## PRODUCTION STATUS

# **FAIL**

Chain required for PASS:

Moodle → Proctopus → **extension detect** → camera/mic → admission → attempt → finish → callback  

**Broken at:** extension detect → always Web Store. Admission and all downstream steps not reached.

---

## BLOCKERS

1. **Farabi Proctoring System is not currently installed in Chrome Default profile** (package directory missing; prefs not showing active enabled extension).  
2. Even with JWT OK, **provider forces Chrome Web Store** until extension is detected.  
3. Cannot complete camera/mic/admission/attempt/callback without a **working Web Store-installed and provider-detected** extension in a normal Chrome profile.  
4. Historical runs with package present also failed detection for `farabi_business` (vendor-side risk remains after reinstall).

---

## ROLLBACK REQUIRED

# **YES — DONE**

Critical gap (no extension + Web Store gate). Live flags and secret disabled immediately per policy.

---

## Next step for a real PASS (human / vendor)

1. In **normal Chrome** open `chrome://extensions` and confirm **Farabi Proctoring System** shows **Enabled**, version, no Errors.  
2. Reinstall from Web Store if missing; enable site access for `https://farabi-proctoring.kaznu.kz`.  
3. Manually launch quiz CMID 201 once human confirms extension stays detected past provider UI.  
4. Only then re-APPROVE for full admit→quiz→callback with live left ON for CMID 201 if PASS.  

Do **not** change JWT/HS256/`farabi_business` payload for this blocker.
