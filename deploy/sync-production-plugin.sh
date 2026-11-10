#!/usr/bin/env bash
# Sync custom/kaznu to the live Moodle tree without deleting existing server files.
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE_LIVE="${MOODLE_LIVE:-/var/www/moodle}"
SRC="$APP_DIR/custom/kaznu"
DEST="$MOODLE_LIVE/local/kaznu"

if [[ ! -d "$SRC" ]]; then
  echo "Missing $SRC — git pull first"
  exit 1
fi

echo "==> Backup $DEST"
ts="$(date +%Y%m%d%H%M%S)"
sudo cp -a "$DEST" "${DEST}.bak.${ts}"

RSYNC_EXTRA=()
if [[ "${FORCE:-0}" == "1" ]]; then
  echo "==> FORCE=1: overwrite matching files from Git"
else
  echo "==> Only add files that are missing on production (existing files kept)"
  RSYNC_EXTRA+=(--ignore-existing)
fi

sudo rsync -av "${RSYNC_EXTRA[@]}" \
  --exclude='.gitkeep' \
  "$SRC/" "$DEST/"
sudo chown -R www-data:www-data "$DEST"

echo "==> Upgrade Moodle plugin"
sudo -u www-data php "$MOODLE_LIVE/admin/cli/upgrade.php" --non-interactive
sudo -u www-data php "$MOODLE_LIVE/admin/cli/purge_caches.php"
echo "==> Production plugin sync done"
