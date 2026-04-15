<?php
require __DIR__ . '/common.php'; require_login();
$users=load_users(); $list=array_values($users);
usort($list, fn($a,$b)=> ($b['last_seen']??0) <=> ($a['last_seen']??0));
?><!doctype html><html><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Users</title>
<style>
body{font-family:system-ui,Arial;background:#0b1020;color:#e9ecff;margin:0}
header{padding:16px 18px;background:rgba(255,255,255,.05);border-bottom:1px solid rgba(255,255,255,.12);display:flex;justify-content:space-between;align-items:center}
a{color:#7fe7ff;text-decoration:none}
.wrap{max-width:1200px;margin:0 auto;padding:18px}
table{width:100%;border-collapse:collapse;font-size:14px}
th,td{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.10);vertical-align:top}
.badge{display:inline-flex;padding:3px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(0,0,0,.22);font-size:12px}
.btn{display:inline-flex;padding:8px 10px;border-radius:12px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.06);color:#e9ecff;cursor:pointer}
.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
</style></head><body>
<header>
  <div class="row"><a class="btn" href="index.php">← Dashboard</a><b>Users</b></div>
  <a class="btn" href="logout.php">Logout</a>
</header>
<div class="wrap">
<table>
<thead><tr><th>User</th><th>chat_id</th><th>Subscribed</th><th>Blocked</th><th>Last seen (UTC)</th><th>Action</th></tr></thead>
<tbody>
<?php foreach($list as $u):
  $name=$u['username']?'@'.$u['username']:($u['first_name']??'user');
  $cid=(int)($u['chat_id']??0);
?>
<tr>
<td><?=h($name)?></td>
<td><?=h($cid)?></td>
<td><span class="badge"><?=!empty($u['subscribed'])?'yes':'no'?></span></td>
<td><span class="badge"><?=!empty($u['blocked'])?'yes':'no'?></span></td>
<td><?=h(gmdate('Y-m-d H:i',(int)($u['last_seen']??0)))?></td>
<td class="row">
  <?php if(empty($u['blocked'])): ?>
    <form method="post" action="user_block.php"><input type="hidden" name="chat_id" value="<?=h($cid)?>"><button class="btn">⛔ Block</button></form>
  <?php else: ?>
    <form method="post" action="user_unblock.php"><input type="hidden" name="chat_id" value="<?=h($cid)?>"><button class="btn">✅ Unblock</button></form>
  <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div></body></html>
