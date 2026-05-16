<?php
/**
 * AIDI Panel — Login Page
 * Halaman login yang dilayani oleh PHP sebelum masuk ke panel HTML
 */
declare(strict_types=1);

define('CONFIG_FILE', __DIR__ . '/config.php');
if (!file_exists(CONFIG_FILE)) { http_response_code(500); die('Config tidak ditemukan'); }
require CONFIG_FILE;

session_name('aidi_session');
session_start([
    'cookie_httponly' => true,
    'cookie_secure'   => true,
    'cookie_samesite' => 'Strict',
]);

// Sudah login? Redirect ke panel
if (!empty($_SESSION['authenticated'])) {
    header('Location: /');
    exit;
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    if ($user === AIDI_rezzaidr && password_verify($pass, AIDI_PASSWORD_HASH)) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username']      = $user;
        $_SESSION['login_time']    = time();
        $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

        // Log login sukses
        $logLine = sprintf("[%s] LOGIN_SUCCESS user=%s ip=%s\n",
            date('Y-m-d H:i:s'), $user, $_SERVER['REMOTE_ADDR'] ?? '?');
        file_put_contents(__DIR__ . '/logs/aidi.log', $logLine, FILE_APPEND | LOCK_EX);

        header('Location: /');
        exit;
    }

    sleep(1); // Anti brute-force
    $error = 'Username atau password salah.';

    $logLine = sprintf("[%s] LOGIN_FAILED ip=%s\n",
        date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'] ?? '?');
    file_put_contents(__DIR__ . '/logs/aidi.log', $logLine, FILE_APPEND | LOCK_EX);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AIDI Panel — Login</title>
<link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Geist',system-ui,sans-serif;background:#f9f8f6;min-height:100vh;display:flex;align-items:center;justify-content:center;color:#1a1917}
.card{background:#fff;border:1px solid #e2e0db;border-radius:10px;padding:36px;width:100%;max-width:380px;box-shadow:0 4px 20px rgba(0,0,0,.06)}
.logo{display:flex;align-items:center;gap:10px;margin-bottom:28px;justify-content:center}
.logo-mark{width:32px;height:32px;background:#1a1917;border-radius:8px;display:flex;align-items:center;justify-content:center}
.logo-mark svg{width:16px;height:16px;fill:#f9f8f6}
.logo-name{font-size:18px;font-weight:700;letter-spacing:-.01em}
.logo-sub{font-size:11px;color:#a09d97;margin-top:1px}
h1{font-size:16px;font-weight:600;margin-bottom:4px;text-align:center}
.sub{font-size:13px;color:#6b6860;text-align:center;margin-bottom:24px}
.fg{margin-bottom:14px}
label{display:block;font-size:11px;font-weight:600;color:#6b6860;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px}
input{width:100%;background:#f3f2ef;border:1px solid #d0cdc7;border-radius:6px;padding:9px 12px;font-family:'Geist',sans-serif;font-size:13px;color:#1a1917;outline:none;transition:border-color .15s}
input:focus{border-color:#2563eb;background:#fff}
.btn{width:100%;background:#1a1917;color:#f9f8f6;border:none;border-radius:6px;padding:10px;font-family:'Geist',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:background .15s;margin-top:6px}
.btn:hover{background:#2d2c2a}
.error{background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:10px 13px;font-size:12px;color:#dc2626;margin-bottom:14px}
.footer{text-align:center;font-size:11px;color:#a09d97;margin-top:20px}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-mark"><svg viewBox="0 0 16 16"><path d="M8 2a6 6 0 100 12A6 6 0 008 2zm0 2a4 4 0 110 8 4 4 0 010-8zm0 1.5a2.5 2.5 0 100 5 2.5 2.5 0 000-5z"/></svg></div>
    <div><div class="logo-name">AIDI Panel</div><div class="logo-sub">Server Manager</div></div>
  </div>
  <h1>Masuk ke Panel</h1>
  <p class="sub">Gunakan kredensial yang dibuat saat instalasi</p>

  <?php if ($error): ?>
  <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="fg">
      <label>Username</label>
      <input type="text" name="username" placeholder="admin" required autofocus
             value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
    </div>
    <div class="fg">
      <label>Password</label>
      <input type="password" name="password" placeholder="••••••••••" required>
    </div>
    <button class="btn" type="submit">Masuk →</button>
  </form>
  <div class="footer">AIDI Panel v1.0.0 — Built for Webinoly</div>
</div>
</body>
</html>
