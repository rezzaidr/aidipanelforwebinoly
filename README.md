## Mengapa AIDI Panel for Webinoly?

Webinoly adalah tool server yang sangat powerful, tapi semuanya via command line. AIDI Panel memberikan antarmuka visual di atasnya — tanpa menggantikan Webinoly, tanpa menginstall stack baru.

| | AIDI Panel | CloudPanel | Plesk |
|---|---|---|---|
| **Berbasis Webinoly** | ✅ | ❌ | ❌ |
| **FastCGI Cache** | ✅ | ⚠️ | ❌ |
| **Redis Object Cache** | ✅ | ⚠️ | ⚠️ |
| **Harga** | Gratis | Gratis/Berbayar | Berbayar |
| **Install di atas stack existing** | ✅ | ❌ | ❌ |
| **Open Source** | ✅ | ❌ | ❌ |

---

## Fitur

### 🌐 Sites Manager
- Buat, hapus, aktifkan/nonaktifkan site
- Tipe: WordPress, PHP, HTML statis, Reverse Proxy
- Info lengkap per site (PHP version, database, ukuran)

### ⚡ FastCGI Cache & Redis
- Toggle cache per site atau global
- Clear cache dengan satu klik
- Konfigurasi skip URL, cookie, query string

### 🔒 SSL Manager
- Aktifkan/nonaktifkan/renew Let's Encrypt per site
- Status dan tanggal expiry langsung terlihat

### 🗄️ Database
- Info database per site
- Import database via panel
- Remote MySQL access toggle

### 🛡️ Keamanan
- HTTP Auth user management
- Block/unblock IP address
- SFTP access management
- Security headers konfigurasi

### 📊 Monitoring
- Real-time log viewer (Nginx, PHP, MySQL)
- Resource usage (CPU, RAM, Disk)
- Service status (Nginx, PHP-FPM, MariaDB, Redis)

### 🔧 Advanced
- Redirect Manager (301/302)
- WordPress Multisite support
- Cron Jobs manager
- PHP version manager
- Firewall (UFW) guide

### 🌙 UI
- Dark mode / Light mode
- Demo mode (preview tanpa server)
- Output modal untuk setiap command
- Notifikasi real-time

---

## Instalasi Cepat

### Syarat

- Ubuntu Server **22.04 LTS** atau **24.04 LTS**
- [Webinoly](https://webinoly.com) sudah terinstall
- PHP 8.0+
- Akses root/sudo

> Belum punya Webinoly? Pilih opsi install otomatis di installer.

### Install

```bash
wget -qO install.sh https://raw.githubusercontent.com/rezzaidr/aidipanelforwebinoly/main/install.sh
sudo bash install.sh
```

Installer akan menanyakan:
- Port panel (default: **22223**)
- Username & password admin
- Timezone

Setelah selesai, akses panel di:
```
https://IP_SERVER:22223
```

> **Note:** Browser akan memberi peringatan SSL self-signed — klik *Advanced* → *Proceed*. Ini normal untuk instalasi pertama.

### Install Manual

Jika ingin install secara manual atau dari source:

```bash
# 1. Clone repo
git clone https://github.com/rezzaidr/aidipanelforwebinoly.git
cd aidipanel

# 2. Jalankan installer
sudo bash install.sh
```

---

## Demo

Ingin mencoba tampilan panel tanpa server?

1. Download file `aidipanel.html` dari [Releases](https://github.com/rezzaidr/aidipanelforwebinoly/releases)
2. Buka di browser
3. Panel berjalan dalam **Demo Mode** — semua fitur bisa diklik, data simulasi

Klik badge **DEMO** di topbar → Login → badge berubah jadi **LIVE** (terhubung ke server nyata).

---

## Screenshot

> *Screenshot akan ditambahkan setelah versi pertama dirilis*

---

## Struktur Repo

```
aidipanel/
├── aidipanel.html          # Frontend panel (single file)
├── install.sh              # Installer otomatis
├── backend/
│   ├── api.php             # Backend API PHP
│   ├── login.php           # Halaman login
│   ├── config.php          # Template konfigurasi
│   ├── nginx-aidipanel.conf # Nginx virtual host
│   └── aidipanel-sudoers   # Sudoers rules
├── docs/
│   ├── README.md           # Panduan developer
│   └── DEVELOPER.md        # Cara kontribusi teknis
└── .github/
    ├── ISSUE_TEMPLATE/     # Template issue
    └── workflows/          # GitHub Actions
```

---

## Update

```bash
sudo bash /opt/aidipanel/update.sh
```

---

## Uninstall

```bash
sudo bash /opt/aidipanel/uninstall.sh
```

Perintah ini **hanya** menghapus AIDI Panel. Webinoly, Nginx, PHP, MariaDB, dan semua site tetap berjalan normal.

---

## Kontribusi

Kontribusi sangat disambut! AIDI Panel masih dalam pengembangan awal dan butuh banyak bantuan.

### Yang Dibutuhkan

- 🐛 **Bug reports** — Temukan bug? [Buka issue](https://github.com/rezzaidr/aidipanelforwebinoly/issues/new?template=bug_report.md)
- 💡 **Feature requests** — [Usulkan fitur](https://github.com/rezzaidr/aidipanelforwebinoly/issues/new?template=feature_request.md)
- 🔧 **Pull requests** — Perbaikan kode, UI, dokumentasi
- 🌐 **Terjemahan** — Panel saat ini dalam Bahasa Indonesia, English menyusul
- 📖 **Dokumentasi** — Perbaikan README, panduan, tutorial

### Cara Kontribusi

```bash
# 1. Fork repo ini
# 2. Clone fork Anda
git clone https://github.com/rezzaidr/aidipanelforwebinoly.git

# 3. Buat branch baru
git checkout -b feat/nama-fitur

# 4. Commit perubahan
git commit -m "feat: tambah fitur X"

# 5. Push dan buat Pull Request
git push origin feat/nama-fitur
```

Baca [CONTRIBUTING.md](CONTRIBUTING.md) untuk panduan lengkap.

---

## Roadmap

### v1.1 (saat ini)
- [x] Frontend panel 20 halaman
- [x] Backend PHP API
- [x] Demo mode & Live mode
- [x] Installer otomatis
- [x] Fetch ke api.php (CSRF, session, rate limit)

### v1.2 (berikutnya)
- [ ] WebSocket untuk live log streaming
- [ ] Tampilan bahasa Inggris
- [ ] Backup ke S3 terintegrasi
- [ ] SMTP wizard
- [ ] Dashboard resource monitoring real-time

### v2.0 (rencana)
- [ ] Multi-server management
- [ ] Plugin system
- [ ] API publik
- [ ] Docker support

---

## Lisensi

MIT License — bebas digunakan, dimodifikasi, dan didistribusikan.  
Lihat [LICENSE](LICENSE) untuk detail.

---

## Kredit

- Dibangun di atas [Webinoly](https://webinoly.com) oleh QROkes
- UI terinspirasi dari [Claude.ai](https://claude.ai)
- Icons oleh [Tabler Icons](https://tabler-icons.io)
- Font oleh [Vercel Geist](https://vercel.com/font)

---

<div align="center">

Dibuat dengan ❤️ untuk komunitas Webinoly Indonesia

**[⭐ Star repo ini](https://github.com/rezzaidr/aidipanelforwebinoly)** jika AIDI Panel bermanfaat untuk Anda!

</div>
