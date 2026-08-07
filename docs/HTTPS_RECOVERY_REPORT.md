# HTTPS RECOVERY REPORT

**Date:** 2026-08-06  
**Scope:** TLS / Apache only — Moodle plugins, DB, auth **not** modified  
**Proctopus / faculty work:** stopped (not continued)

---

## Root cause

**Server TLS was already healthy:** Let's Encrypt certificate for `farabischool.kz` (+ `www`) was **valid**, Apache listened on **443**, vhost pointed at live LE paths, `openssl verify` → **OK**, site returned **HTTP 200** on `/login/index.php` with `Secure` cookies.

Likely client-side symptoms earlier (agent SSL/TLS trust failure, incomplete intermediate trust for **YE1 / Root YE** chain in some environments) — **not expired cert and not dead Apache**.

Server-side improvements applied:
1. Backup of Apache SSL vhost + LE options  
2. Confirmed `ssl` + `rewrite` modules  
3. **Canonical www → apex** 301 on SSL vhost  
4. `apachectl configtest` → Syntax OK + Apache reload  
5. Moodle cache purge (no config change)

**No new certificate issued** (existing valid 87 days).

---

## Report fields

| Field | Value |
|-------|--------|
| **Root cause** | TLS stack valid on server; previous client verify / trust issues; www apex redirect was only soft (Moodle 303) |
| **System time** | OK — NTP synced, UTC (`Thu 2026-08-06 17:35 UTC`) |
| **DNS** | `farabischool.kz` + `www` → **94.247.128.224** |
| **Port 443** | Listening (Apache) |
| **Certificate subject** | `CN=farabischool.kz` |
| **Certificate issuer** | Let's Encrypt **YE1** |
| **Valid from** | 2026-08-04 16:57:10 GMT |
| **Valid until** | 2026-11-02 16:57:09 GMT (**~87 days**) |
| **SAN** | `DNS:farabischool.kz`, `DNS:www.farabischool.kz` |
| **Apache configtest** | **Syntax OK** |
| **Active SSL vhost** | `*:443 farabischool.kz` → `/etc/apache2/sites-enabled/farabischool-le-ssl.conf` |
| **Certificate paths** | `SSLCertificateFile` …`/live/farabischool.kz/fullchain.pem`<br>`SSLCertificateKeyFile` …`/live/farabischool.kz/privkey.pem` |
| **Certbot renewal dry-run** | **Success** — simulated renew of farabischool.kz + www |
| **Apache reload** | **OK** after www-apex rewrite |
| **HTTP → HTTPS** | **301** → `https://farabischool.kz/...` |
| **www → apex** | **301** `https://www…` → `https://farabischool.kz/...` (Apache rewrite) |
| **Moodle login** | **HTTPS 200** `/login/index.php`; `Set-Cookie: … secure; HttpOnly` |
| **`$CFG->wwwroot`** | `https://farabischool.kz` (unchanged) |
| **`sslproxy`** | Not present / not required |
| **Errors remaining** | AH00558 FQDN warning (cosmetic); probe 404s/scanners in vhost error log — unrelated to TLS; OCSP URI absent on ECDSA cert (certbot info only) |
| **Production status** | **HTTPS OK** — site reachable with valid LE cert |

### External checks (agent)

| Check | Result |
|-------|--------|
| `https://farabischool.kz/login/index.php` | **200** |
| `https://www.farabischool.kz/` | **301** → `https://farabischool.kz/` |
| `http://farabischool.kz/` | **301** → `https://farabischool.kz/` |

### Moodle / Proctopus

| Item | Status |
|------|--------|
| Moodle code / plugins / DB / auth settings | **Not changed** |
| Proctopus / faculty deploy | **Not continued** |

### Backup

`/home/ubuntu/backups/https_recovery_20260806_173944/` (vhost + options-ssl-apache)

---

## Production status: **HTTPS RESTORED / CONFIRMED HEALTHY**

Next steps (only if user still sees trust errors in a specific browser):

1. Clear browser HSTS cache / try another network  
2. Confirm device CA store is current  
3. Only then consider reissue with alternate preferred chain — **not required now**

Proctopus / faculty work remains deferred until you re-approve.
