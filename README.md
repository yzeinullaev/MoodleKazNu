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
  custom/              # ваши плагины → moodle/public/local/
  deploy/              # деплой на сервер
  scripts/             # setup-local-from-zip.ps1
```

## Локально: установить Moodle 5.02

Windows ZIP-инсталлятор **не подходит** (старая структура папок). Используйте официальный архив:

```powershell
cd C:\OSPanel\home\MoodleMerey
powershell -ExecutionPolicy Bypass -File .\scripts\setup-local-official.ps1
```

Перезапустите OSPanel. Корень сайта: `moodle\public` (уже в `.osp/project.ini`).

Создайте `moodledata` вне репозитория (например `C:\OSPanel\userdata\moodledata-kaznu`).

Установка: **http://moodle.local/install.php**

`config.php` и `moodledata/` в Git не попадают.

## Обновление Moodle 5.02 → новая версия

1. Скачайте новый ZIP с moodle.org
2. Замените архив в `moodle/`, снова запустите `setup-local-from-zip.ps1`
3. В админке или CLI: `php admin\cli\upgrade.php`

На сервере поменяйте `MOODLE_VERSION` в `deploy/install-moodle-server.sh` и выполните деплой.

## CI/CD (GitHub → сервер)

При `git push` в `master` выполняется `deploy/deploy.sh` по SSH.

### Secrets (Settings → Secrets → Actions)

| Secret | Значение |
|--------|----------|
| `SSH_HOST` | `94.247.128.224` |
| `SSH_USER` | `ubuntu` |
| `SSH_PRIVATE_KEY` | приватный ключ deploy |

### Deploy-ключ

```powershell
ssh-keygen -t ed25519 -C "github-deploy-moodle" -f "$env:USERPROFILE\.ssh\moodle_deploy" -N '""'
```

- `moodle_deploy.pub` → на сервер: `~/.ssh/authorized_keys`
- `moodle_deploy` (без .pub) → GitHub Secret `SSH_PRIVATE_KEY`

### Первый запуск на сервере

```bash
ssh ubuntu@94.247.128.224
# после git clone репозитория:
bash /var/www/moodle-kaznu/deploy/server-setup.sh
```

## Свои изменения

Только папка `custom/` — при деплое синхронизируется в `moodle/`. Пуш в GitHub обновляет сервер автоматически.

## Безопасность

Не храните пароли и `config.php` в репозитории. Для CI/CD используйте SSH-ключ, не пароль.
