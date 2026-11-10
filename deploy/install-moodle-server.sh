#!/usr/bin/env bash
# Download official Moodle 5.02 on Linux server (without git clone of full tree).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE_ROOT="${MOODLE_ROOT:-$APP_DIR/moodle}"
VERSION="${MOODLE_VERSION:-502}"
TGZ="moodle-latest-${VERSION}.tgz"
URL="https://download.moodle.org/download.php/direct/stable${VERSION}/${TGZ}"

cd "$APP_DIR"

if [[ -f "$MOODLE_ROOT/lib/components.json" ]]; then
  echo "==> Moodle already present in $MOODLE_ROOT"
  exit 0
fi

echo "==> Download $TGZ"
curl -fL "$URL" -o "/tmp/${TGZ}"
tar -xzf "/tmp/${TGZ}" -C /tmp
rm -f "/tmp/${TGZ}"

rm -rf "$MOODLE_ROOT"
mv "/tmp/moodle" "$MOODLE_ROOT"
chown -R www-data:www-data "$MOODLE_ROOT"
echo "==> Moodle ${VERSION} installed to $MOODLE_ROOT (web root: $MOODLE_ROOT/public)"
