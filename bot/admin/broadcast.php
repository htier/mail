<?php
require __DIR__ . '/common.php'; require_login();
$jobs=load_jobs(); $msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  $text=trim($_POST['text']??'');
  if($text!==''){
    $id=bin2hex(random_bytes(6));
    $jobs[$id]=['id'=>$id,'text'=>$text,'created_by'=>(int)$_SESSION['admin_id'],'status'=>'queued','created_at'=>time(),'sent'=>0,'failed'=>0,'cursor'=>0];
    save_jobs($jobs); $msg="Created JOB_ID: $id";
  }
}
?><!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Broadcast</title>
<style>
body{font-family:system-ui,Arial;background:#0b1020;color:#e9ecff;margin:0}
header{padding:16px 18px;background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center}
.wrap{max-width:900px;margin:0 auto;padding:18px}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px}
.btn{display:inline-flex;padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#e9ecff;cursor:pointer;text-decoration:none}
textarea{width:100%;min-height:140px;padding:10px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.22);color:#e9ecff}
.small{opacity:.8;font-size:13px}
.ok{color:#7fe7ff}
</style></head><body>
<header><div><a class="btn" href="index.php">← Dashboard</a> <b>Broadcast</b></div><a class="btn" href="logout.php">Logout</a></header>
<div class="wrap"><div class="card">
<?php if($msg): ?><p class="ok"><?=h($msg)?></p><?php endif; ?>
<form method="post">
<div class="small">Message to subscribed users</div>
<textarea name="text" placeholder="Type broadcast message..."></textarea>
<div style="margin-top:10px;"><button class="btn" type="submit">📣 Create job</button></div>
</form>
<p class="small">Cron: run <code>bot/cron_send.php</code> every minute.</p>
</div></div>
</body></html>
