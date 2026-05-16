# Security Policy — AIDI Panel

## Versi yang Didukung

| Versi | Status Keamanan |
|-------|----------------|
| 1.1.x | ✅ Aktif didukung |
| 1.0.x | ⚠️ Hanya critical fix |
| < 1.0 | ❌ Tidak didukung |

---

## Melaporkan Kerentanan Keamanan

**JANGAN buka issue publik untuk laporan keamanan.**

Jika Anda menemukan kerentanan keamanan di AIDI Panel, laporkan secara privat:

### Cara Melaporkan

1. **GitHub Private Security Advisory** (direkomendasikan):
   - Buka repo → tab **Security** → **Report a vulnerability**
   - Isi formulir dengan detail kerentanan

2. **Email langsung:**
   - Kirim ke: `imrezzaindra@gmail.com`
   - Subject: `[SECURITY] AIDI Panel - Deskripsi Singkat`
   - Gunakan enkripsi PGP jika memungkinkan

### Informasi yang Dibutuhkan

Sertakan informasi berikut:
- Deskripsi kerentanan
- Langkah untuk mereproduksi
- Dampak potensial
- Versi AIDI Panel yang terpengaruh
- Saran perbaikan (opsional)

---

## Proses Penanganan

1. **Konfirmasi** — Tim akan konfirmasi penerimaan dalam 48 jam
2. **Investigasi** — Verifikasi dan analisis dampak dalam 7 hari
3. **Perbaikan** — Develop dan test fix
4. **Release** — Rilis patch dengan credit ke pelapor
5. **Disclosure** — Pengumuman publik setelah fix tersedia

Timeline target: **14 hari** dari laporan ke patch release untuk isu critical.

---

## Kerentanan yang Paling Relevan

Karena AIDI Panel mengeksekusi perintah di server, area berikut paling kritis:

- **Command injection** — input user yang lolos ke shell
- **Path traversal** — akses file di luar direktori yang diizinkan
- **Authentication bypass** — bypass login atau session
- **CSRF** — request berbahaya dari domain lain
- **Privilege escalation** — akses command di luar sudoers whitelist
- **Log injection** — manipulasi file log

---

## Praktik Keamanan yang Sudah Diimplementasi

- ✅ Input sanitization untuk semua parameter
- ✅ Command whitelist regex ketat
- ✅ Shell injection guard (cek `; & | \` $ ( ) < >`)
- ✅ CSRF token per session
- ✅ Rate limiting (PHP + Nginx)
- ✅ Bcrypt password hash
- ✅ File sensitif diblokir di Nginx
- ✅ Audit log semua action
- ✅ HTTPS wajib
- ✅ Security headers (CSP, HSTS, X-Frame-Options)

---

## Bug Bounty

Saat ini tidak ada program bug bounty formal. Tapi pelapor kerentanan valid akan mendapat:
- Credit di CHANGELOG dan release notes
- Mention di SECURITY.md sebagai contributor keamanan
- Terima kasih yang tulus 🙏

---

## Kontak

- Security issues: buka **Private Security Advisory** di GitHub
- Pertanyaan umum keamanan: buka **Discussion** di GitHub
