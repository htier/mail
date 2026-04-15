<?php
require __DIR__ . '/common.php';
$err = '';

// Method 1: One-time Telegram token
$token = $_GET['token'] ?? '';
if ($token) {
    [$ok, $val] = tm_validate_admin_token($token);
    if ($ok && tm_is_admin($val, $ADMINS)) {
        $_SESSION['admin_id'] = $val;
        header('Location: index.php'); exit;
    }
    $err = $ok ? 'not_admin' : $val;
}

// Method 2: Password login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $adminPass = $siteConfig['blog']['admin_pass_sha256'] ?? '';
    $input = hash('sha256', $_POST['password']);
    if (hash_equals($adminPass, $input)) {
        $_SESSION['admin_id'] = 9999; // password-based login marker
        header('Location: index.php'); exit;
    }
    $err = 'wrong_password';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Login — TeMail</title>
<style>
*{box-sizing:border-box}
body{font-family:ui-sans-serif,system-ui,Arial;background:#07091c;color:#dce8ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{width:min(480px,94vw);background:rgba(10,15,34,.92);border:1px solid rgba(0,219,164,.22);border-radius:20px;padding:28px;box-shadow:0 24px 80px rgba(0,0,0,.70)}
h2{margin:0 0 6px;font-size:22px}
p{margin:8px 0;color:#5a6a8a;font-size:14px}
.err{color:#ff8080;font-size:14px;margin-top:8px}
.divider{display:flex;align-items:center;gap:10px;margin:18px 0;color:#5a6a8a;font-size:13px}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.08)}
input[type=password]{width:100%;background:rgba(0,0,0,.30);border:1px solid rgba(255,255,255,.09);color:#dce8ff;padding:12px 14px;border-radius:14px;font-size:14px;font-family:inherit;outline:none;margin-top:6px}
input[type=password]:focus{border-color:rgba(0,219,164,.40)}
.btn{width:100%;border:none;padding:12px;border-radius:14px;background:linear-gradient(90deg,#00dba4,#4c7dff);color:#040e14;font-weight:800;font-size:14px;cursor:pointer;margin-top:12px}
.btn:hover{opacity:.9}
.note{background:rgba(0,219,164,.07);border:1px solid rgba(0,219,164,.18);border-radius:12px;padding:12px;font-size:13px;color:#8ab0a0;margin-top:16px}
</style>
</head>
<body>
<div class="card">
  <h2>🔐 Admin Login</h2>
  <p>TeMail.pro Admin Panel</p>

  <?php if ($err): ?>
    <p class="err">⚠️ Error: <?= h($err) ?></p>
  <?php endif; ?>

  <!-- Password login -->
  <form method="post">
    <label style="font-size:14px;color:#5a6a8a">Admin Password</label>
    <input type="password" name="password" placeholder="Enter password" autofocus/>
    <button class="btn" type="submit">Sign In</button>
  </form>

  <div class="divider">OR</div>

  <div class="note">
    <strong style="color:#dce8ff">Login via Telegram</strong><br/>
    Open the bot → tap 🔐 Web Admin button → get a one-time 5-minute login link.
    <br/><br/>
    <a href="https://t.me/temail_pro_bot" target="_blank" style="color:#00dba4">Open Telegram Bot →</a>
  </div>
</div>
</body>
</html>
