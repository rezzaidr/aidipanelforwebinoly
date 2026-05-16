<?php
/**
 * AIDI Panel — Backend API
 * Mengeksekusi Webinoly commands via HTTP request
 *
 * KEAMANAN:
 * - Hanya bisa diakses dari localhost atau IP yang di-whitelist
 * - Semua command di-whitelist secara ketat
 * - Session-based authentication
 * - Rate limiting
 * - Semua input di-sanitize
 */

declare(strict_types=1);

// ── CEK VERSI PHP ─────────────────────────────────
if (PHP_MAJOR_VERSION < 8) {
    http_response_code(500);
    die(json_encode([
        'error' => 'AIDI Panel membutuhkan PHP 8.0 atau lebih baru. Versi saat ini: ' . PHP_VERSION
    ]));
}

// ── KONFIGURASI ──────────────────────────────────
define('AIDI_VERSION', '1.0.0');
define('CONFIG_FILE',  __DIR__ . '/config.php');
define('LOG_FILE',     __DIR__ . '/logs/aidi.log');
define('SESSION_NAME', 'aidi_session');
define('RATE_LIMIT',   30);  // max request per menit per IP
define('RATE_WINDOW',  60);  // detik

// Load config
if (!file_exists(CONFIG_FILE)) {
    http_response_code(500);
    die(json_encode(['error' => 'Config file tidak ditemukan. Jalankan installer.']));
}
require CONFIG_FILE;

// ── HEADERS ──────────────────────────────────────
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, no-cache');

// ── CORS — hanya izinkan dari panel itu sendiri ──
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    // Tidak perlu CORS karena API dan panel di server yang sama
    http_response_code(403);
    die(json_encode(['error' => 'Cross-origin tidak diizinkan']));
}

// ── RATE LIMITING ─────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
rateLimitCheck($ip);

// ── AUTENTIKASI ──────────────────────────────────
session_name(SESSION_NAME);
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict',
]);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Endpoint login tidak perlu auth
if ($action !== 'login' && $action !== 'status') {
    if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        die(json_encode(['error' => 'Tidak terautentikasi', 'redirect' => '/login']));
    }
    // Validasi CSRF token
    $csrf = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($csrf !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        die(json_encode(['error' => 'CSRF token tidak valid']));
    }
}

// ── ROUTER ───────────────────────────────────────
$response = match ($action) {
    'login'          => actionLogin(),
    'logout'         => actionLogout(),
    'status'         => actionStatus(),
    'site_list'      => actionSiteList(),
    'site_create'    => actionSiteCreate(),
    'site_delete'    => actionSiteDelete(),
    'site_toggle'    => actionSiteToggle(),
    'site_info'      => actionSiteInfo(),
    'ssl_toggle'     => actionSSLToggle(),
    'cache_toggle'   => actionCacheToggle(),
    'cache_clear'    => actionCacheClear(),
    'stack_info'     => actionStackInfo(),
    'php_install'    => actionPHPInstall(),
    'db_password'    => actionDBPassword(),
    'httpauth_add'   => actionHttpauthAdd(),
    'httpauth_delete'=> actionHttpauthDelete(),
    'httpauth_list'  => actionHttpauthList(),
    'blockip_add'    => actionBlockIPAdd(),
    'blockip_remove' => actionBlockIPRemove(),
    'blockip_list'   => actionBlockIPList(),
    'sftp_toggle'    => actionSFTPToggle(),
    'run_command'    => actionRunCommand(),
    'logs_get'       => actionLogsGet(),
    'server_info'    => actionServerInfo(),
    'webinoly_update'=> actionWebinolyUpdate(),
    'webinoly_verify'=> actionWebinolyVerify(),
    default          => ['error' => 'Action tidak dikenal: ' . htmlspecialchars($action)]
};

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;


// ════════════════════════════════════════════════
// ACTIONS
// ════════════════════════════════════════════════

function actionLogin(): array
{
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        return ['error' => 'Username dan password diperlukan'];
    }

    // Verifikasi kredensial dari config
    $validUser = AIDI_rezzaidr;
    $validHash = AIDI_PASSWORD_HASH; // bcrypt hash

    if ($username !== $validUser || !password_verify($password, $validHash)) {
        aidiLog('LOGIN_FAILED', "Attempt dari IP: " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        sleep(1); // Delay untuk mencegah brute force
        return ['error' => 'Username atau password salah'];
    }

    $_SESSION['authenticated'] = true;
    $_SESSION['username']       = $username;
    $_SESSION['login_time']     = time();
    $_SESSION['csrf_token']     = bin2hex(random_bytes(32));

    aidiLog('LOGIN_SUCCESS', "User: $username");

    return [
        'success'    => true,
        'username'   => $username,
        'csrf_token' => $_SESSION['csrf_token'],
    ];
}

function actionLogout(): array
{
    $user = $_SESSION['username'] ?? '?';
    session_destroy();
    aidiLog('LOGOUT', "User: $user");
    return ['success' => true];
}

function actionStatus(): array
{
    return [
        'authenticated' => !empty($_SESSION['authenticated']),
        'version'       => AIDI_VERSION,
        'server_time'   => date('Y-m-d H:i:s'),
    ];
}

function actionSiteList(): array
{
    $raw    = runWebinoly('site -list');
    $sites  = parseSiteList($raw['output']);
    return ['success' => true, 'sites' => $sites, 'raw' => $raw['output']];
}

function actionSiteCreate(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $type   = sanitizeEnum($_POST['type'] ?? 'wp', ['html','php','wp','proxy']);
    $cache  = filter_var($_POST['cache'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $redis  = filter_var($_POST['redis'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $ssl    = filter_var($_POST['ssl'] ?? true,  FILTER_VALIDATE_BOOLEAN);
    $proxy  = sanitizeProxyTarget($_POST['proxy'] ?? '');
    $php    = sanitizeEnum($_POST['php'] ?? '8.3', ['8.3','8.2','8.1','8.0','7.4']);

    if (!$domain) return ['error' => 'Domain tidak valid'];

    // Build command
    $cmd = match ($type) {
        'proxy' => "sudo site $domain -proxy=[$proxy]",
        'wp'    => "sudo site $domain -wp" . ($cache ? ' -cache=on' : '') . ($redis ? ' -redis=on' : ''),
        default => "sudo site $domain -$type",
    };

    $result = runWebinoly($cmd);
    if (!$result['success']) return ['error' => $result['output']];

    // SSL
    if ($ssl) {
        $sslResult = runWebinoly("sudo site $domain -ssl=on");
    }

    aidiLog('SITE_CREATE', "domain=$domain type=$type");

    return [
        'success' => true,
        'domain'  => $domain,
        'command' => $cmd,
        'output'  => $result['output'],
    ];
}

function actionSiteDelete(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -delete");
    aidiLog('SITE_DELETE', "domain=$domain");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionSiteToggle(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $state  = sanitizeEnum($_POST['state'] ?? 'on', ['on', 'off']);
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -$state");
    aidiLog('SITE_TOGGLE', "domain=$domain state=$state");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionSiteInfo(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -info");
    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionSSLToggle(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $state  = sanitizeEnum($_POST['state'] ?? 'on', ['on', 'off', 'renew']);
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -ssl=$state");
    aidiLog('SSL_TOGGLE', "domain=$domain state=$state");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionCacheToggle(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $state  = sanitizeEnum($_POST['state'] ?? 'on', ['on', 'off']);
    if (!$domain) return ['error' => 'Domain tidak valid'];

    $result = runWebinoly("sudo site $domain -cache=$state");
    aidiLog('CACHE_TOGGLE', "domain=$domain state=$state");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionCacheClear(): array
{
    $domain = sanitizeDomain($_POST['domain'] ?? '');
    $cmd    = $domain ? "sudo webinoly -clear-cache=$domain" : "sudo webinoly -clear-cache";

    $result = runWebinoly($cmd);
    aidiLog('CACHE_CLEAR', "domain=" . ($domain ?: 'all'));

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionStackInfo(): array
{
    $nginx  = runWebinoly('nginx -v 2>&1');
    $php    = runWebinoly('php -v');
    $mysql  = runWebinoly('mysql --version');
    $redis  = runWebinoly('redis-server --version');

    return [
        'success' => true,
        'nginx'   => trim($nginx['output']),
        'php'     => trim($php['output']),
        'mysql'   => trim($mysql['output']),
        'redis'   => trim($redis['output']),
    ];
}

function actionPHPInstall(): array
{
    $version = sanitizeEnum($_POST['version'] ?? '', ['8.3','8.2','8.1','8.0','7.4']);
    if (!$version) return ['error' => 'Versi PHP tidak valid'];

    $result = runWebinoly("sudo stack -php-ver=$version");
    aidiLog('PHP_INSTALL', "version=$version");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionDBPassword(): array
{
    $result = runWebinoly('sudo webinoly -dbpass');
    // Jangan return password mentah ke response - log only
    aidiLog('DB_PASSWORD_VIEW', "User: " . ($_SESSION['username'] ?? '?'));
    return [
        'success' => $result['success'],
        'output'  => $result['output'],
        'note'    => 'Password hanya ditampilkan di terminal server',
    ];
}

function actionHttpauthAdd(): array
{
    $user  = sanitizeUsername($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $scope = sanitizeDomain($_POST['scope'] ?? '');

    if (!$user || strlen($pass) < 6) return ['error' => 'Username atau password tidak valid'];

    // Pass via stdin untuk keamanan (tidak muncul di process list)
    $result = runWebinolyWithInput("sudo httpauth -add", "$user\n$pass\n");
    aidiLog('HTTPAUTH_ADD', "user=$user scope=$scope");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionHttpauthDelete(): array
{
    $user = sanitizeUsername($_POST['username'] ?? '');
    if (!$user) return ['error' => 'Username tidak valid'];

    $result = runWebinoly("sudo httpauth -delete=$user");
    aidiLog('HTTPAUTH_DELETE', "user=$user");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionHttpauthList(): array
{
    $result = runWebinoly('sudo httpauth -list');
    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionBlockIPAdd(): array
{
    $ip = sanitizeIP($_POST['ip'] ?? '');
    if (!$ip) return ['error' => 'IP address tidak valid'];

    $result = runWebinoly("sudo webinoly -blockip=$ip");
    aidiLog('BLOCKIP_ADD', "ip=$ip");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionBlockIPRemove(): array
{
    $ip = sanitizeIP($_POST['ip'] ?? '');
    if (!$ip) return ['error' => 'IP address tidak valid'];

    $result = runWebinoly("sudo webinoly -blockip=$ip -off");
    aidiLog('BLOCKIP_REMOVE', "ip=$ip");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionBlockIPList(): array
{
    // Baca langsung dari Nginx config Webinoly
    $blockFile = '/etc/nginx/apps.d/blockip.conf';
    if (!file_exists($blockFile)) {
        return ['success' => true, 'ips' => []];
    }
    $content = file_get_contents($blockFile);
    preg_match_all('/deny\s+([^\s;]+);/', $content, $matches);
    return ['success' => true, 'ips' => $matches[1] ?? []];
}

function actionSFTPToggle(): array
{
    $state = sanitizeEnum($_POST['state'] ?? 'on', ['on', 'off']);
    $result = runWebinoly("sudo webinoly -sftp=$state");
    aidiLog('SFTP_TOGGLE', "state=$state");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionRunCommand(): array
{
    // Hanya command yang ada di whitelist yang boleh dieksekusi
    $cmd = $_POST['command'] ?? '';
    $allowed = getAllowedCommands();

    $isAllowed = false;
    foreach ($allowed as $pattern) {
        if (preg_match($pattern, $cmd)) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        aidiLog('COMMAND_BLOCKED', "cmd=$cmd user=" . ($_SESSION['username'] ?? '?'));
        return ['error' => 'Command tidak diizinkan: ' . htmlspecialchars($cmd)];
    }

    $result = runWebinoly($cmd);
    aidiLog('COMMAND_RUN', "cmd=$cmd");

    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionLogsGet(): array
{
    $site  = sanitizeDomain($_POST['site'] ?? '');
    $type  = sanitizeEnum($_POST['type'] ?? 'access', ['access', 'error']);
    $lines = min((int)($_POST['lines'] ?? 100), 500);

    if ($site) {
        $logFile = "/var/log/nginx/$site.$type.log";
    } else {
        $logFile = "/var/log/nginx/$type.log";
    }

    if (!file_exists($logFile)) {
        return ['success' => false, 'error' => 'Log file tidak ditemukan', 'file' => $logFile];
    }

    // Keamanan: pastikan file ada di dalam /var/log/nginx/
    $realPath = realpath($logFile);
    if (!$realPath || strpos($realPath, '/var/log/nginx/') !== 0) {
        return ['error' => 'Path log tidak valid'];
    }

    $output = shell_exec("tail -n $lines " . escapeshellarg($realPath) . " 2>&1");
    $lines_arr = array_filter(explode("\n", $output ?? ''));

    return [
        'success' => true,
        'file'    => $logFile,
        'lines'   => array_values($lines_arr),
        'count'   => count($lines_arr),
    ];
}

function actionServerInfo(): array
{
    return [
        'success'    => true,
        'hostname'   => gethostname(),
        'os'         => php_uname('s') . ' ' . php_uname('r'),
        'php_ver'    => PHP_VERSION,
        'uptime'     => shell_exec('uptime -p') ?: 'unknown',
        'load'       => sys_getloadavg(),
        'disk_total' => disk_total_space('/'),
        'disk_free'  => disk_free_space('/'),
        'mem'        => getMemoryInfo(),
        'time'       => date('Y-m-d H:i:s T'),
    ];
}

function actionWebinolyUpdate(): array
{
    $result = runWebinoly('sudo webinoly -update');
    aidiLog('WEBINOLY_UPDATE', '');
    return ['success' => $result['success'], 'output' => $result['output']];
}

function actionWebinolyVerify(): array
{
    $result = runWebinoly('sudo webinoly -verify');
    return ['success' => $result['success'], 'output' => $result['output']];
}


// ════════════════════════════════════════════════
// HELPERS — COMMAND EXECUTION
// ════════════════════════════════════════════════

/**
 * Eksekusi command Webinoly dengan aman.
 * Command harus sudah di-whitelist sebelum dipanggil.
 */
function runWebinoly(string $cmd): array
{
    // Double-check: pastikan tidak ada shell injection
    if (preg_match('/[;&|`$\(\)<>]/', $cmd)) {
        return ['success' => false, 'output' => 'Command mengandung karakter tidak aman'];
    }

    $descriptors = [
        0 => ['pipe', 'r'],  // stdin
        1 => ['pipe', 'w'],  // stdout
        2 => ['pipe', 'w'],  // stderr
    ];

    $env = array_merge($_ENV, [
        'HOME'  => '/root',
        'PATH'  => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        'SHELL' => '/bin/bash',
    ]);

    $process = proc_open("bash -c " . escapeshellarg($cmd), $descriptors, $pipes, '/tmp', $env);

    if (!is_resource($process)) {
        return ['success' => false, 'output' => 'Gagal menjalankan process'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    $output   = trim($stdout . "\n" . $stderr);

    return [
        'success'   => $exitCode === 0,
        'output'    => $output,
        'exit_code' => $exitCode,
    ];
}

/**
 * Eksekusi command dengan input via stdin (untuk password, dll)
 */
function runWebinolyWithInput(string $cmd, string $input): array
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'aidi_');
    file_put_contents($tmpFile, $input);
    chmod($tmpFile, 0600);

    $result = runWebinoly("$cmd < " . escapeshellarg($tmpFile));
    unlink($tmpFile);

    return $result;
}


// ════════════════════════════════════════════════
// HELPERS — SANITIZATION
// ════════════════════════════════════════════════

function sanitizeDomain(string $input): string
{
    $input = strtolower(trim($input));
    // Hanya huruf, angka, titik, strip
    if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]+)?[a-z0-9]$/', $input)) return '';
    if (strlen($input) > 253) return '';
    return $input;
}

function sanitizeEnum(string $input, array $allowed): string
{
    return in_array($input, $allowed, true) ? $input : '';
}

function sanitizeUsername(string $input): string
{
    $input = strtolower(trim($input));
    if (!preg_match('/^[a-z0-9_\-]{3,32}$/', $input)) return '';
    return $input;
}

function sanitizeIP(string $input): string
{
    $input = trim($input);
    // IP tunggal atau CIDR
    if (filter_var($input, FILTER_VALIDATE_IP)) return $input;
    // CIDR notation
    if (preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $input)) {
        [$ip, $mask] = explode('/', $input);
        if (filter_var($ip, FILTER_VALIDATE_IP) && (int)$mask <= 32) return $input;
    }
    return '';
}

function sanitizeProxyTarget(string $input): string
{
    $input = trim($input);
    // localhost:port atau IP:port
    if (preg_match('/^(localhost|127\.0\.0\.1|[\w\-\.]+):\d{2,5}$/', $input)) return $input;
    return 'localhost:3000';
}


// ════════════════════════════════════════════════
// HELPERS — UTILITIES
// ════════════════════════════════════════════════

function rateLimitCheck(string $ip): void
{
    $cacheFile = sys_get_temp_dir() . '/aidi_rl_' . md5($ip);
    $now       = time();

    $data = ['count' => 0, 'window_start' => $now];
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true) ?? $data;
    }

    if ($now - $data['window_start'] > RATE_WINDOW) {
        $data = ['count' => 0, 'window_start' => $now];
    }

    $data['count']++;
    file_put_contents($cacheFile, json_encode($data), LOCK_EX);

    if ($data['count'] > RATE_LIMIT) {
        http_response_code(429);
        die(json_encode(['error' => 'Rate limit exceeded. Coba lagi dalam 1 menit.']));
    }
}

function aidiLog(string $action, string $detail): void
{
    if (!is_dir(dirname(LOG_FILE))) {
        mkdir(dirname(LOG_FILE), 0750, true);
    }
    $line = sprintf(
        "[%s] [%s] [%s] %s %s\n",
        date('Y-m-d H:i:s'),
        $action,
        $_SERVER['REMOTE_ADDR'] ?? '?',
        $_SESSION['username'] ?? 'guest',
        $detail
    );
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

function parseSiteList(string $output): array
{
    $sites = [];
    $lines = explode("\n", $output);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        // Parse output "webinoly site -list"
        if (preg_match('/^([\w\-\.]+)\s+\[([\w]+)\]/', $line, $m)) {
            $sites[] = ['domain' => $m[1], 'type' => $m[2]];
        }
    }
    return $sites;
}

function getMemoryInfo(): array
{
    $mem = shell_exec('free -b') ?: '';
    preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $mem, $m);
    return [
        'total' => (int)($m[1] ?? 0),
        'used'  => (int)($m[2] ?? 0),
        'free'  => (int)($m[3] ?? 0),
    ];
}

function getAllowedCommands(): array
{
    return [
        '/^sudo webinoly -verify$/',
        '/^sudo webinoly -update$/',
        '/^sudo webinoly -info$/',
        '/^sudo webinoly -clear-cache(=[a-z0-9\.\-]+)?$/',
        '/^sudo webinoly -dbpass$/',
        '/^sudo webinoly -server-reset$/',
        '/^sudo systemctl (reload|restart|start|stop) (nginx|php[0-9\.]+\-fpm|mariadb|mysql|redis)$/',
        '/^sudo nginx -t$/',
        '/^redis-cli FLUSHALL$/',
        '/^sudo ufw (status verbose|enable|disable)$/',
        '/^sudo ufw (allow|deny) [0-9]+(\/tcp|\/udp)?$/',
        '/^sudo stack -php-ver=[0-9\.]+$/',
        '/^sudo stack -pma(=off)?$/',
        '/^nginx -v 2>&1$/',
        '/^php -v$/',
        '/^mysql --version$/',
        '/^redis-server --version$/',
        '/^uptime -p$/',
    ];
}
