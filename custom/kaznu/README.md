# local_kaznu — AI-чат с историей

## Возможности

- Отдельная **история диалогов у каждого пользователя** (как в ChatGPT): список чатов, переключение, удаление.
- Сообщения хранятся в БД Moodle (`local_kaznu_conversation`, `local_kaznu_message`).
- Опция **«Искать в интернете»** — Tavily (рекомендуется) или резервный DuckDuckGo.

## Установка

1. Плагин уже в `custom/kaznu/` — при деплое копируется в `moodle/public/local/kaznu/`.
2. Локально (OSPanel): скопируйте или синхронизируйте в `moodle/public/local/kaznu/`.
3. Админка → **Уведомления** (или CLI): `php admin/cli/upgrade.php`
4. **Настройки сайта → Плагины → Local plugins → KazNU AI**:
   - OpenAI API key
   - Модель (по умолчанию `gpt-4o-mini`)
   - Tavily API key (для стабильного поиска)
5. Страница чата: `/local/kaznu/chat.php` (пункт в навигации для авторизованных пользователей).

## Права

Capability `local/kaznu:useaichat` — по умолчанию у ролей user/student/teacher.
