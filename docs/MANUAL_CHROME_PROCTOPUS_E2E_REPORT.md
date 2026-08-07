# MANUAL CHROME PROCTOPUS E2E REPORT

**Site:** https://farabischool.kz  
**Scope:** CMID **201** only (Quiz 22 / Course 24 hidden)  
**Date:** 2026-08-07  
**Method:** Real Chrome **Default** profile (`User Data` path), remote-debugging only — **no** Incognito, **no** `--load-extension`, **no** cloned temp profile, **no** headless  
**JWT/secret/algorithm/payload:** **unchanged** (Farabi Business secret, HS256, Code.php body, `farabi_business`)

---

## Chrome profile

| Item | Result |
|------|--------|
| Chrome profile | **Default** (interactive User Data) |
| Incognito | **NO** |
| Chrome version | **Chrome/151.0.7922.76** |

---

## Extension

| Item | Result |
|------|--------|
| Installed | **YES** (package under Default/Extensions, version folder **1.0.4**) |
| Enabled | **YES (package present / prior Web Store install)** — `chrome://extensions` shadow DOM text not fully readable via automation; not force-loaded |
| Version | **1.0.4** |
| Allow in incognito | **N/A** (incognito not used) |
| Site access | Package declares broad `host_permissions` (http/https) + `externally_connectable` for `*.kaznu.kz` / `*.kaznu.edu.kz` / localhost — **not** farabischool.kz (provider page is on `farabi-proctoring.kaznu.kz`, so should be in scope) |
| Extension errors | **None observed** in file prefs / no Errors text captured |

---

## Provider

| Item | Result |
|------|--------|
| JWT accepted | **YES** (host `farabi-proctoring.kaznu.kz`, title **Farabi Proctoring**, no «Неправильный токен!») |
| Extension detected | **NO** |
| Web Store redirect | **YES** |
| Provider UI opened | **YES** (briefly) then redirect |

### Redirect path (redacted)

1. `https://farabischool.kz/mod/quiz/view.php?id=201`  
2. `https://farabi-proctoring.kaznu.kz/integration/simple/farabi_business/start/?token=[redacted]`  
3. `https://chromewebstore.google.com/detail/farabi-proctoring-system/hmagmabgealhgocgpapilfpdfcciekag`  

**url_before_store:** start endpoint on `farabi-proctoring.kaznu.kz`  
**store_url:** Chrome Web Store Farabi Proctoring System page  

**STOP condition met per APPROVE:** real Default profile + extension package present → still Web Store. **JWT not modified.**

---

## Checks (admission path)

| Item | Result |
|------|--------|
| Camera permission | **NO / NOT OBSERVED** |
| Camera preview | **NO / NOT OBSERVED** |
| Microphone | **NO / NOT OBSERVED** |
| Screen sharing | **NO / NOT OBSERVED** |
| Face check | **NO / NOT OBSERVED** |
| Environment check | **NO / NOT OBSERVED** |
| Admission | **NO** |

---

## Moodle

| Item | Result |
|------|--------|
| Moodle return | **NO** |
| Session admitted | **NO** (launch `started`/`redirected` only → expired after stop) |
| Attempt created | **NO** (count remained **3** pre-existing) |
| Quiz completed / grade | **NOT REACHED** |
| Callback | **NOT OBSERVED** (0 callback rows) |

---

## Console / security

| Item | Result |
|------|--------|
| Console errors | **NOT OBSERVED** (none captured) |
| Token leakage (Apache residual `token=` / `eyJ`) | **NO** |

---

## Rollback

| Item | Result |
|------|--------|
| Flags after rollback | `proctopus_enabled=0`, `proctopus_live_calls_enabled=0` |
| Secret | **cleared** |
| Course 24 | **hidden** |
| Quiz rule 22 | unchanged |

---

## Remaining issue

Provider accepts JWT for **`farabi_business`** but **does not recognize** the Web Store extension **Farabi Proctoring System 1.0.4** as installed/usable on this machine/session and forces install via Chrome Web Store.

This is **not** fixable by Moodle JWT/secret/HS256/payload changes. Likely causes (vendor-side / extension config):

1. Extension ↔ page messaging / identity check failing for tenant `farabi_business`  
2. Extension disabled silently / SW error not visible in automation  
3. Chrome 151 policy / enterprise restriction  
4. Tenant require newer extension or server allowlist of extension IDs  
5. Detection expects non-automation signal even on Default profile  

**APPROVAL REQUIRED** for any contact with Proctopus/Farabi owner on extension detection for `farabi_business`, or a **human-only** re-test after they confirm extension status in `chrome://extensions` UI (manual toggle Site access if needed). Do not change JWT again until vendor confirms.
