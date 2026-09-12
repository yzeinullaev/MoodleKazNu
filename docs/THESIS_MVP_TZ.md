# ТЗ MVP — Нормоконтроль диссертации + маршрут + ЭЦП

**Модуль:** `local_kaznu` (подсистема `thesis` / `dissertation`)  
**Контур:** локальный Docker `C:\OSPanel\home\MoodleKazNu`, затем прод  
**Статус документа:** черновик MVP для реализации  
**Дата:** 2026-09-13

---

## 1. Цель и границы

### Цель
Студент загружает работу (диплом / магистерскую / черновик диссертации) → система проверяет **оформление по правилам вуза** → выдаёт отчёт → назначенный преподаватель может **подписать допуск ЭЦП**.

### Это НЕ
- замена НЦГНТЭ / официального антиплагиата PhD;
- присуждение степени / решение диссовета;
- «ИИ одобрил диссертацию» как юридический факт.

### Позиционирование в UI
> Рекомендательный нормоконтроль и внутренний электронный допуск научрука.  
> Официальная проверка заимствований для PhD — по регламенту вуза / НЦГНТЭ.

---

## 2. Роли

| Роль Moodle / кастом | Действия MVP |
|----------------------|--------------|
| **student** | загрузка файла, просмотр отчётов, повторная загрузка, отправка научруку |
| **supervisor** (назначенный преподаватель) | очередь работ, просмотр отчёта, замечания текстом, подпись ЭЦП / отклонение |
| **normocontrol** (опционально, фаза 1.1) | та же очередь, без права «научного» допуска — только «формат OK» |
| **manager / admin** | CRUD правил оформления, включение модуля, назначение научрука, журнал ЭЦП |

Назначение научрука: вручную админом или поле `supervisorid` на заявке (MVP — вручную).

---

## 3. Статусы заявки (`submission`)

```
draft          → студент загрузил, ещё не отправил
checking       → идёт парсинг / AI-пояснения
checked        → отчёт готов, студент может править и перезалить
submitted      → отправлено научруку
revision       → научрук вернул с замечаниями
approved_unsigned → «ок по смыслу», ждёт ЭЦП (опционально объединить с approved)
approved       → подписано ЭЦП (финал MVP)
rejected       → отказ с комментарием
```

Переходы:

| Из | В | Кто |
|----|---|-----|
| — | draft | student (upload) |
| draft/revision | checking → checked | system |
| checked | submitted | student |
| submitted | revision / approved / rejected | supervisor |
| approved (без ЭЦП-stub) | approved | supervisor + ЭЦП success |

---

## 4. Сущности БД (предложение таблиц)

Префикс Moodle: `mdl_`.

### 4.1 `local_kaznu_thesis_rule_set`
Набор правил (например «Магистратура ИТ 2026»).

| Поле | Тип | Описание |
|------|-----|----------|
| id | int | PK |
| name | char | название |
| worktype | char | bachelor / master / phd_draft |
| enabled | int | 1/0 |
| rulesjson | text | JSON правил (см. §5) |
| timecreated / timemodified | int | |

### 4.2 `local_kaznu_thesis_submission`
| Поле | Тип | Описание |
|------|-----|----------|
| id | int | PK |
| userid | int | студент |
| supervisorid | int | научрук (nullable до назначения) |
| rulesetid | int | FK rule_set |
| title | char | тема работы |
| status | char | см. §3 |
| fileid | int | file area `submission` (Moodle file API) |
| contenthash | char | sha256 файла на момент отчёта |
| reportjson | text | результат нормоконтроля |
| supervisor_comment | text | замечания |
| timecreated / timemodified | int | |

### 4.3 `local_kaznu_thesis_signature`
| Поле | Тип | Описание |
|------|-----|----------|
| id | int | PK |
| submissionid | int | FK |
| signerid | int | преподаватель |
| sigtype | char | `ncalayer` / `stub` (локально) |
| filehash | char | хэш подписанного файла |
| cms_or_sig | text | отсоединённая подпись / CMS (или stub token) |
| cert_subject | text | DN из сертификата |
| valid_from / valid_to | int | |
| verify_ok | int | 1/0 |
| timecreated | int | |

### 4.4 `local_kaznu_thesis_event` (аудит)
| Поле | Описание |
|------|----------|
| submissionid, userid, event, detailjson, timecreated | upload / check / submit / sign / reject |

**File areas (Moodle):**
- `local_kaznu/thesis_submission` — исходный DOCX/PDF
- `local_kaznu/thesis_report` — PDF/HTML отчёт (опционально)
- `local_kaznu/thesis_signed` — файл + `.sig` / CMS

---

## 5. Правила оформления (`rulesjson`) — что парсить без LLM

Источник истины — **детерминированный парсер DOCX (OOXML)**, не ChatGPT.

Пример:

```json
{
  "page": { "size": "A4", "margin_mm": { "top": 20, "bottom": 20, "left": 30, "right": 10 } },
  "body": {
    "font": ["Times New Roman", "TimesNewRoman"],
    "size_pt": 14,
    "line_spacing": 1.5,
    "align": "justify",
    "first_line_indent_cm": 1.25
  },
  "headings": {
    "h1": { "font": ["Times New Roman"], "size_pt": 14, "bold": true, "all_caps": false }
  },
  "structure_required": ["title", "toc", "introduction", "conclusion", "references"],
  "limits": { "min_pages": 40, "max_pages": 120 },
  "forbidden": { "fonts": ["Comic Sans MS"], "colors_in_body": true }
}
```

### Парсер MVP (PHP)
- Библиотека: **PhpOffice/PhpWord** (добавить dependency в плагин или micro-service в Docker) **или** простой unzip+XML (styles.xml, document.xml) без Composer, если нельзя тянуть пакеты.
- Рекомендация для Docker-продукта: отдельный сервис `thesis-checker` (Python `python-docx`) в compose — проще и точнее; Moodle дергает HTTP localhost.

### Результат отчёта (`reportjson`)

```json
{
  "score": 72,
  "passed": false,
  "checks": [
    { "id": "body.font", "ok": false, "expected": "Times New Roman", "found": "Calibri", "count": 14 },
    { "id": "page.margin_left", "ok": true, "expected": 30, "found": 30 }
  ],
  "summary_ru": "…",
  "engine": "docx-rules-v1",
  "ai_hints": null
}
```

**ИИ (опционально, фаза 1.1):** по списку failed-checks сгенерировать понятные советы «как исправить в Word» через уже существующий OpenAI-ключ `local_kaznu`. Текст работы в LLM **не отправлять** целиком в MVP.

---

## 6. Антиплагиат в MVP

**В MVP не строить свою БД заимствований.**

Варианты (выбрать один флаг в settings):
1. `plagiarism_mode = off` — только нормоконтроль;
2. `plagiarism_mode = upload_report` — студент/оператор прикрепляет PDF отчёта Antiplagiat/Strike (% в поле вручную);
3. `plagiarism_mode = api` — заглушка интерфейса под будущий API (не реализовывать вызов без лицензии).

UI: блок «Оригинальность (неофициально / внешний отчёт)» с disclaimer.

---

## 7. ЭЦП

### Локально (Docker)
- Режим **`stub`**: кнопка «Подписать (demo)» пишет в `local_kaznu_thesis_signature` запись `sigtype=stub`, `verify_ok=1`, `filehash=sha256`.
- Нужно, чтобы отладить UI/статусы без NCALayer.

### Прод (фаза 2)
- **NCALayer** (НУЦ РК) в браузере преподавателя:
  1. фронт считает hash файла / получает CMS;
  2. backend проверяет подпись (библиотека / сервис верификации);
  3. сохраняет CMS + subject сертификата;
  4. статус → `approved`.
- Запрет: подпись только своим `signerid === $USER->id`.
- Хранить неизменный `contenthash` файла; при новой загрузке — сбрасывать подпись.

Юр. смысл подписи в MVP: **«Научный руководитель: ознакомлен / допускаю к следующему этапу»**, не защита.

---

## 8. Экраны (маршруты)

Базовый URL: `/local/kaznu/thesis/`

| Страница | Роль | Назначение |
|----------|------|------------|
| `index.php` | student | список моих работ |
| `edit.php` | student | создать / загрузить / перезалить / «Отправить» |
| `report.php` | student+supervisor | отчёт нормоконтроля |
| `queue.php` | supervisor | очередь `submitted` |
| `review.php` | supervisor | замечания / ЭЦП / reject |
| `admin_rules.php` | admin | CRUD rule sets |
| `settings` | admin | enable, plagiarism_mode, eds_mode |

Навигация: пункт в кабинете студента + capability `local/kaznu:thesissupervise`.

Capabilities:
- `local/kaznu:thesisupload`
- `local/kaznu:thesissupervise`
- `local/kaznu:thesismanage`

---

## 9. Архитектура в Docker-продукте

```
[browser] → moodle (local_kaznu)
                ├─ file storage (moodledata volume)
                ├─ MariaDB (thesis_* tables)
                └─ HTTP → thesis-checker:8089  (optional service)
                              └─ python-docx audit → JSON
```

MVP-минимум без отдельного сервиса: PHP-парсер в `classes/service/thesis_format_service.php`.  
Отдельный сервис — если PHP окажется слабым на сложных DOCX.

Volumes уже есть (`moodlekaznu_db_data`, `moodlekaznu_moodledata`) — файлы и БД переживают restart.

---

## 10. План реализации (спринты)

### Sprint A — каркас (3–5 дней)
1. Таблицы + upgrade.php + capabilities + settings (`thesis_enabled`).
2. Студент: upload DOCX → статус draft/checked с **заглушкой отчёта**.
3. Научрук: очередь + comment + approve/reject **без ЭЦП**.

### Sprint B — реальный нормоконтроль
1. Парсер: шрифт тела, размер, поля, line spacing, запрещённые шрифты.
2. Админка rule_set JSON.
3. Отчёт UI (зелёный/красный чеклист).

### Sprint C — ЭЦП stub + аудит
1. Подпись stub + отображение «подписано».
2. Журнал событий.
3. Disclaimer в UI + lang strings (ru/kk/en).

### Sprint D (после MVP)
1. NCALayer.
2. AI-hints только по failed checks.
3. Прикрепление внешнего отчёта антиплагиата.
4. Уведомления Moodle message.

---

## 11. Критерии приёмки MVP

- [ ] Студент загружает DOCX ≤ 50 МБ, видит чеклист по правилам.
- [ ] Смена файла сбрасывает approve/подпись.
- [ ] Научрук видит только назначенные работы.
- [ ] Stub-ЭЦП меняет статус на `approved` и пишет hash+signer.
- [ ] Везде disclaimer про неофициальность / не НЦГНТЭ.
- [ ] Данные в named volumes; `compose down` без `-v` не трогает заявки.
- [ ] Выключенный `thesis_enabled` скрывает пункт меню.

---

## 12. Риски и решения

| Риск | Решение |
|------|---------|
| DOCX «кривой» (не styles, ручной формат) | В отчёте: «низкая уверенность», частичный fail |
| PDF вместо DOCX | MVP: только DOCX; PDF — later |
| Утечка текста в LLM | По умолчанию AI off; hints без полного текста |
| Путаница со статусом защиты | Копирайт + статусы без слова «защищена» |
| Чужой ЭЦП | Только `$USER`, аудит, на проде NCALayer |

---

## 13. Следующий шаг разработки

Начать **Sprint A** в `plugins/local/kaznu`:
- `db/install.xml` + `upgrade.php` (таблицы §4);
- `classes/service/thesis_submission_service.php`;
- страницы `thesis/index.php`, `edit.php`, `queue.php`;
- setting `thesis_enabled`.

После каркаса — Sprint B (парсер шрифт/поля).
