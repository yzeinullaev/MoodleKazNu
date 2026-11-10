#!/usr/bin/env bash
# On server: sync Git plugin files, patch upgrade, bump version, run Moodle upgrade.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE_LIVE="${MOODLE_LIVE:-/var/www/moodle}"
PLUGIN="$MOODLE_LIVE/local/kaznu"
PATCH="$APP_DIR/deploy/patches/upgrade-2026091306-ai-history.php"

cd "$APP_DIR"
git pull origin master

echo "==> Sync AI chat files from Git (portal files untouched where paths differ)"
for f in ajax.php chat.js chat_gpt.php styles_chatgpt.css; do
  sudo install -m 0644 -o www-data -g www-data "$APP_DIR/custom/kaznu/$f" "$PLUGIN/$f"
done
sudo install -m 0644 -o www-data -g www-data \
  "$APP_DIR/custom/kaznu/classes/ai_chat_service.php" "$PLUGIN/classes/ai_chat_service.php"
sudo install -m 0644 -o www-data -g www-data \
  "$APP_DIR/custom/kaznu/classes/web_search.php" "$PLUGIN/classes/web_search.php"
sudo mkdir -p "$PLUGIN/classes/privacy"
sudo install -m 0644 -o www-data -g www-data \
  "$APP_DIR/custom/kaznu/classes/privacy/provider.php" "$PLUGIN/classes/privacy/provider.php"

if ! grep -q '2026091306' "$PLUGIN/db/upgrade.php"; then
  echo "==> Patch upgrade.php"
  sudo cp -a "$PLUGIN/db/upgrade.php" "$PLUGIN/db/upgrade.php.bak.$(date +%s)"
  sudo python3 - "$PLUGIN/db/upgrade.php" "$PATCH" <<'PY'
import sys
path, patch = sys.argv[1], sys.argv[2]
with open(path, encoding='utf-8') as f:
    body = f.read()
with open(patch, encoding='utf-8') as f:
    block = f.read()
if '2026091306' in body:
    sys.exit(0)
marker = '    return true;'
if marker not in body:
    raise SystemExit('upgrade.php marker not found')
body = body.replace(marker, block + '\n' + marker)
with open(path, 'w', encoding='utf-8') as f:
    f.write(body)
print('patched')
PY
fi

append_lang() {
  local file="$1"
  local marker="$2"
  local block="$3"
  if sudo grep -q "$marker" "$file" 2>/dev/null; then
    return
  fi
  echo "$block" | sudo tee -a "$file" >/dev/null
}

RU_BLOCK='
$string['"'"'newchat'"'"'] = '"'"'Новый чат'"'"';
$string['"'"'aichat'"'"'] = '"'"'AI Ассистент'"'"';
$string['"'"'typemessage'"'"'] = '"'"'Введите сообщение…'"'"';
$string['"'"'thinking'"'"'] = '"'"'Думаю…'"'"';
$string['"'"'deletechat'"'"'] = '"'"'Удалить чат'"'"';
$string['"'"'websearch'"'"'] = '"'"'Искать в интернете'"'"';
$string['"'"'history'"'"'] = '"'"'История'"'"';
$string['"'"'noapikey'"'"'] = '"'"'OpenAI API key не настроен.'"'"';
$string['"'"'invalidconversation'"'"'] = '"'"'Чат не найден или нет доступа.'"'"';
'
append_lang "$PLUGIN/lang/ru/local_kaznu.php" "newchat" "$RU_BLOCK"

echo "==> Bump version.php"
sudo sed -i "s/\\\$plugin->version   = [0-9]*;/\$plugin->version   = 2026091306;/" "$PLUGIN/version.php"

echo "==> Moodle upgrade"
sudo -u www-data php "$MOODLE_LIVE/admin/cli/upgrade.php" --non-interactive
sudo -u www-data php "$MOODLE_LIVE/admin/cli/purge_caches.php"

echo "==> Done. Test: /local/kaznu/chat_gpt.php"
