# MoodleKazNu

Репозиторий для KazNU: **свои файлы в Git**, ядро Moodle **не в Git** (слишком большое).

| Где | Откуда берётся Moodle |
|-----|------------------------|
| Локально (OSPanel) | Официальный `moodle-latest-502.tgz` (скрипт `setup-local-official.ps1`) |
| Сервер (Linux) | Скачивание `moodle-latest-502.tgz` с moodle.org при деплое |

## Структура

```
MoodleKazNu/
  moodle/              # ядро Moodle 5.02 (не коммитится)
    public/            # корень сайта в OSPanel
  custom/kaznu/        # local_kaznu → moodle/public/local/kaznu/
  deploy/              # деплой на сервер
  scripts/             # setup-local-from-zip.ps1
```

## AI-чат (`local_kaznu`)

Плагин в `custom/kaznu/`: история диалогов на пользователя, OpenAI, опциональный поиск в интернете.  
Страница: `/local/kaznu/chat.php`. Настройки: *Плагины → Local plugins → KazNU AI*.

## CI/CD (GitHub → сервер)

При `git push` в `master` выполняется `deploy/deploy.sh` по SSH.

### Secrets (Settings → Secrets → Actions)

| Secret | Значение |
|--------|----------|
| `SSH_HOST` | `94.247.128.224` |
| `SSH_USER` | `ubuntu` |
| `SSH_PRIVATE_KEY` | приватный ключ deploy |
| `APP_DIR` | `/var/www/moodle-kaznu` (опционально) |

### Первый запуск на сервере

```bash
ssh ubuntu@94.247.128.224
bash /var/www/moodle-kaznu/deploy/server-setup.sh
```

## Демо-курс (опционально)

После установки плагина на сервере:

```bash
sudo -u www-data php /var/www/moodle/public/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/public/local/kaznu/cli/setup_demo.php
```

`setup_demo.php` — если файл присутствует в репозитории.

## Безопасность

Не храните пароли и `config.php` в репозитории. Для CI/CD используйте SSH-ключ, не пароль.
