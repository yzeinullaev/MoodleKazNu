# Отчёт: недоступность farabischool.kz (2026-08-10)

**Дата отчёта:** 2026-08-10  
**Сайт:** https://farabischool.kz  
**Хост:** `94.247.128.224` (VPS Ubuntu 24.04, hostname `39272`)  
**Источники:** `dmesg`, `journalctl`, `sar` (sysstat), Apache `farabischool_access.log` / `error.log` / `farabischool_error.log`, MariaDB status/journal, snapshot RAM/swap/MPM.

---

## 1. Краткий вердикт

Сайт «не отвечал» не из‑за падения Apache и не из‑за бага Moodle-плагинов.  
**Корневая причина: исчерпание RAM (~961 MiB) → OOM killer убил MariaDB (`mariadbd`) в 08:00:14–08:00:17 UTC.**  
Без БД Moodle не может обслуживать PHP-страницы → для пользователей сайт «висит» / ошибки чтения БД / таймауты.

Это **рецидив**: MariaDB/Apache уже убивались OOM **5 и 9 августа**.

| Фактор | Роль |
|--------|------|
| VPS 1 vCPU / ~1 GiB RAM, **без swap** (до митигации) | Базовый ресурсный предел |
| Apache `MaxRequestWorkers 150` (mpm_prefork) | Теоретически убийственный оверхед на 1 GB |
| MariaDB ~240–270 MiB RSS + InnoDB pool 128 MiB | Крупный резидент → цель OOM |
| Нагрузка 07:30–08:00: редактирование курса (AJAX) + массовый скан 07:45 | Триггер пикового давления |
| OOM kill MariaDB 08:00 UTC | Прямая причина недоступности Moodle |

---

## 2. Таймлайн инцидента (UTC, 10 августа 2026)

| Время | Что произошло (доказательство) |
|-------|--------------------------------|
| Ночь 09–10 / утро | MariaDB: `InnoDB: Memory pressure event disregarded` (давление памяти уже есть, pool 128 m) — journal |
| 06:04 | SSH brute-force (`user=mysql` с `193.46.255.86`) — **не** причина даунтайма, фон атак |
| 07:00–07:40 | Нагрузка низкая: `sar -q` load ~0.2–0.4 |
| **07:45** | **Всплеск HTTP: 358 req/min** (access log). Сканер `111.23.140.34` + живые редакторы Moodle |
| **08:00:14–17** | **OOM:** `Out of memory: Killed process … (mariadbd)` — dmesg + journal |
| 08:00:15–17 | `mariadb.service: Failed with result 'oom-kill'`. Restart counter **4** |
| 08:00:21–24 | Apache/PHP: `Error Cannot kill process` — залипшие воркеры |
| 08:00:21 | Moodle PHP: *«Ошибка чтения из базы данных…»* (client `2.134.86.171`, referer `modedit.php`) |
| 08:00:22–42 | MariaDB crash recovery LSN, recovery ~1070 pages; **ready** 08:00:42 |
| 08:00:18 | **sar:** load **94.55 / 84.36 / 51.32**, run queue **79** — сервер в thrash |
| ~08:03 | Graceful restart Apache (митигация MPM), `long lost child came home` |
| После | Swap 1 GiB, `MaxRequestWorkers 25`. Health: `/` 302, landing 200 |

**Окно отказа для пользователей:** примерно **08:00–08:01** (DB down) + остаточный перегруз и swap thrash (load 15–90 в последующие минуты диагностики).

---

## 3. Доказательства по источникам

### 3.1 Kernel OOM (dmesg) — главный факт

```
[Mon Aug 10 08:00:14 2026] Out of memory: Killed process 585729 (mariadbd)
  total-vm:1108460kB, anon-rss:247612kB, ...
```

**История OOM (тот же VPS):**

| Когда | Убит процесс |
|-------|----------------|
| Wed Aug 5 12:42 | `mariadbd` (~277 MiB RSS) |
| Sun Aug 9 12:26 | `mariadbd` (~261 MiB) |
| Sun Aug 9 12:32 | `apache2` |
| Sun Aug 9 13:02 | `apache2` |
| Sun Aug 9 13:10 | `apt-check` |
| **Mon Aug 10 08:00** | **`mariadbd` (~248 MiB)** |

Вывод: это **хронический дефицит памяти**, не разовый сбой диска/конфига Moodle.

### 3.2 systemd / MariaDB

- `Failed with result 'oom-kill'`
- Restart job, counter **4**
- Recovery: checkpoint → recover 1070 pages → ready ~08:00:42
- После перезапуска (снятие ~5 мин): Memory ~42 MiB, peak 255 MiB (+ swap)
- `innodb_buffer_pool_size = 128 MiB`
- До/после: записей `Memory pressure event disregarded` — InnoDB **не** сжимает pool ниже 128 m

### 3.3 SAR (sysstat) — пик нагрузки

| Метка | runq-sz | ldavg-1 | ldavg-5 | ldavg-15 |
|-------|---------|---------|---------|----------|
| 07:40 | 7 | 0.40 | 0.31 | 0.34 |
| **08:00** | **79** | **94.55** | **84.36** | **51.32** |

Load ушёл с «спокойного» в катастрофу **синхронно с OOM/рестартом БД** (конкуренция PHP + recovery + thrash).

### 3.4 Apache access — что грузило HTTP (07:00–09:00)

**Топ IP:**

| Запросы | IP | Интерпретация |
|--------:|-----|----------------|
| 552 | `2.134.86.171` | Живой Chrome (редактирование/AJAX) |
| 460 | `89.218.8.125` | Живой Chrome macOS |
| 338 | `111.23.140.34` | **Сканер** (wp-admin, nacos, solr, phpMyAdmin…) |
| … | bots/Applebot | фон |

**Топ path (окно 07–09):**  
`core_courseformat_get_state`, `draftfiles_ajax`, `tiny_autosave_*`, `modedit.php`, `section.php` — **реальная работа в конструкторе курса**, не «тихий» idle.  
Плюс **358 req за минуту 07:45** — совпадение скана + AJAX.

**HTTP statuses (07–09):** 200×1061, 404×213 (сканы), 500×8 (DB outage хвост).

### 3.5 Apache error

- 08:00:22–24: `Cannot kill process` — воркеры/PHP в ненормальном состоянии при OOM.
- 08:03: graceful restart + `long lost child came home` — дезинфекция после перегруза.
- Error log vhost: массовые **404 на webshell-имена** (`aa.php`, `wp-ws68.php`…) от `4.223.73.90` и login-сканы — **побочные атаки, не root cause**; скриптов «not found» = успешных шеллов в этих запросах **не видно**.

### 3.6 Ресурсы VPS (после частичного recovery)

| Параметр | Значение |
|----------|----------|
| CPU | 1 vCPU |
| RAM | 961 MiB |
| Swap | 1.0 GiB (добавлен при митигации; **раньше 0**) |
| Disk `/` | 8.4 G / 20 G (45%) — **не** причина |
| Apache MPM сейчас | StartServers 2, MaxRequestWorkers **25**, MaxConn/Child 500 |
| Ранее | MaxRequestWorkers **150** (несовместимо с 1 GB) |

### 3.7 Moodle cron

`* * * * * www-data php …/admin/cli/cron.php` — штатно. **Не доказан** как триггер 08:00 (нет всплеска cron-only в access; основной spike — browser AJAX + scanner).

---

## 4. Причинная цепочка (why)

```text
[1] Недостаточная ёмкость VPS (1 CPU / ~1 GiB RAM)
        +
[2] Агрессивный Apache prefork (до 150 воркеров)
        +
[3] MariaDB + PHP Moodle (тяжёлые страницы / AJAX редактора)
        +
[4] Пик: редактирование курса + outbound scanners ~07:45
        ↓
[5] Исчерпание free RAM (swap отсутствует → мгновенный hard fail)
        ↓
[6] Linux OOM killer выбирает mariadbd (~250 MiB)
        ↓
[7] Moodle: DB read error / timeout / «сайт не открывается»
        ↓
[8] High load (100+) на thrash + crash recovery DB
```

**Что это НЕ было:**
- не отказ диска;
- не падение только Apache (мастер оставался, воркеры страдали);
- не подтверждённый взлом через успешный webshell по этим 404;
- не специфический дефект Proctopus/finish-flow в логах окна (путь peak — `modedit` / courseformat AJAX).

---

## 5. Митигации, уже применённые (10.08)

1. **Apache mpm_prefork:** `MaxRequestWorkers 150 → 25` (backup conf timestamp `*.bak.20260810080320`).
2. **Swapfile 1 GiB** + fstab — амортизация OOM (медленнее thrash, но лучше жёсткого kill-only).
3. MariaDB auto-restart (systemd) восстановил БД ~за 25–30 с.
4. Проверка снаружи: `https://farabischool.kz/` → 302; landing → **200**.

---

## 6. Риск сейчас

- RAM всё ещё **~1 GiB**; MariaDB + Moodle prefork остаются на грани.
- `innodb_buffer_pool` 128 m + PHP-воркеры легко снова давят память под сессией редактирования/класса.
- Internet scanners идут постоянно (WP, nacos, `.env`).
- Restart MariaDB count **4** — исторический сигнал.

**Без увеличения RAM повторение вероятно** (как 5, 9, 10 августа).

---

## 7. Рекомендации (по приоритету)

| P | Действие | Зачем |
|---|----------|--------|
| P0 | **upgrade VPS ≥ 2–4 GiB RAM** (идеально 2 vCPU) | Убрать хронический OOM |
| P0 | Оставить/закрепить `MaxRequestWorkers ≤ 20–30` на 1–2 GB | Не раздувать prefork |
| P1 | limity MariaDB: `max_connections` 30–50, осторожно с buffer pool | Не отбирать всю RAM |
| P1 | fail2ban / rate-limit / WAF rules на `wp-*`, `.env`, `phpmyadmin`, `nacos` | Срезать 404-шум и drain CPU |
| P1 | Запрет password SSH для системных user / fail2ban sshd | Фон brute force (уже в journal) |
| P2 | PHP-FPM + mpm_event вместо prefork | Лучше контроль памяти на малом VPS |
| P2 | Redis для Moodle session/cache (при ≥2 GB) | Снизить DB load |
| P2 | Мониторинг: alert на load>5, free mem<100 M, OOM, mariadb restarts | Раннее предупреждение |

---

## 8. Чеклист «проверено всё» (scope)

| Область | Статус | Вывод |
|---------|--------|--------|
| dmesg OOM | OK | **Root: kill mariadbd 08:00** |
| journal MariaDB 48h | OK | oom-kill + recovery timeline |
| Apache error global/vhost | OK | sticky workers; DB error PHP; scanner 404 flood |
| Access pattern 07–09 | OK | editors + scanner spike 07:45 |
| SAR load/mem | OK | jump 07:40 → 08:00 (load ~95) |
| Disk | OK | 45% free, not full |
| Swap | OK | was none; now 1G |
| MPM | OK | was 150, now 25 |
| Cron Moodle | OK | every minute; not primary smoking gun |
| SSH attacks | OK | present, secondary |
| Malware via logs | OK | mass 404 probe; no success indicators in sampled errors |
| Public health | OK | site responds after recovery |

---

## 9. Итог одной фразой

**Сайт упал, потому что на VPS с ~1 GiB RAM concurrent Moodle (Apache prefork) + MariaDB исчерпали память; OOM убил MariaDB в 08:00 UTC 10.08.2026 — Moodle без БД перестал нормально отдавать страницы. Триггер пика — нагрузка редакторов и сканеров ~07:45; системная неспособность — слишком маленький сервер и завышенный MaxRequestWorkers.**

*Секреты, JWT, DB passwords в отчёт не включались.*
