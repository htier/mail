<?php
session_start();
$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang   = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';

// Already logged in
if (!empty($_SESSION['blog_admin'])) {
    header("Location: /admin/index.php?lang=" . $lang);
    exit;
}

$err = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u    = trim($_POST['username'] ?? '');
    $p    = $_POST['password'] ?? '';
    $hash = hash('sha256', $p);
    if (
        $u === ($config['blog']['admin_user'] ?? 'admin') &&
        hash_equals($config['blog']['admin_pass_sha256'] ?? '', $hash)
    ) {
        session_regenerate_id(true);
        $_SESSION['blog_admin'] = true;
        header("Location: /admin/index.php?lang=" . $lang);
        exit;
    }
    $err = "Invalid username or password";
}

$brand = $config['site']['brand'] ?? 'TeMail.pro';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <meta name="robots" content="noindex, nofollow"/>
  <title>Admin Login — <?= htmlspecialchars($brand) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=2.0"/>
</head>
<body>
<div class="bg-art" aria-hidden="true"></div>
<div class="container">
  <div class="panel" style="max-width:440px;margin:60px auto">

    <div style="text-align:center;margin-bottom:22px">
      <div style="width:52px;height:52px;border-radius:16px;background:linear-gradient(135deg,#6fe7ff,#c056ff);display:grid;place-items:center;font-size:24px;margin:0 auto 12px">✉</div>
      <h2 style="margin:0"><?= htmlspecialchars($brand) ?></h2>
      <p style="color:var(--muted);font-size:14px;margin:6px 0 0">Admin Panel</p>
    </div>

    <?php if ($err): ?>
      <p style="background:rgba(255,82,82,.12);border:1px solid rgba(255,82,82,.3);color:#ff8a80;padding:10px 14px;border-radius:10px;font-size:13px;margin-bottom:14px">
        <?= htmlspecialchars($err) ?>
      </p>
    <?php endif; ?>

    <form method="post" style="display:grid;gap:10px">
      <input class="input"  name="username" placeholder="Username" autocomplete="username" required/>
      <input class="input"  name="password" placeholder="Password" type="password" autocomplete="current-password" required/>
      <button class="btn"   type="submit">Login →</button>
      <a class="btn secondary" href="/index.php?lang=<?= htmlspecialchars($lang) ?>">← Back to site</a>
    </form>

    <p style="color:var(--muted);font-size:12px;text-align:center;margin-top:16px">
      Change password in <code>config/config.json</code>
    </p>
  </div>
</div>
</body>
</html>
