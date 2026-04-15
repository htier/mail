<?php
require __DIR__ . '/common.php';
$token = $_GET['token'] ?? '';
if($token){
  [$ok,$val]=tm_validate_admin_token($token);
  if($ok && tm_is_admin($val,$ADMINS)){
    $_SESSION['admin_id']=$val;
    header('Location: index.php'); exit;
  }
  $err = $ok ? 'not_admin' : $val;
}
?><!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Login</title>
<style>
body{font-family:system-ui,Arial;background:#0b1020;color:#e9ecff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{width:min(520px,92vw);background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:22px}
.bad{color:#ff88d6}
.small{opacity:.85}
</style></head><body>
<div class="card">
<h2>Admin login</h2>
<p class="small">Open the one-time login link from Telegram admin menu.</p>
<?php if(!empty($err)): ?><p class="bad">Token error: <?=h($err)?></p><?php endif; ?>
<p class="small">No token? Go back to Telegram → /start → Open Web Admin.</p>
</div></body></html>
