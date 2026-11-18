#!/usr/bin/env bash
# Run ON the server as root after: cd /var/www/moodle-kaznu && git pull
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE="${MOODLE:-/var/www/moodle}"
SRC="$APP_DIR/custom/kaznu"
DEST="$MOODLE/local/kaznu"
if [[ ! -d "$SRC/thesis" ]]; then
  echo "Missing $SRC/thesis — git pull master first (commit 157b11d+)"
  exit 1
fi
rsync -av --delete \
  --exclude='.git' --exclude='docs/' --exclude='tests/' \
  "$SRC/" "$DEST/"
chown -R www-data:www-data "$DEST"
sudo -u www-data php "$MOODLE/admin/cli/upgrade.php" --non-interactive
sudo -u www-data php "$MOODLE/admin/cli/purge_caches.php"
echo "OK: kaznu version $(grep version "$DEST/version.php" | head -1)"