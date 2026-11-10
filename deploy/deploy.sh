#!/usr/bin/env bash
# Run on server after git pull (also used by GitHub Actions).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE_ROOT="${MOODLE_ROOT:-$APP_DIR/moodle}"
MOODLE_DIR="${MOODLE_DIR:-$MOODLE_ROOT/public}"
BRANCH="${DEPLOY_BRANCH:-master}"

cd "$APP_DIR"

echo "==> Fetch latest code ($BRANCH)"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

if [[ ! -f "$MOODLE_ROOT/lib/components.json" ]]; then
  echo "==> Moodle core missing — installing from moodle.org"
  bash "$APP_DIR/deploy/install-moodle-server.sh"
fi

if [[ -d "$APP_DIR/custom" ]]; then
  echo "==> Sync custom plugins"
  sudo rsync -a --delete \
    --exclude='.gitkeep' \
    "$APP_DIR/custom/" "$MOODLE_ROOT/public/local/"
  sudo chown -R www-data:www-data "$MOODLE_ROOT/public/local"
fi

MOODLE_CLI="$MOODLE_DIR"
if [[ ! -f "$MOODLE_CLI/admin/cli/upgrade.php" && -f "$MOODLE_ROOT/admin/cli/upgrade.php" ]]; then
  MOODLE_CLI="$MOODLE_ROOT"
fi

CONFIG_PHP=""
for candidate in "$MOODLE_DIR/config.php" "$MOODLE_ROOT/config.php"; do
  if [[ -f "$candidate" ]]; then
    CONFIG_PHP="$candidate"
    break
  fi
done

if [[ -n "$CONFIG_PHP" ]]; then
  echo "==> Moodle upgrade ($MOODLE_CLI)"
  sudo -u www-data php "$MOODLE_CLI/admin/cli/maintenance.php" --enable || true
  sudo -u www-data php "$MOODLE_CLI/admin/cli/upgrade.php" --non-interactive
  sudo -u www-data php "$MOODLE_CLI/admin/cli/maintenance.php" --disable || true
  sudo -u www-data php "$MOODLE_CLI/admin/cli/purge_caches.php"
else
  echo "==> config.php not found — run install first (see README)"
fi

echo "==> Deploy finished"
