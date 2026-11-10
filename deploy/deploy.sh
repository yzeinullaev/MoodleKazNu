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
  rsync -a --delete \
    --exclude='.gitkeep' \
    "$APP_DIR/custom/" "$MOODLE_ROOT/public/local/"
fi

if [[ -f "$MOODLE_ROOT/config.php" ]]; then
  echo "==> Moodle upgrade"
  sudo -u www-data php "$MOODLE_ROOT/admin/cli/maintenance.php" --enable || true
  sudo -u www-data php "$MOODLE_ROOT/admin/cli/upgrade.php" --non-interactive
  sudo -u www-data php "$MOODLE_ROOT/admin/cli/maintenance.php" --disable || true
  sudo -u www-data php "$MOODLE_ROOT/admin/cli/purge_caches.php"
else
  echo "==> config.php not found — run install first (see README)"
fi

echo "==> Deploy finished"
