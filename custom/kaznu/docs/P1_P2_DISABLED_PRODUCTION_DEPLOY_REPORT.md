# P1/P2 DISABLED PRODUCTION DEPLOY REPORT

**Date:** 2026-08-06  
**Decision context:** APPROVE WITH CONDITIONS — deploy disabled scaffold only  
**Live Proctopus / Faculty enforcement:** NOT attempted  

---

## DEPLOY OUTCOME

| Item | Result |
|------|--------|
| **Production deploy executed** | **NO — blocked** |
| **Blocker** | TCP port **22 (SSH) unreachable** from the deployment agent network (`Connection timed out`) |
| **Port 443** | Reachable to `farabischool.kz` / `94.247.128.224` |
| **plink / OpenSSH / WSL SSH** | All timed out |
| **Production state change** | **None** (no maintenance toggle, no file rsync, no upgrade) |
| **Rollback required** | **No** (nothing applied) |

---

## WHAT WAS PREPARED (ready for one-shot when SSH available)

Artifacts in `dist_p1p2_disabled/`:

| File | Content |
|------|---------|
| `kaznu_p1p2.tgz` | `local_kaznu` **2026080801** / `1.1.0-faculty-filter-scaffold` (no packages/) |
| `local_proctopus_2026080802.tgz` | `local_proctopus` **2026080802** / `0.2.0-legacy-p2` |
| `quizaccess_proctopus_2026080802.tgz` | `quizaccess_proctopus` **2026080802** |
| `deploy_p1p2_disabled_on_server.sh` | Full server-side: backup → deploy → upgrade → force flags OFF → smokes → maintenance off |
| `upload_and_deploy_p1p2.sh` | Client wrapper (requires SSH) |

Server script enforces after install:

- `univer_faculty_filter_enabled=0`
- `univer_allowed_faculty_ids=28` (if empty)
- `univer_login_allowlist=oxikbayev.yernur` (if empty)
- `proctopus_enabled=0`
- `proctopus_live_calls_enabled=0`
- `proctopus_jwt_secret=""` (never stores chat secret)
- runs faculty **dry-run only** (no `--apply`)
- runs `p2_smoke.php` (refuses if live calls on)
- **no Apache rewrite**
- maintenance OFF only if smokes pass

### To finish deploy from a network that can SSH

```bash
# On a machine with SSH access to 94.247.128.224:
export SSH_HOST=ubuntu@94.247.128.224
# optional: export SSH_PASS=... + sshpass
cd dist_p1p2_disabled
bash upload_and_deploy_p1p2.sh
```

Or manually: copy tarballs, extract to `/tmp/p1p2_disabled_src/{kaznu,local_proctopus,quizaccess_proctopus}`, run:

`SRC=/tmp/p1p2_disabled_src bash deploy_p1p2_disabled_on_server.sh`

---

## PREFLIGHT (local package)

| Check | Result |
|-------|--------|
| Secret `PotomDam` / chat key in runtime source | **Not present** |
| `CURLOPT_VERBOSE => 1` | **None** (`=> false` only) |
| Manual `insert_record('quiz_attempts'…)` in runtime | **None** (assert-negation in tests only) |
| `dl.kaznu` / `kaznu_moodle` | **Blocklists / negative tests only** — not used as endpoints |
| Install defaults live/enabled | **0 / 0** |
| Callback default verifier | **disabled_verifier** |
| Callback rewrite deploy | **Not in scripts** |

---

## REPORT FIELDS (as requested) — status when deploy blocked

### Backup / Versions / Upgrade / Cache / Maintenance

| Item | Status |
|------|--------|
| Backup | **Not run** (needs host) |
| Versions on prod | **Unchanged** (not queried — no SSH) |
| Upgrade | **Not run** |
| Cache purge | **Not run** |
| Maintenance | **Not toggled** |

### FACULTY

| Item | Status |
|------|--------|
| Settings deployed | **No** |
| Feature flag | **Remains whatever prod had** (target after deploy: **0**) |
| Dry-run | **Not run** |
| Users changed | **0** |
| Login regression | **N/A — code not pushed** |

### PROCTOPUS

| Item | Status |
|------|--------|
| Plugins installed | **No** |
| Enabled | N/A (target **0**) |
| Live calls | N/A (target **0**) |
| Secret | N/A (target empty; chat secret **not** packaged) |
| Callback | N/A (remain disabled; **no rewrite**) |
| Rewrite | **Not applied** |
| Existing quizzes changed | **No** |

### SMOKE

| Item | Status |
|------|--------|
| Faculty / P2 / Portal / Quiz | **Not run on production** |
| Real HTTP to Proctopus | **None** (agent did not initiate; prod hooks not installed) |

### SECURITY

| Item | Status |
|------|--------|
| Production config secret pollution | **None applied** |
| JWT/secret logs | **N/A** |
| Legacy domains in deploy payload runtime | Guarded only |

### Rollback required

**No**

### Production status

**Unchanged** relative to pre-approval. Scaffold remains local + `dist_p1p2_disabled/` packages only.

### Next approval required

1. **Ops:** restore **SSH reachability** (or run upload script from allowed network / bastion), then re-run this disabled deploy.  
2. After successful **DISABLED** deploy + optional admin stub Quiz: gates still require separate APPROVE for faculty ON, rotated secret, live start, rewrite, callback trust.

---

## NEXT GATES (unchanged — no autonomous progress)

1. facultyId discovery  
2. faculty filter ON  
3. rotated Proctopus secret  
4. controlled live start  
5. callback contract  
6. Apache rewrite  
7. live Quiz enable  

**Stop.** No live enable attempted. No production file changes this turn.
