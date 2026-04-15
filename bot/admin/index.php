<?php
require __DIR__ . '/common.php'; require_login();
$users=load_users(); $jobs=load_jobs(); $now=time();
$total=count($users); $subs=0; $a24=0; $a7=0; $blocked=0;
foreach($users as $u){
  if(!empty($u['subscribed'])) $subs++;
  if(!empty($u['blocked'])) $blocked++;
  $ls=$u['last_seen']??0;
  if($now-$ls<=86400) $a24++;
  if($now-$ls<=86400*7) $a7++;
}
?><!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>TeMail Admin</title>
<style>
body{font-family:system-ui,Arial;background:#0b1020;color:#e9ecff;margin:0}
header{padding:16px 18px;background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center}
a{color:#7fe7ff;text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:18px}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
@media(max-width:900px){.grid{grid-template-columns:repeat(2,1fr)}}
.card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:14px}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px}
th,td{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.10);vertical-align:top}
.badge{display:inline-flex;padding:3px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.22);font-size:12px}
.btn{display:inline-flex;padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#e9ecff;cursor:pointer}
.small{opacity:.8;font-size:13px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
</style></head><body>
<header>
  <div><b>TeMail Admin</b> <span class="badge">secure token login</span></div>
  <div class="row">
    <a class="btn" href="broadcast.php">📣 Broadcast</a>
    <a class="btn" href="users.php">👥 Users</a>
    <a class="btn" href="logout.php">Logout</a>
  </div>
</header>
<div class="wrap">
  <div class="grid">
    <div class="card"><div class="small">Total</div><h2><?=h($total)?></h2></div>
    <div class="card"><div class="small">Subscribed</div><h2><?=h($subs)?></h2></div>
    <div class="card"><div class="small">Active 24h</div><h2><?=h($a24)?></h2></div>
    <div class="card"><div class="small">Active 7d</div><h2><?=h($a7)?></h2></div>
  </div>
  <div class="card" style="margin-top:12px;">
    <div class="row" style="justify-content:space-between;">
      <b>Broadcast jobs</b><span class="small">Blocked: <?=h($blocked)?></span>
    </div>
    <table>
      <thead><tr><th>Job</th><th>Status</th><th>Sent</th><th>Failed</th><th>Created (UTC)</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach($jobs as $j): ?>
        <tr>
          <td><?=h($j['id']??'')?></td>
          <td><span class="badge"><?=h($j['status']??'')?></span></td>
          <td><?=h($j['sent']??0)?></td>
          <td><?=h($j['failed']??0)?></td>
          <td><?=h(gmdate('Y-m-d H:i',(int)($j['created_at']??0)))?></td>
          <td>
            <?php if(($j['status']??'')==='queued' || ($j['status']??'')==='sending'): ?>
            <form method="post" action="job_cancel.php" style="display:inline">
              <input type="hidden" name="id" value="<?=h($j['id'])?>">
              <button class="btn" type="submit">⛔ Cancel</button>
            </form>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="small">Set Cron: run <code>bot/cron_send.php</code> every 1 minute.</div>
  </div>
</div>
</body></html>
