#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════
#  AIDI Panel — Installer v1.0.0
#  https://github.com/rezzaidr/aidipanelforwebinoly
#
#  Cara install:
#    wget -qO install.sh https://raw.githubusercontent.com/rezzaidr/aidipanelforwebinoly/main/install.sh
#    sudo bash install.sh
#
#  Atau langsung:
#    curl -sS https://raw.githubusercontent.com/rezzaidr/aidipanelforwebinoly/main/install.sh | sudo bash
# ════════════════════════════════════════════════════════════════

set -euo pipefail

# ── Warna output ─────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; BOLD='\033[1m'; NC='\033[0m'

ok()   { echo -e "${GREEN}✓${NC} $*"; }
info() { echo -e "${CYAN}→${NC} $*"; }
warn() { echo -e "${YELLOW}⚠${NC}  $*"; }
err()  { echo -e "${RED}✗${NC} $*"; exit 1; }
head() { echo -e "\n${BOLD}${BLUE}$*${NC}"; echo "────────────────────────────────────"; }

# ── Konstanta ─────────────────────────────────────────────────
AIDI_VERSION="1.0.0"
AIDI_PORT=22223
AIDI_DIR="/opt/aidipanel"
AIDI_PANEL_DIR="/var/www/aidipanel"
AIDI_LOG="/var/log/aidipanel/install.log"
WEBINOLY_INSTALLED=false

# ── Banner ────────────────────────────────────────────────────
show_banner() {
  echo ""
  echo -e "${BOLD}"
  echo "   █████╗ ██╗██████╗ ██╗    ██████╗  █████╗ ███╗   ██╗███████╗██╗     "
  echo "  ██╔══██╗██║██╔══██╗██║    ██╔══██╗██╔══██╗████╗  ██║██╔════╝██║     "
  echo "  ███████║██║██║  ██║██║    ██████╔╝███████║██╔██╗ ██║█████╗  ██║     "
  echo "  ██╔══██║██║██║  ██║██║    ██╔═══╝ ██╔══██║██║╚██╗██║██╔══╝  ██║     "
  echo "  ██║  ██║██║██████╔╝██║    ██║     ██║  ██║██║ ╚████║███████╗███████╗"
  echo "  ╚═╝  ╚═╝╚═╝╚═════╝ ╚═╝    ╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝╚══════╝╚══════╝"
  echo -e "${NC}"
  echo -e "  ${CYAN}Server Manager Panel — v${AIDI_VERSION}${NC}"
  echo -e "  ${CYAN}Built for Webinoly Stack${NC}"
  echo ""
}

# ── Cek syarat ────────────────────────────────────────────────
check_requirements() {
  head "Memeriksa Persyaratan"

  # Root
  [[ $EUID -ne 0 ]] && err "Installer harus dijalankan sebagai root (sudo bash install.sh)"
  ok "Running as root"

  # OS Ubuntu
  if ! grep -qi "ubuntu" /etc/os-release 2>/dev/null; then
    err "AIDI Panel hanya mendukung Ubuntu Server"
  fi

  # Ubuntu version
  local ver
  ver=$(grep -oP '(?<=VERSION_ID=")[0-9]+' /etc/os-release)
  if [[ "$ver" -lt 22 ]]; then
    err "Diperlukan Ubuntu 22.04 LTS atau lebih baru (terdeteksi: Ubuntu $ver)"
  fi
  ok "Ubuntu $ver LTS"

  # Cek Webinoly
  if command -v webinoly &>/dev/null || command -v site &>/dev/null; then
    WEBINOLY_INSTALLED=true
    ok "Webinoly terdeteksi"
  else
    warn "Webinoly belum terinstall — akan diinstall jika pilih opsi LEMP"
  fi

  # Cek PHP
  if ! command -v php &>/dev/null; then
    warn "PHP belum terinstall — akan diinstall"
  else
    ok "PHP $(php -r 'echo PHP_VERSION;')"
  fi

  # Cek Nginx
  if command -v nginx &>/dev/null; then
    ok "Nginx $(nginx -v 2>&1 | grep -oP 'nginx/[\d\.]+')"
  else
    warn "Nginx belum terinstall"
  fi

  # RAM minimal 512MB
  local ram_mb
  ram_mb=$(free -m | awk '/^Mem:/{print $2}')
  if [[ "$ram_mb" -lt 512 ]]; then
    warn "RAM kurang dari 512MB ($ram_mb MB). Mungkin lambat."
  else
    ok "RAM: ${ram_mb}MB"
  fi

  # Disk minimal 5GB
  local disk_gb
  disk_gb=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
  if [[ "$disk_gb" -lt 5 ]]; then
    err "Disk tersisa kurang dari 5GB ($disk_gb GB)"
  fi
  ok "Disk tersisa: ${disk_gb}GB"
}

# ── Pilihan instalasi ─────────────────────────────────────────
choose_options() {
  head "Pilihan Instalasi"

  echo -e "Pilih tipe instalasi:\n"
  echo "  1) Panel saja (Webinoly sudah ada)"
  echo "  2) Panel + Install Webinoly (LEMP stack baru)"
  echo ""
  read -rp "Pilihan [1/2]: " INSTALL_TYPE
  INSTALL_TYPE="${INSTALL_TYPE:-1}"

  echo ""
  read -rp "Port panel [default: 22223]: " INPUT_PORT
  AIDI_PORT="${INPUT_PORT:-22223}"

  echo ""
  read -rp "Username admin panel: " AIDI_rezzaidr
  AIDI_rezzaidr="${AIDI_rezzaidr:-admin}"

  echo ""
  read -rsp "Password admin panel (min 8 karakter): " AIDI_PASSWORD
  echo ""
  if [[ ${#AIDI_PASSWORD} -lt 8 ]]; then
    err "Password minimal 8 karakter"
  fi

  echo ""
  read -rp "Timezone [default: Asia/Jakarta]: " AIDI_TZ
  AIDI_TZ="${AIDI_TZ:-Asia/Jakarta}"

  echo ""
  echo -e "${YELLOW}Ringkasan Instalasi:${NC}"
  echo "  • Tipe    : $([ "$INSTALL_TYPE" = "2" ] && echo "Panel + Webinoly (LEMP)" || echo "Panel saja")"
  echo "  • Port    : $AIDI_PORT"
  echo "  • Username: $AIDI_rezzaidr"
  echo "  • Timezone: $AIDI_TZ"
  echo "  • Dir     : $AIDI_DIR"
  echo ""
  read -rp "Lanjutkan? [y/N]: " CONFIRM
  [[ "${CONFIRM,,}" != "y" ]] && err "Instalasi dibatalkan"
}

# ── Install Webinoly (opsional) ───────────────────────────────
install_webinoly() {
  if [[ "$INSTALL_TYPE" != "2" ]]; then return; fi

  head "Menginstall Webinoly (LEMP Stack)"
  info "Mengupdate sistem..."
  apt-get update -qq && apt-get upgrade -y -qq

  info "Menginstall Webinoly..."
  wget -qO weby qrok.es/wy
  bash weby
  rm -f weby
  ok "Webinoly terinstall"
}

# ── Install dependencies ──────────────────────────────────────
install_dependencies() {
  head "Menginstall Dependencies"

  apt-get update -qq

  local packages=("php8.3-fpm" "php8.3-cli" "php8.3-curl" "php8.3-mbstring"
                  "php8.3-xml" "php8.3-zip" "curl" "wget" "git" "unzip")

  for pkg in "${packages[@]}"; do
    if dpkg -l "$pkg" &>/dev/null; then
      info "$pkg sudah ada"
    else
      info "Menginstall $pkg..."
      apt-get install -y -qq "$pkg"
      ok "$pkg terinstall"
    fi
  done
}

# ── Setup direktori ───────────────────────────────────────────
setup_directories() {
  head "Menyiapkan Direktori"

  mkdir -p "$AIDI_DIR"/{logs,config,cache}
  mkdir -p "$AIDI_PANEL_DIR"/{api,assets,logs}
  mkdir -p /var/log/aidipanel

  chown -R www-data:www-data "$AIDI_PANEL_DIR"
  chmod -R 750 "$AIDI_DIR"
  chmod 700 "$AIDI_DIR/logs"

  ok "Direktori disiapkan"
}

# ── Download/copy panel files ─────────────────────────────────
AIDI_REPO="https://raw.githubusercontent.com/rezzaidr/aidipanelforwebinoly/main"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

install_panel_files() {
  head "Menginstall File Panel"

  # Prioritas 1: file ada di direktori lokal (pakai ZIP yang sudah didownload)
  if [[ -f "$SCRIPT_DIR/aidipanel.html" ]]; then
    info "Menggunakan file panel dari direktori lokal..."
    cp "$SCRIPT_DIR/aidipanel.html" "$AIDI_PANEL_DIR/index.html"
    ok "Frontend (aidipanel.html) disalin"

    if [[ -d "$SCRIPT_DIR/backend" ]]; then
      cp "$SCRIPT_DIR/backend/api.php"   "$AIDI_PANEL_DIR/api/"
      cp "$SCRIPT_DIR/backend/login.php" "$AIDI_PANEL_DIR/"
      ok "Backend PHP disalin"
    else
      warn "Folder backend/ tidak ada — download dari internet..."
      _download_files
    fi

  # Prioritas 2: download dari internet
  else
    info "File lokal tidak ditemukan — download dari internet..."
    _download_files
  fi

  # Set permissions
  chown -R www-data:www-data "$AIDI_PANEL_DIR"
  find "$AIDI_PANEL_DIR" -type f -exec chmod 644 {} \;
  find "$AIDI_PANEL_DIR" -type d -exec chmod 755 {} \;

  ok "File panel terinstall"
}

_download_files() {
  local DL_OK=false

  # Coba wget dulu, fallback ke curl
  _dl() {
    local url="$1" dest="$2"
    if command -v wget &>/dev/null; then
      wget -qO "$dest" "$url" && return 0
    fi
    if command -v curl &>/dev/null; then
      curl -sfLo "$dest" "$url" && return 0
    fi
    return 1
  }

  info "Mendownload aidipanel.html..."
  _dl "$AIDI_REPO/aidipanel.html" "$AIDI_PANEL_DIR/index.html" || err "Gagal download aidipanel.html"
  ok "Frontend didownload"

  info "Mendownload api.php..."
  _dl "$AIDI_REPO/backend/api.php" "$AIDI_PANEL_DIR/api/api.php" || err "Gagal download api.php"
  ok "Backend api.php didownload"

  info "Mendownload login.php..."
  _dl "$AIDI_REPO/backend/login.php" "$AIDI_PANEL_DIR/login.php" || err "Gagal download login.php"
  ok "Login page didownload"
}


# ── Generate config ───────────────────────────────────────────
generate_config() {
  head "Membuat Konfigurasi"

  info "Membuat hash password..."
  local PASS_HASH
  PASS_HASH=$(php -r "echo password_hash('$AIDI_PASSWORD', PASSWORD_BCRYPT, ['cost'=>12]);")

  cat > "$AIDI_PANEL_DIR/api/config.php" << PHPEOF
<?php
// AIDI Panel — Config (auto-generated oleh installer)
// Generated: $(date '+%Y-%m-%d %H:%M:%S')

define('AIDI_rezzaidr',      '${AIDI_rezzaidr}');
define('AIDI_PASSWORD_HASH', '${PASS_HASH}');
define('AIDI_PORT',           ${AIDI_PORT});
define('AIDI_TIMEZONE',      '${AIDI_TZ}');
define('WEBINOLY_PATH',      '/opt/webinoly');
define('NGINX_SITES',        '/etc/nginx/sites-available');
define('WEB_ROOT',           '/var/www');
define('ALLOWED_IPS',        []);
define('SESSION_LIFETIME',    3600);

date_default_timezone_set(AIDI_TIMEZONE);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
PHPEOF

  chmod 640 "$AIDI_PANEL_DIR/api/config.php"
  chown root:www-data "$AIDI_PANEL_DIR/api/config.php"

  ok "Config dibuat"
}

# ── Setup Nginx virtual host ──────────────────────────────────
setup_nginx() {
  head "Mengkonfigurasi Nginx"

  local CONF_FILE="/etc/nginx/sites-available/aidipanel"

  cat > "$CONF_FILE" << NGINXEOF
# AIDI Panel — Nginx Config
# Port: ${AIDI_PORT}
# Auto-generated oleh installer.sh

server {
    listen ${AIDI_PORT} ssl http2;
    listen [::]:${AIDI_PORT} ssl http2;

    server_name _;

    root ${AIDI_PANEL_DIR};
    index index.html;

    # SSL — Self-signed (ganti dengan Let's Encrypt jika ada domain)
    ssl_certificate     /etc/ssl/aidipanel/cert.pem;
    ssl_certificate_key /etc/ssl/aidipanel/key.pem;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_ciphers         ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_session_cache   shared:AIDI:10m;
    ssl_session_timeout 1d;

    # Security headers
    add_header X-Frame-Options         DENY        always;
    add_header X-Content-Type-Options  nosniff     always;
    add_header X-XSS-Protection        "1; mode=block" always;
    add_header Referrer-Policy         "no-referrer" always;
    add_header Content-Security-Policy "default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; img-src 'self' data:;" always;

    # Panel frontend
    location / {
        try_files \$uri \$uri/ =404;
    }

    # API backend
    location /api/ {
        # Hanya izinkan dari IP yang sama / loopback
        # Uncomment baris berikut untuk membatasi akses:
        # allow 127.0.0.1;
        # deny all;

        try_files \$uri \$uri/ /api/index.php?\$query_string;
    }

    location ~ /api/.*\.php$ {
        fastcgi_pass   unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include        fastcgi_params;

        # Batasi method
        limit_except POST GET { deny all; }
    }

    # Larang akses ke file sensitif
    location ~ /\.(env|git|sql|log|conf|bak)$ {
        deny all;
        return 404;
    }
    location ~ /api/config\.php$ {
        deny all;
        return 404;
    }
    location ~ /api/logs/ {
        deny all;
        return 404;
    }

    # Logging
    access_log /var/log/aidipanel/nginx-access.log;
    error_log  /var/log/aidipanel/nginx-error.log;
}
NGINXEOF

  ln -sf "$CONF_FILE" /etc/nginx/sites-enabled/aidipanel
  ok "Nginx virtual host dibuat"
}

# ── Generate SSL self-signed ──────────────────────────────────
setup_ssl() {
  head "Membuat SSL Certificate (Self-Signed)"

  mkdir -p /etc/ssl/aidipanel

  local SERVER_IP
  SERVER_IP=$(curl -s --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')

  openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
    -keyout /etc/ssl/aidipanel/key.pem \
    -out    /etc/ssl/aidipanel/cert.pem \
    -subj   "/C=ID/ST=Indonesia/L=Jakarta/O=AIDI Panel/OU=Server/CN=${SERVER_IP}" \
    -addext "subjectAltName=IP:${SERVER_IP}" \
    2>/dev/null

  chmod 600 /etc/ssl/aidipanel/key.pem
  chmod 644 /etc/ssl/aidipanel/cert.pem

  ok "SSL self-signed dibuat (valid 10 tahun)"
  info "IP server: $SERVER_IP"
}

# ── Setup sudoers ─────────────────────────────────────────────
setup_sudoers() {
  head "Mengkonfigurasi Sudoers"

  cat > /etc/sudoers.d/aidipanel << 'SUDOEOF'
# AIDI Panel sudoers — auto-generated
# Izinkan www-data jalankan Webinoly commands tanpa password

www-data ALL=(ALL) NOPASSWD: /usr/local/bin/site
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/stack
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/webinoly
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/httpauth
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/log
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload nginx
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart nginx
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload php*-fpm
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart php*-fpm
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload mariadb
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart mariadb
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl start redis
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl stop redis
www-data ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart redis
www-data ALL=(ALL) NOPASSWD: /usr/sbin/nginx -t
www-data ALL=(ALL) NOPASSWD: /usr/bin/redis-cli FLUSHALL
SUDOEOF

  chmod 440 /etc/sudoers.d/aidipanel

  # Validasi sudoers
  if visudo -c -f /etc/sudoers.d/aidipanel &>/dev/null; then
    ok "Sudoers dikonfigurasi"
  else
    rm -f /etc/sudoers.d/aidipanel
    err "Sudoers validation gagal"
  fi
}

# ── Setup firewall (UFW) ──────────────────────────────────────
setup_firewall() {
  head "Mengkonfigurasi Firewall"

  if ! command -v ufw &>/dev/null; then
    info "Menginstall UFW..."
    apt-get install -y -qq ufw
  fi

  # Pastikan SSH tidak di-block
  ufw allow 22/tcp comment 'SSH' 2>/dev/null || true

  # Port panel
  ufw allow "${AIDI_PORT}/tcp" comment 'AIDI Panel' 2>/dev/null || true

  ok "Port $AIDI_PORT dibuka di UFW"
  info "Jalankan 'ufw enable' jika UFW belum aktif"
}

# ── Restart services ──────────────────────────────────────────
restart_services() {
  head "Merestart Services"

  nginx -t && systemctl reload nginx && ok "Nginx reloaded"
  systemctl reload php8.3-fpm       && ok "PHP-FPM reloaded"
}

# ── Tampilkan info ─────────────────────────────────────────────
show_result() {
  local SERVER_IP
  SERVER_IP=$(curl -s --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')

  echo ""
  echo -e "${GREEN}════════════════════════════════════════════${NC}"
  echo -e "${BOLD}${GREEN}  AIDI Panel berhasil diinstall! 🎉${NC}"
  echo -e "${GREEN}════════════════════════════════════════════${NC}"
  echo ""
  echo -e "${BOLD}  Akses Panel:${NC}"
  echo -e "  🔗  ${CYAN}https://${SERVER_IP}:${AIDI_PORT}${NC}"
  echo ""
  echo -e "${BOLD}  Kredensial:${NC}"
  echo -e "  👤  Username : ${CYAN}${AIDI_rezzaidr}${NC}"
  echo -e "  🔑  Password : ${YELLOW}(yang Anda masukkan tadi)${NC}"
  echo ""
  echo -e "${BOLD}  Info Penting:${NC}"
  echo -e "  ⚠️   SSL self-signed — browser akan warning, klik 'Advanced' → 'Proceed'"
  echo -e "  📁  Panel dir  : ${AIDI_PANEL_DIR}"
  echo -e "  📝  Log file   : /var/log/aidipanel/"
  echo -e "  ⚙️   Config     : ${AIDI_PANEL_DIR}/api/config.php"
  echo ""
  echo -e "${BOLD}  Untuk update panel:${NC}"
  echo -e "  ${CYAN}sudo bash /opt/aidipanel/update.sh${NC}"
  echo ""
  echo -e "${BOLD}  Untuk uninstall:${NC}"
  echo -e "  ${CYAN}sudo bash /opt/aidipanel/uninstall.sh${NC}"
  echo ""
  echo -e "${GREEN}════════════════════════════════════════════${NC}"

  # Simpan info ke file
  cat > /opt/aidipanel/install-info.txt << INFOEOF
AIDI Panel Installation Info
Installed: $(date '+%Y-%m-%d %H:%M:%S')
Version: ${AIDI_VERSION}
URL: https://${SERVER_IP}:${AIDI_PORT}
Username: ${AIDI_rezzaidr}
Panel Dir: ${AIDI_PANEL_DIR}
INFOEOF
  chmod 640 /opt/aidipanel/install-info.txt
}

# ── Uninstall helper ──────────────────────────────────────────
create_uninstall_script() {
  cat > /opt/aidipanel/uninstall.sh << 'UNEOF'
#!/usr/bin/env bash
# AIDI Panel — Uninstaller
echo "Menghapus AIDI Panel..."
rm -f /etc/nginx/sites-enabled/aidipanel
rm -f /etc/nginx/sites-available/aidipanel
rm -f /etc/sudoers.d/aidipanel
rm -rf /opt/aidipanel
rm -rf /var/www/aidipanel
rm -rf /etc/ssl/aidipanel
rm -rf /var/log/aidipanel
ufw delete allow 22223/tcp 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
echo "AIDI Panel berhasil dihapus. Webinoly dan sites tetap berjalan normal."
UNEOF
  chmod +x /opt/aidipanel/uninstall.sh
}

# ── Update helper ─────────────────────────────────────────────
create_update_script() {
  cat > /opt/aidipanel/update.sh << 'UPEOF'
#!/usr/bin/env bash
# AIDI Panel — Updater
echo "Mengupdate AIDI Panel..."
# Backup config
cp /var/www/aidipanel/api/config.php /tmp/aidipanel-config.bak
# Download & install versi baru
wget -qO /tmp/aidipanel-update.sh https://raw.githubusercontent.com/rezzaidr/aidipanelforwebinoly/main/update.sh
bash /tmp/aidipanel-update.sh
# Restore config
cp /tmp/aidipanel-config.bak /var/www/aidipanel/api/config.php
echo "Update selesai!"
UPEOF
  chmod +x /opt/aidipanel/update.sh
}

# ── Logging ───────────────────────────────────────────────────
setup_logging() {
  mkdir -p "$(dirname "$AIDI_LOG")"
  exec > >(tee -a "$AIDI_LOG") 2>&1
  info "Log disimpan di: $AIDI_LOG"
}

# ── MAIN ──────────────────────────────────────────────────────
main() {
  show_banner
  setup_logging
  check_requirements
  choose_options
  install_webinoly
  install_dependencies
  setup_directories
  install_panel_files
  generate_config
  setup_ssl
  setup_nginx
  setup_sudoers
  setup_firewall
  restart_services
  create_uninstall_script
  create_update_script
  show_result
}

main "$@"
