#!/usr/bin/env bash
# ============================================================
# Setup server yaya-gallery (nginx + PHP-FPM 8.4, bukan aaPanel)
# Jalankan sebagai ROOT di server 172.16.1.100:
#   sudo bash setup-server.sh
#
# Asumsi sudah ada di server:
#   - nginx jalan di port 80/443
#   - PHP 8.4 FPM (socket /run/php/php8.4-fpm.sock)
#   - MariaDB/MySQL jalan
#   - cloudflared terpasang (tunnel '9router' sudah aktif)
#
# Yang dikerjakan:
#   1. Siapkan folder site /var/www/html/yaya-gallery
#   2. Clone repo ke sana (atau git pull kalau sudah ada)
#   3. Buat database yaya_gallery + user + import skema
#   4. Tulis app/config.local.php
#   5. Buat site nginx yaya.wflabs.dev (jangan timpa yang sudah ada)
#   6. Set owner folder ke www-data
#   7. Pasang public key deploy ke /root/.ssh/authorized_keys
# ============================================================
set -euo pipefail

SITE_DIR="/var/www/html/yaya-gallery"
SITE_DOMAIN="yaya.wflabs.dev"
NGINX_CONF="/etc/nginx/sites-available/yaya-gallery"
DB_NAME="yaya_gallery"
DB_USER="yaya_gallery"
DB_PASS="YayaGallery_2026!"
APP_DB_HOST="localhost"

echo "==> [1/7] Siapkan folder site $SITE_DIR"
mkdir -p "$SITE_DIR"

echo "==> [2/7] Clone repo ke $SITE_DIR"
if [ -d "$SITE_DIR/.git" ]; then
  echo "    sudah ada repo, jalankan git pull --ff-only"
  git -C "$SITE_DIR" pull --ff-only
else
  git clone https://github.com/repowahyuillahi/yaya-gallery.git "$SITE_DIR"
fi

echo "==> [3/7] Buat database $DB_NAME + user $DB_USER + import skema"
mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
mysql -u root "$DB_NAME" < "$SITE_DIR/database/database.sql"
echo "    skema database diimport."

echo "==> [4/7] Tulis $SITE_DIR/app/config.local.php"
cat > "$SITE_DIR/app/config.local.php" <<EOF
<?php
\$DB_HOST = '${APP_DB_HOST}';
\$DB_NAME = '${DB_NAME}';
\$DB_USER = '${DB_USER}';
\$DB_PASS = '${DB_PASS}';
EOF
chown www-data:www-data "$SITE_DIR/app/config.local.php"
chmod 640 "$SITE_DIR/app/config.local.php"

echo "==> [5/7] Siapkan site nginx $SITE_DOMAIN"
if [ ! -f "$NGINX_CONF" ]; then
  cat > "$NGINX_CONF" <<EOF
server {
    server_name ${SITE_DOMAIN};
    root ${SITE_DIR};
    index index.php;
    client_max_body_size 3M;

    location / { try_files \$uri \$uri/ /index.php; }
    location ~ \.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME /index.php;
    }
}
EOF
  ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/yaya-gallery
  echo "    site nginx dibuat & enabled."
else
  echo "    site nginx sudah ada, lewati (cek /etc/nginx/sites-enabled/yaya-gallery)."
fi
nginx -t && systemctl reload nginx

echo "==> [6/7] Set owner folder site ke www-data"
chown -R www-data:www-data "$SITE_DIR"
chmod -R 755 "$SITE_DIR"
mkdir -p "$SITE_DIR/uploads/dresses" "$SITE_DIR/uploads/receipts" "$SITE_DIR/uploads/logo"
chmod -R 775 "$SITE_DIR/uploads"

echo "==> [7/7] Pasang public key deploy ke /root/.ssh/authorized_keys"
mkdir -p /root/.ssh
chmod 700 /root/.ssh
if ! grep -q "github-actions-yaya-gallery" /root/.ssh/authorized_keys 2>/dev/null; then
  echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIG0gCBT7/7UbVx7+WqJ8IuIcGFx1av4Ig0S1WLQ7/cMy github-actions-yaya-gallery" >> /root/.ssh/authorized_keys
  echo "    public key ditambahkan."
else
  echo "    public key sudah ada, lewati."
fi
chmod 600 /root/.ssh/authorized_keys

echo ""
echo "============================================================"
echo "Selesai. Langkah manual tersisa:"
echo "  1. Pastikan cloudflared tunnel '9router' sudah mengarah"
echo "     subdomain ${SITE_DOMAIN} ke 127.0.0.1:80."
echo "  2. Login awal aplikasi: admin / admin123 (ganti setelah masuk)."
echo ""
echo "Untuk CI/CD GitHub, isi Secrets di repo:"
echo "  DEPLOY_HOST    = 172.16.1.100   (atau pakai cloudflared SSH relay)"
echo "  DEPLOY_USER    = root"
echo "  DEPLOY_SSH_KEY = isi private key (file yaya_deploy)"
echo "  DEPLOY_PORT    = 22"
echo "  DEPLOY_PATH    = $SITE_DIR"
echo "============================================================"
