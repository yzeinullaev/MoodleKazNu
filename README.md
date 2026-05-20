# MoodleKazNu — local plugin `kaznu`

Плагин для Moodle 4.5 без изменения ядра. Размещается в `moodle/local/kaznu/`.

## Установка на сервер

```bash
cd /var/www/moodle/local
sudo rm -rf kaznu
sudo git clone https://github.com/yzeinullaev/MoodleKazNu.git kaznu
sudo chown -R www-data:www-data kaznu
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/local/kaznu/cli/setup_demo.php
```

## Демо-курс

После `setup_demo.php`:

| | |
|---|---|
| Курс | `SUMMER2026` — Летняя онлайн-школа КазНУ (демо) |
| Студент | `demo_student` / `Student2026!` |
| Преподаватель | `demo_teacher` / `Teacher2026!` |

Пересоздать курс: `php local/kaznu/cli/setup_demo.php --reset`

## Разработка

Все изменения — только в этом репозитории. После push на сервере:

```bash
cd /var/www/moodle/local/kaznu && sudo git pull
sudo -u www-data php /var/www/moodle/admin/cli/upgrade.php --non-interactive
sudo -u www-data php /var/www/moodle/admin/cli/purge_caches.php
```
