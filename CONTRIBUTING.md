# Panduan Kontribusi — AIDI Panel

Terima kasih sudah tertarik berkontribusi! Dokumen ini menjelaskan cara terbaik untuk membantu pengembangan AIDI Panel.

---

## Cara Kontribusi

### 1. Laporkan Bug

Temukan bug? [Buka issue baru](https://github.com/rezzaidr/aidipanelforwebinoly/issues/new?template=bug_report.md) dengan informasi:

- Langkah untuk mereproduksi bug
- Yang diharapkan terjadi vs yang sebenarnya terjadi
- Screenshot jika ada
- OS, versi Ubuntu, versi PHP, versi Webinoly

### 2. Usulkan Fitur

Punya ide fitur? [Buka feature request](https://github.com/rezzaidr/aidipanelforwebinoly/issues/new?template=feature_request.md).

### 3. Pull Request

```bash
# Fork di GitHub, lalu:
git clone https://github.com/rezzaidr/aidipanelforwebinoly.git
cd aidipanel
git checkout -b feat/nama-fitur-anda

# Buat perubahan...

git add .
git commit -m "feat: deskripsi perubahan"
git push origin feat/nama-fitur-anda
# Buka Pull Request di GitHub
```

---

## Standar Kode

### JavaScript (Frontend — aidipanel.html)

- Gunakan `async/await`, bukan callback
- Semua fetch ke server via `apiFetch(action, params)`
- Tambah halaman baru dengan pola `P.namahalaman = () => \`...\``
- Gunakan `esc()` untuk semua output user-generated content (cegah XSS)
- Function names: camelCase, deskriptif

```javascript
// ✅ Benar
async function doSomething(domain) {
  const result = await apiFetch('site_info', { domain });
  if (result.success !== false) {
    toast('Berhasil ✓', 'ok');
  }
}

// ❌ Salah
function doSomething(domain) {
  runCmd(`sudo site ${domain} -info`, 'ok'); // langsung command
}
```

### PHP (Backend — api.php)

- Semua input di-sanitize sebelum dipakai
- Command hanya lewat `runWebinoly()`, tidak pernah `shell_exec` langsung
- Tambah action baru ke `match()` router dan whitelist
- Semua action harus return `['success' => true/false, ...]`

```php
// ✅ Benar — action baru
function actionMyNewFeature(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -my-option");
    aidiLog('MY_FEATURE', "domain=$domain");

    return ['success' => $result['success'], 'output' => $result['output']];
}

// Tambah ke getAllowedCommands() jika perlu run_command:
'/^sudo site [a-z0-9\.\-]+ -my-option$/',
```

### Commit Messages

Format: `type: deskripsi singkat`

| Type | Kapan dipakai |
|------|--------------|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `docs` | Perubahan dokumentasi |
| `style` | Perubahan CSS/UI tanpa logika |
| `refactor` | Refactoring kode |
| `perf` | Peningkatan performa |
| `test` | Menambah atau memperbaiki test |

Contoh:
```
feat: tambah halaman WP multisite domain mapping
fix: SSL toggle tidak reload halaman setelah berhasil
docs: tambah panduan instalasi manual di README
style: perbaiki padding stat cards di mobile
```

---

## Struktur Kode Frontend

```javascript
// ── DATA GLOBAL ──────────────────────
const DB = { sites: [...], httpUsers: [...], ... };

// ── API LAYER ────────────────────────
async function apiFetch(action, params) { ... }  // Satu-satunya cara fetch
function apiSimulate(action, params) { ... }       // Demo mode responses

// ── NAVIGATION ───────────────────────
function go(page) { ... }    // Ganti halaman
const P = {};                // Object berisi semua halaman
const AR = {};               // afterRender hooks per halaman

// ── HALAMAN (template literals) ──────
P.namahalaman = () => `<div class="card">...</div>`;
AR.namahalaman = () => { /* event setelah render */ };

// ── ACTION FUNCTIONS ─────────────────
async function doSomething() { ... }  // Selalu async jika fetch ke API

// ── UTILITIES ────────────────────────
function toast(msg, type, dur) { ... }
function openModal(html) { ... }
function showOutputModal(cmd, output, type) { ... }
```

## Cara Tambah Halaman Baru

```javascript
// 1. Tambah nav item di sidebar HTML:
<div class="nav-item" data-p="mypage"><i class="ti ti-icon"></i>Nama Halaman</div>

// 2. Daftarkan di pageTitles:
const pageTitles = {
  ...,
  mypage: 'Nama Halaman',
};

// 3. Buat fungsi halaman:
P.mypage = () => `
<div class="card">
  <div class="card-hd">
    <div class="card-title"><i class="ti ti-icon"></i>Judul</div>
  </div>
  <!-- konten -->
</div>`;

// 4. (Opsional) Jalankan sesuatu setelah render:
AR.mypage = () => {
  document.getElementById('my-input')?.focus();
};
```

---

## Setup Development

Tidak perlu server untuk development frontend:

```bash
# Buka di browser langsung
open aidipanel.html   # macOS
xdg-open aidipanel.html  # Linux

# Panel berjalan dalam Demo Mode
# Semua fetch ke API disimulasi oleh apiSimulate()
```

Untuk development backend (butuh VPS dengan Webinoly):

```bash
# Upload file ke server
scp backend/api.php user@vps:/var/www/aidipanel/api/

# Tail log untuk debug
ssh user@vps "tail -f /var/log/aidipanel/aidi.log"

# Test endpoint langsung
curl -X POST https://IP:22223/api/api.php \
  -d "action=status" \
  -k  # skip SSL verify untuk self-signed
```

---

## Apa yang Paling Dibutuhkan Sekarang

Prioritas kontribusi saat ini (urut dari yang paling penting):

1. **Testing di VPS nyata** — install, coba semua fitur, laporkan bug
2. **Terjemahan ke Bahasa Inggris** — buat file `lang/en.js`
3. **WebSocket live log** — ganti polling dengan WebSocket
4. **Unit test untuk api.php** — pakai PHPUnit
5. **Screenshot/demo video** — untuk README dan promosi
6. **Perbaikan UI mobile** — panel saat ini desktop-focused

---

## Code of Conduct

- Bersikap sopan dan konstruktif
- Hargai perbedaan pendapat
- Fokus pada masalah, bukan orangnya
- Kontribusi sekecil apapun dihargai

---

Pertanyaan? Buka [Discussion](https://github.com/rezzaidr/aidipanelforwebinoly/discussions) di GitHub.
