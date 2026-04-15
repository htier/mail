<?php
session_start();
$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $u = $_POST['username'] ?? '';
  $p = $_POST['password'] ?? '';
  $hash = hash('sha256', $p);
  if($u === ($config['blog']['admin_user'] ?? 'admin') && hash_equals($config['blog']['admin_pass_sha256'], $hash)){
    $_SESSION['blog_admin'] = true;
    header("Location: /admin/panel.php?lang=".$lang);
    exit;
  }
  $err = "Invalid login";
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<link rel="stylesheet" href="/assets/css/styles.css"/>
<title>Admin Login</title>
</head><body>
<div class="bg-art"></div>
<div class="container">
  <div class="panel" style="max-width:520px;margin:40px auto">
    <h2>Admin Login</h2>
    <!--<p style="color:var(--muted)">Default password is <code>change-me</code>. Change it in <code>config/config.json</code>.</p>-->
    <?php if(!empty($err)): ?><p style="color:#ff8fb2"><?php echo htmlspecialchars($err); ?></p><?php endif; ?>
    <form method="post">
      <div style="display:grid;gap:10px;margin-top:12px">
        <input class="input" name="username" placeholder="Username" required/>
        <input class="input" name="password" placeholder="Password" type="password" required/>
        <button class="btn" type="submit">Login</button>
        <a class="btn secondary" href="/blog.php?lang=<?php echo htmlspecialchars($lang); ?>">Back</a>
      </div>
    </form>
  </div>
</div>
</body></html>
