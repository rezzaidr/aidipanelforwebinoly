# Panduan Developer — AIDI Panel

Dokumen ini menjelaskan arsitektur teknis secara mendalam untuk developer yang ingin memahami atau berkontribusi ke AIDI Panel.

---

## Arsitektur Sistem

```
┌─────────────────────────────────────────────────────┐
│                    BROWSER                           │
│                                                      │
│  aidipanel.html                                      │
│  ┌──────────────┐  ┌──────────────────────────────┐ │
│  │   Sidebar    │  │         Content Area          │ │
│  │   Nav Items  │  │   P.dashboard()               │ │
│  │   (data-p)   │  │   P.sites()                   │ │
│  └──────────────┘  │   P.cache() ... (20 pages)    │ │
│                    └──────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────┐ │
│  │          API Layer (apiFetch)                   │ │
│  │  demoMode=true → apiSimulate() → fake response  │ │
│  │  demoMode=false → fetch('/api/api.php') → real  │ │
│  └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
                          │ HTTPS POST
                          ▼
┌─────────────────────────────────────────────────────┐
│              SERVER (Ubuntu + Webinoly)              │
│                                                      │
│  Nginx :22223                                        │
│  └── /var/www/aidipanel/                            │
│      ├── index.html   (frontend)                    │
│      ├── login.php    (halaman login)               │
│      └── api/                                       │
│          ├── api.php  (backend API)                 │
│          └── config.php (credentials)               │
│                                                      │
│  api.php                                             │
│  ├── Auth: session + CSRF check                     │
│  ├── Rate limit: 30 req/min per IP                  │
│  ├── Router: match($action) → actionXxx()           │
│  ├── Sanitize input                                  │
│  └── runWebinoly($cmd) → proc_open → stdout/stderr  │
│                          │                           │
│                          ▼                           │
│  Webinoly Commands                                   │
│  sudo site, sudo stack, sudo webinoly, httpauth      │
│                          │                           │
│                          ▼                           │
│  Nginx + PHP-FPM + MariaDB + Redis                  │
└─────────────────────────────────────────────────────┘
```

---

## Frontend — aidipanel.html

File tunggal ~120KB berisi semua HTML, CSS, JavaScript.

### State Global

```javascript
// Data simulasi (dipakai saat demo mode)
const DB = {
  sites: [...],         // Array site objects
  httpUsers: [...],     // HTTP Auth users
  blockedIPs: [...],    // IP yang diblokir
  cacheState: {},       // { domain: boolean }
  serverInfo: null,     // Data dari server_info action
};

// API state
const API = {
  url:       '/api/api.php',
  csrfToken: null,      // Diisi setelah login
  demoMode:  true,      // true = simulasi, false = server nyata
  loading:   false,
};

// Navigation state
let curPage = 'dashboard';
const P  = {};  // Page render functions: P.dashboard = () => `<html>`
const AR = {};  // After-render hooks: AR.create = () => { focus input }
```

### Flow Navigasi

```javascript
// User klik nav item
go('sites')
  → curPage = 'sites'
  → update nav active state
  → document.getElementById('content').innerHTML = P.sites()
  → if (AR.sites) AR.sites()  // after-render hook
```

### Flow API Call

```javascript
// Tombol diklik → action function → apiFetch → response → update UI

// Contoh: user klik "Hapus Site"
confirmDel('example.com')         // tampilkan modal konfirmasi
  → user klik "Ya, Hapus"
  → doDel('example.com')          // async function
  → apiFetch('site_delete', {domain: 'example.com'})
  → demoMode ? apiSimulate() : fetch('/api/api.php', POST)
  → result = { success: true, output: '...' }
  → update DB.sites (hapus dari array)
  → toast('Site example.com dihapus ✓', 'ok')
  → showOutputModal(...)           // tampilkan output server
  → go('sites')                   // refresh halaman
```

### Menambah Halaman Baru

```javascript
// 1. Nav item di HTML sidebar:
`<div class="nav-item" data-p="mypage">
  <i class="ti ti-icon"></i>Nama Halaman
</div>`

// 2. Page title:
const pageTitles = {
  ...,
  mypage: 'Nama Halaman',
};

// 3. Render function:
P.mypage = () => `
<div class="card">
  <div class="card-hd">
    <div class="card-title"><i class="ti ti-icon"></i>Judul</div>
    <button class="btn btn-sm btn-primary" onclick="doSomething()">
      <i class="ti ti-plus"></i>Aksi
    </button>
  </div>
  <div class="fg">
    <label class="flabel">Input</label>
    <input class="finput" id="my-input" placeholder="...">
  </div>
  <button class="btn btn-primary w100" onclick="doSomething()">Submit</button>
</div>`;

// 4. After-render (opsional):
AR.mypage = () => {
  document.getElementById('my-input')?.focus();
};

// 5. Action function:
async function doSomething() {
  const val = document.getElementById('my-input')?.value?.trim();
  if (!val) { toast('Isi input!', 'err'); return; }

  const result = await apiFetch('my_action', { value: val });
  if (result.success !== false) {
    toast('Berhasil ✓', 'ok');
    go('mypage'); // refresh
  } else {
    toast(result.error || 'Gagal', 'err', 4000);
  }
}
```

### CSS Design System

```css
/* Variables utama */
--bg, --bg-panel, --bg-subtle   /* Backgrounds */
--border, --border-md, --border-dk  /* Borders */
--text, --text-2, --text-3      /* Text colors */
--accent, --accent-lt, --accent-md  /* Blue accent */
--ok, --ok-lt, --ok-bd          /* Green (success) */
--warn, --warn-lt, --warn-bd    /* Amber (warning) */
--err, --err-lt, --err-bd       /* Red (error) */
--info, --info-lt, --info-bd    /* Cyan (info) */
--font, --mono                  /* Geist + Geist Mono */

/* Komponen siap pakai */
.card          /* Container putih dengan border */
.card-hd       /* Header card dengan flexbox */
.stat          /* Stat card dengan accent bar kiri */
.badge         /* Pill badge (b-ok, b-err, b-warn, dll) */
.btn           /* Button dasar */
.btn-primary   /* Button hitam */
.btn-danger    /* Button merah */
.btn-success   /* Button hijau */
.btn-sm        /* Button kecil */
.btn-icon      /* Button kotak 28x28 */
.finput        /* Input field */
.fselect       /* Select dropdown */
.ftoggle       /* Toggle switch */
.terminal      /* Terminal dark block */
.notice        /* Alert box (n-info, n-warn, n-err, n-ok) */
.g2, .g3, .g4  /* Grid 2/3/4 kolom */
.fx .fxc .fxb  /* Flexbox utilities */
.mono .t2 .t3  /* Typography utilities */
```

---

## Backend — api.php

### Request/Response Format

```
POST /api/api.php
Content-Type: application/x-www-form-urlencoded

action=site_create&domain=example.com&type=wp&cache=true&_csrf=TOKEN
```

```json
{
  "success": true,
  "output": "WordPress site example.com created successfully.\nSSL installed.",
  "domain": "example.com"
}
```

### Menambah Action Baru

```php
// 1. Di match() router (urut abjad):
'my_action' => actionMyAction(),

// 2. Fungsi action:
function actionMyAction(): array
{
    // Ambil dan sanitasi input
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $option = sanitizeEnum($_POST['option'] ?? '', ['a', 'b', 'c']);

    if (!$domain) return ['error' => 'Domain tidak valid'];
    if (!$option) return ['error' => 'Option tidak valid'];

    // Jalankan command via runWebinoly (bukan shell_exec langsung!)
    $result = runWebinoly("sudo site $domain -my-option=$option");

    // Log aktivitas
    aidiLog('MY_ACTION', "domain=$domain option=$option");

    return [
        'success' => $result['success'],
        'output'  => $result['output'],
        'domain'  => $domain,
    ];
}

// 3. Jika perlu dieksekusi via run_command juga, tambah ke whitelist:
function getAllowedCommands(): array
{
    return [
        // ... existing patterns ...
        '/^sudo site [a-z0-9\.\-]+ -my-option=[abc]$/',
    ];
}
```

### Sanitization Functions

```php
sanitizeDomain($input)    // "example.com" → valid | "" → invalid
sanitizeEnum($input, $allowed)  // hanya nilai dari $allowed
sanitizeUsername($input)  // [a-z0-9_-]{3,32}
sanitizeIP($input)        // IPv4 atau CIDR
sanitizeProxyTarget($input)  // host:port
```

### runWebinoly() — Cara Kerja

```php
function runWebinoly(string $cmd): array
{
    // 1. Cek karakter berbahaya
    if (preg_match('/[;&|`$\(\)<>]/', $cmd)) {
        return ['success' => false, 'output' => 'Karakter tidak aman'];
    }

    // 2. Buka process
    $process = proc_open(
        "bash -c " . escapeshellarg($cmd),
        [0 => stdin_pipe, 1 => stdout_pipe, 2 => stderr_pipe],
        $pipes, '/tmp', $env
    );

    // 3. Baca output
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'success'   => $exitCode === 0,
        'output'    => trim($stdout . "\n" . $stderr),
        'exit_code' => $exitCode,
    ];
}
```

---

## Nginx Config — Port 22223

File: `/etc/nginx/sites-available/aidipanel`

Hal penting:
- `limit_req_zone` — rate limiting 5 req/menit untuk login, 30 req/menit untuk API
- `add_header Content-Security-Policy` — CSP ketat
- `location ~ /api/config\.php$ { deny all; }` — blokir akses config
- `fastcgi_pass unix:/var/run/php/php8.3-fpm.sock` — via Unix socket

---

## Sudoers — Izin www-data

File: `/etc/sudoers.d/aidipanel`

```
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/site
www-data ALL=(ALL) NOPASSWD: /usr/local/bin/webinoly
...
```

**Prinsip least privilege:** hanya command spesifik yang diizinkan, bukan `ALL`.

Setiap command yang ditambahkan ke sudoers HARUS juga ada di regex whitelist di `getAllowedCommands()`.

---

## Testing

### Test Manual di VPS

```bash
# 1. Upload file
scp backend/api.php user@vps:/var/www/aidipanel/api/

# 2. Test endpoint login
curl -k -X POST https://IP:22223/api/api.php \
  -d "action=status" | python3 -m json.tool

# 3. Test dengan session (2 request)
# Step 1: login
COOKIE_JAR="/tmp/aidi_test_cookies"
LOGIN=$(curl -k -s -c "$COOKIE_JAR" -X POST https://IP:22223/api/api.php \
  -d "action=login&username=admin&password=pass")
echo $LOGIN | python3 -m json.tool

# Step 2: ambil CSRF token dan gunakan
CSRF=$(echo $LOGIN | python3 -c "import sys,json; print(json.load(sys.stdin)['csrf_token'])")
curl -k -s -b "$COOKIE_JAR" -X POST https://IP:22223/api/api.php \
  -d "action=site_list&_csrf=$CSRF" | python3 -m json.tool
```

### Test Frontend (Demo Mode)

```bash
# Buka di browser langsung
python3 -m http.server 8080
# Buka http://localhost:8080/aidipanel.html
```

---

## Roadmap Teknis

### v1.2 — WebSocket Live Log

```javascript
// Frontend: ganti polling dengan WebSocket
const ws = new WebSocket('wss://IP:22223/ws/logs');
ws.onmessage = e => appendLogLine(JSON.parse(e.data));

// Backend: butuh WebSocket server
// Opsi A: Ratchet (PHP WebSocket library)
// Opsi B: Node.js proxy kecil di samping PHP
// Opsi C: Server-Sent Events (SSE) — lebih mudah, satu arah
```

### v1.2 — Real-time Resource Monitoring

```javascript
// Poll server_info tiap 5 detik saat di halaman dashboard
AR.dashboard = async () => {
  const interval = setInterval(async () => {
    if (curPage !== 'dashboard') { clearInterval(interval); return; }
    const info = await apiFetch('server_info');
    updateResourceBars(info);
  }, 5000);
};
```

### v2.0 — Multi-Server

```javascript
// State: array server, bukan satu server
const SERVERS = [
  { id: 'vps1', name: 'Production', ip: '1.2.3.4', apiUrl: 'https://1.2.3.4:22223/api/api.php' },
  { id: 'vps2', name: 'Staging',    ip: '5.6.7.8', apiUrl: 'https://5.6.7.8:22223/api/api.php' },
];
let activeServer = SERVERS[0];

// apiFetch menggunakan activeServer.apiUrl
```

---

## Struktur File Final

```
aidipanel/
├── aidipanel.html           # Frontend (single file, ~120KB)
├── install.sh               # Installer
├── CHANGELOG.md
├── CONTRIBUTING.md
├── DEVELOPER.md             # File ini
├── LICENSE
├── README.md
├── SECURITY.md
├── .gitignore
├── backend/
│   ├── api.php              # Backend API (~700 baris)
│   ├── config.php           # Template config
│   ├── login.php            # Login page (~112 baris)
│   ├── nginx-aidipanel.conf # Nginx config
│   └── aidipanel-sudoers    # Sudoers rules
├── docs/
│   └── PANDUAN-PUBLISH-GITHUB.md
└── .github/
    ├── ISSUE_TEMPLATE/
    │   ├── bug_report.md
    │   └── feature_request.md
    └── workflows/
        └── validate.yml     # GitHub Actions CI
```
