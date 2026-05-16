<?php
/**
 * AIDI Panel — Konfigurasi
 * File ini di-generate otomatis oleh installer.sh
 * JANGAN edit secara manual kecuali Anda tahu apa yang dilakukan.
 */

// Kredensial admin panel
define('AIDI_rezzaidr',      'admin');
define('AIDI_PASSWORD_HASH', ''); // Di-isi oleh installer (bcrypt hash)

// Port panel
define('AIDI_PORT', 22223);

// Timezone server
define('AIDI_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set(AIDI_TIMEZONE);

// Path Webinoly
define('WEBINOLY_PATH', '/opt/webinoly');
define('NGINX_SITES',   '/etc/nginx/sites-available');
define('WEB_ROOT',      '/var/www');

// Keamanan
define('ALLOWED_IPS', []); // Kosong = semua IP boleh akses (batasi jika perlu)
define('SESSION_LIFETIME', 3600); // 1 jam

ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
