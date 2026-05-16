# Changelog

Semua perubahan penting pada AIDI Panel dicatat di sini.

Format berdasarkan [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan project ini menggunakan [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.1.0] — 2026-05-16

### Ditambahkan
- **API Layer lengkap** — fungsi `apiFetch()` sebagai pusat semua komunikasi ke server
- **Demo Mode & Live Mode** — badge DEMO/LIVE di topbar, toggle dengan klik
- **Login Modal** — form login dengan CSRF token protection
- **Session management** — cek sesi aktif saat panel dibuka, auto-logout tiap 5 menit
- **Loading spinner** — progress indicator saat command sedang berjalan
- **Output Modal** — tampilkan hasil command nyata dari server (stdout/stderr)
- **`apiSimulate()`** — simulasi response realistis untuk demo mode (delay 400-700ms)
- **`syncServerData()`** — ambil data sites dari server setelah login
- **`refreshServerInfo()`** — refresh info server (uptime, RAM, disk)
- **`sslAction()`** — dedicated function untuk SSL on/off/renew
- Semua action functions diubah ke `async/await`:
  - `doCreate`, `doDel`, `toggleStatus`
  - `clearCache`, `togCacheSite`
  - `doAddUser`, `doDelUser`
  - `doBlock`, `doUnblock`
  - `enableSSL`, `disableSSL`
- Halaman **Redirect Manager** — kelola redirect 301/302
- Halaman **WP Multisite** — konversi dan kelola WordPress Multisite
- Halaman **Cron Jobs** — tambah/hapus/jalankan cron job
- Halaman **PHP Info** — kelola versi PHP, php.ini, extensions
- Halaman **Firewall** — panduan UFW + port rules Webinoly
- Nav section **Advanced** di sidebar

### Diperbaiki (dari v1.0.1)
- Tool cards onclick tidak berfungsi (BUG-01)
- `confirmUninstall()` tidak bisa dipanggil (BUG-02)
- `switchLogTab()` tidak terdefinisi (BUG-03)
- `install_panel_files()` di-comment out di installer (BUG-06)
- Tidak ada cek versi PHP di api.php (BUG-07)

---

## [1.0.1] — 2026-05-16

### Diperbaiki
- **BUG-01** HIGH — Tool cards onclick `${fn()}` dieksekusi saat render bukan saat klik
- **BUG-02** MEDIUM — `confirmUninstall()` return string bukan function reference
- **BUG-03** MEDIUM — `switchLogTab()` dipanggil tapi tidak didefinisikan
- **BUG-06** HIGH — `install_panel_files()` di-comment out, file tidak disalin
- **BUG-07** LOW — Tidak ada cek versi PHP di `api.php`

---

## [1.0.0] — 2026-05-15

### Rilis Pertama 🎉

#### Frontend (aidipanel.html)
- **15 halaman panel**: Dashboard, Sites Manager, Buat Site Baru, Stack Builder,
  FastCGI Cache, SSL Manager, Database, HTTP Auth, Block IP, SFTP,
  Log Viewer, Backup, SMTP Email, Admin Tools, Pengaturan
- Sidebar navigasi dengan badge counter
- Topbar dengan search, Verify, Update, Site Baru
- Dark mode / Light mode (tersimpan di localStorage)
- Sistem notifikasi dengan bell dropdown
- Modal dialog untuk konfirmasi dan detail
- Toast notifications (ok/err/info/warn)
- Desain terinspirasi Claude UI — warna netral, tipografi bersih

#### Backend (api.php)
- 22 API action endpoints
- Autentikasi session PHP + bcrypt password hash
- CSRF token protection
- Rate limiting (30 req/menit per IP)
- Command whitelist ketat dengan regex
- Input sanitization (`sanitizeDomain`, `sanitizeIP`, `sanitizeEnum`, dll)
- Shell injection guard
- Audit log semua aktivitas
- PHP 8.0+ dengan `match()` expression

#### Installer (install.sh)
- Deteksi OS dan versi Ubuntu
- Cek persyaratan (RAM, disk, Webinoly)
- Pilihan install panel saja atau + Webinoly
- Generate SSL self-signed otomatis
- Setup Nginx virtual host port 22223
- Konfigurasi sudoers untuk www-data
- Script update dan uninstall

#### Keamanan
- HTTPS dengan SSL (self-signed atau Let's Encrypt)
- Security headers (CSP, X-Frame-Options, HSTS, dll)
- Rate limiting di Nginx dan PHP
- File sensitif diblokir (config.php, logs/, .sh)
- Audit log semua action

---

[1.1.0]: https://github.com/rezzaidr/aidipanelforwebinoly/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/rezzaidr/aidipanelforwebinoly/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/rezzaidr/aidipanelforwebinoly/releases/tag/v1.0.0
