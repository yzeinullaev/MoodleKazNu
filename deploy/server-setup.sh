#!/usr/bin/env bash
# One-time server setup (run as ubuntu with sudo).
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/moodle-kaznu}"
MOODLE_DATA="${MOODLE_DATA:-/var/moodledata}"
REPO_URL="${REPO_URL:-https://github.com/yzeinullaev/MoodleKazNu.git}"
BRANCH="${BRANCH:-master}"
DEPLOY_USER="${DEPLOY_USER:-ubuntu}"

echo "==> Install packages"
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y \
  git nginx mariadb-server \
  php-fpm php-cli php-mysql php-xml php-mbstring php-curl php-zip php-gd php-intl php-soap php-bcmath

echo "==> Create directories"
sudo mkdir -p "$APP_DIR" "$MOODLE_DATA"
sudo chown -R "$DEPLOY_USER:www-data" "$APP_DIR" "$MOODLE_DATA"
sudo chmod 2770 "$MOODLE_DATA"

if [[ ! -d "$APP_DIR/.git" ]]; then
  echo "==> Clone repository"
  git clone -b "$BRANCH" "$REPO_URL" "$APP_DIR"
  cd "$APP_DIR"
  bash "$APP_DIR/deploy/install-moodle-server.sh"
else
  echo "==> Repository already exists at $APP_DIR"
fi

echo "==> Nginx site (adjust server_name)"
sudo tee /etc/nginx/sites-available/moodle-kaznu >/dev/null <<EOF
server {
    listen 80;
    server_name _;
    root $APP_DIR/moodle/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ [^/]\.php(/|$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/moodle-kaznu /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

echo "==> Next steps:"
echo "  1. Create DB: sudo mysql -e \"CREATE DATABASE moodle; CREATE USER 'moodle'@'localhost' IDENTIFIED BY '...'; GRANT ALL ON moodle.* TO 'moodle'@'localhost';\""
echo "  2. Copy config: cp $APP_DIR/config.php.example $APP_DIR/moodle/config.php && nano $APP_DIR/moodle/config.php"
echo "  3. Install: sudo -u www-data php $APP_DIR/moodle/admin/cli/install.php --lang=ru"
echo "  4. Add GitHub deploy public key to ~/.ssh/authorized_keys"
