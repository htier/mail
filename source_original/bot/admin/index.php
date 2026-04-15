<?php
require __DIR__ . '/common.php';
require_login();

$users   = load_users();
$jobs    = load_jobs();
$premium = load_premium();
$posts   = load_posts();
$now     = time();

// Bot stats
$total = 0; $subs = 0; $a24 = 0; $a7 = 0; $blocked = 0;
foreach ($users as $u) {
    $total++;
    if (!empty($u['subscribed'])) $subs++;
    if (!empty($u['blocked'])) $blocked++;
    $ls = $u['last_seen'] ?? 0;
    if ($now - $ls <= 86400) $a24++;
    if ($now - $ls <= 86400 * 7) $a7++;
}

// Premium stats (skip _user_* keys)
$premiumActive = 0; $premiumTotal = 0;
foreach ($premium as $k => $v) {
    if (strpos($k, '_user_') === 0) continue;
    $premiumTotal++;
    if (is_array($v) && ($v['expires_at'] ?? 0) >= $now) $premiumActive++;
}

// Blog stats
$postData  = $posts['posts'] ?? [];
$published = count(array_filter($postData, fn($p) => ($p['status'] ?? '') === 'published'));
$drafts    = count($postData) - $published;

// Recent premium codes
$recentPremium = [];
foreach ($premium as $k => $v) {
    if (strpos($k, '_user_') === 0) continue;
    if (is_array($v)) { $v['_code'] = $k; $recentPremium[] = $v; }
}
usort($recentPremium, fn($a,$b) => ($b['created_at']??0) <=> ($a['created_at']??0));
$recentPremium = array_slice($recentPremium, 0, 20);

// Recent users
$userList = array_values($users);
usort($userList, fn($a,$b) => ($b['last_seen']??0) <=> ($a['last_seen']??0));
$recentUsers = array_slice($userList, 0, 30);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Admin Panel — TeMail.pro</title>
<style>
*{box-sizing:border-box}
body{font-family:ui-sans-serif,system-ui,Arial;background:#07091c;color:#dce8ff;margin:0;font-size:14px}
a{color:#00dba4;text-decoration:none}
a:hover{text-decoration:underline}
header{padding:14px 20px;background:rgba(0,219,164,.06);border-bottom:1px solid rgba(255,255,255,.08);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
header h1{margin:0;font-size:18px}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.wrap{max-width:1300px;margin:0 auto;padding:18px 16px}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;border-bottom:1px solid rgba(255,255,255,.07);padding-bottom:12px}
.tab{padding:9px 16px;border-radius:12px;border:1px solid transparent;color:#5a6a8a;cursor:pointer;font-size:14px;font-weight:600;background:none;font-family:inherit;transition:all .2s}
.tab:hover{color:#dce8ff;background:rgba(255,255,255,.04)}
.tab.active{color:#00dba4;border-color:rgba(0,219,164,.30);background:rgba(0,219,164,.07)}
.section{display:none}
.section.active{display:block}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
@media(max-width:900px){.stats-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:500px){.stats-grid{grid-template-columns:1fr}}
.stat-card{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:16px}
.stat-card .lbl{color:#5a6a8a;font-size:12px;margin-bottom:4px}
.stat-card .val{font-size:28px;font-weight:800}
.stat-card .val.teal{color:#00dba4}
.stat-card .val.gold{color:#ffb347}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;margin-bottom:14px}
.card h3{margin:0 0 12px;font-size:16px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:9px 8px;border-bottom:1px solid rgba(255,255,255,.10);text-align:left;color:#5a6a8a;font-weight:600}
td{padding:9px 8px;border-bottom:1px solid rgba(255,255,255,.06);vertical-align:middle}
tr:last-child td{border-bottom:none}
.badge{display:inline-flex;padding:3px 9px;border-radius:999px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.05);font-size:11px}
.badge.green{border-color:rgba(0,219,164,.30);color:#00dba4;background:rgba(0,219,164,.07)}
.badge.gold{border-color:rgba(255,179,71,.30);color:#ffb347;background:rgba(255,149,0,.07)}
.badge.red{border-color:rgba(255,80,80,.30);color:#ff8080;background:rgba(255,80,80,.07)}
.badge.blue{border-color:rgba(76,125,255,.30);color:#7ab0ff;background:rgba(76,125,255,.07)}
.btn{display:inline-flex;padding:8px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:rgba(255,255,255,.06);color:#dce8ff;cursor:pointer;font-size:13px;font-family:inherit;font-weight:600;text-decoration:none}
.btn:hover{background:rgba(255,255,255,.10);text-decoration:none}
.btn.danger{border-color:rgba(255,80,80,.25);color:#ff8080;background:rgba(255,80,80,.07)}
.btn.danger:hover{background:rgba(255,80,80,.14)}
.btn.primary{background:linear-gradient(90deg,#00dba4,#4c7dff);color:#040e14;border:none;font-weight:800}
.btn.primary:hover{opacity:.9}
form{margin:0}
input,select,textarea{background:rgba(0,0,0,.30);border:1px solid rgba(255,255,255,.09);color:#dce8ff;padding:9px 12px;border-radius:12px;font-family:inherit;font-size:13px;width:100%;outline:none;margin-top:4px}
input:focus,select:focus,textarea:focus{border-color:rgba(0,219,164,.40)}
textarea{min-height:120px;resize:vertical}
label{display:block;color:#5a6a8a;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
.mono{font-family:ui-monospace,"Cascadia Code",monospace;letter-spacing:1px}
</style>
</head>
<body>
<header>
  <div>
    <h1>🛠 TeMail Admin</h1>
    <div style="color:#5a6a8a;font-size:12px;margin-top:2px">Logged in · <?= date('Y-m-d H:i') ?> UTC</div>
  </div>
  <div class="row">
    <a class="btn" href="broadcast.php">📣 Broadcast</a>
    <a class="btn" href="users.php">👥 Users</a>
    <a class="btn" href="logout.php">Logout</a>
  </div>
</header>

<div class="wrap">
  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="showTab('dashboard',this)">📊 Dashboard</button>
    <button class="tab" onclick="showTab('bot_users',this)">🤖 Bot Users</button>
    <button class="tab" onclick="showTab('premium_tab',this)">⭐ Premium</button>
    <button class="tab" onclick="showTab('blog_tab',this)">📝 Blog</button>
    <button class="tab" onclick="showTab('broadcast_tab',this)">📣 Broadcast</button>
  </div>

  <!-- ===== DASHBOARD ===== -->
  <div id="dashboard" class="section active">
    <div class="stats-grid">
      <div class="stat-card"><div class="lbl">Total Bot Users</div><div class="val teal"><?= $total ?></div></div>
      <div class="stat-card"><div class="lbl">Active 24h</div><div class="val"><?= $a24 ?></div></div>
      <div class="stat-card"><div class="lbl">Premium Active</div><div class="val gold"><?= $premiumActive ?></div></div>
      <div class="stat-card"><div class="lbl">Blog Posts</div><div class="val"><?= $published ?></div></div>
      <div class="stat-card"><div class="lbl">Subscribed</div><div class="val"><?= $subs ?></div></div>
      <div class="stat-card"><div class="lbl">Active 7d</div><div class="val"><?= $a7 ?></div></div>
      <div class="stat-card"><div class="lbl">Premium Total</div><div class="val"><?= $premiumTotal ?></div></div>
      <div class="stat-card"><div class="lbl">Blocked</div><div class="val" style="color:#ff8080"><?= $blocked ?></div></div>
    </div>

    <!-- Recent Bot Users -->
    <div class="card">
      <h3>🕒 Recent Bot Users</h3>
      <table>
        <thead><tr><th>User</th><th>Chat ID</th><th>Lang</th><th>Last seen</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($recentUsers, 0, 10) as $u): ?>
          <tr>
            <td><?= h($u['username'] ? '@'.$u['username'] : ($u['first_name'] ?? 'user')) ?></td>
            <td class="mono"><?= h($u['chat_id'] ?? '') ?></td>
            <td><?= h(strtoupper($u['lang'] ?? 'en')) ?></td>
            <td><?= h(gmdate('Y-m-d H:i', $u['last_seen'] ?? 0)) ?></td>
            <td>
              <?php if (!empty($u['blocked'])): ?><span class="badge red">Blocked</span>
              <?php elseif (!empty($u['subscribed'])): ?><span class="badge green">Active</span>
              <?php else: ?><span class="badge">Inactive</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ===== BOT USERS ===== -->
  <div id="bot_users" class="section">
    <div class="card">
      <h3>🤖 All Bot Users (<?= $total ?>)</h3>
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>User</th><th>Chat ID</th><th>Lang</th><th>Last seen</th><th>Subscribed</th><th>Blocked</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($userList as $u): ?>
          <tr>
            <td><?= h($u['username'] ? '@'.$u['username'] : ($u['first_name'] ?? 'user')) ?></td>
            <td class="mono"><?= h($u['chat_id'] ?? '') ?></td>
            <td><?= h(strtoupper($u['lang'] ?? 'en')) ?></td>
            <td><?= h(gmdate('Y-m-d H:i', $u['last_seen'] ?? 0)) ?></td>
            <td><?= !empty($u['subscribed']) ? '<span class="badge green">Yes</span>' : '<span class="badge">No</span>' ?></td>
            <td><?= !empty($u['blocked']) ? '<span class="badge red">Yes</span>' : '—' ?></td>
            <td>
              <?php if (empty($u['blocked'])): ?>
                <form method="post" action="user_block.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= h($u['chat_id'] ?? '') ?>">
                  <button class="btn danger" type="submit" onclick="return confirm('Block this user?')">Block</button>
                </form>
              <?php else: ?>
                <form method="post" action="user_unblock.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= h($u['chat_id'] ?? '') ?>">
                  <button class="btn" type="submit">Unblock</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <!-- ===== PREMIUM ===== -->
  <div id="premium_tab" class="section">
    <div class="stats-grid" style="grid-template-columns:repeat(3,1fr)">
      <div class="stat-card"><div class="lbl">Active Premium</div><div class="val gold"><?= $premiumActive ?></div></div>
      <div class="stat-card"><div class="lbl">Total Codes Issued</div><div class="val"><?= $premiumTotal ?></div></div>
      <div class="stat-card"><div class="lbl">Expired / Redeemed</div><div class="val"><?= $premiumTotal - $premiumActive ?></div></div>
    </div>
    <div class="card">
      <h3>⭐ Premium Codes</h3>
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Code</th><th>User ID</th><th>Created</th><th>Expires</th><th>Redeemed</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recentPremium as $c): ?>
          <tr>
            <td class="mono" style="font-size:12px"><?= h($c['_code'] ?? '') ?></td>
            <td class="mono"><?= h($c['user_id'] ?? '') ?></td>
            <td><?= h(gmdate('Y-m-d', $c['created_at'] ?? 0)) ?></td>
            <td><?= h(gmdate('Y-m-d', $c['expires_at'] ?? 0)) ?></td>
            <td><?= !empty($c['redeemed']) ? '<span class="badge blue">Yes</span>' : '—' ?></td>
            <td>
              <?php if (($c['expires_at'] ?? 0) >= $now): ?>
                <span class="badge green">Active</span>
              <?php else: ?>
                <span class="badge red">Expired</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($recentPremium)): ?>
          <tr><td colspan="6" style="color:#5a6a8a;text-align:center;padding:20px">No premium codes yet</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <!-- ===== BLOG ===== -->
  <div id="blog_tab" class="section">
    <div class="row" style="justify-content:space-between;margin-bottom:14px">
      <h3 style="margin:0">📝 Blog Posts (<?= count($postData) ?>)</h3>
      <button class="btn primary" onclick="showPostForm()">+ New Post</button>
    </div>

    <!-- Post form (hidden by default) -->
    <div class="card" id="postForm" style="display:none">
      <h3 id="postFormTitle">New Post</h3>
      <form id="postFormEl" onsubmit="submitPost(event)">
        <input type="hidden" id="postId" name="id" value=""/>
        <div class="form-row">
          <div>
            <label>Title</label>
            <input type="text" id="postTitle" name="title" required/>
          </div>
          <div>
            <label>Status</label>
            <select id="postStatus" name="status">
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div>
            <label>Published Date</label>
            <input type="date" id="postDate" name="published_at"/>
          </div>
          <div>
            <label>Tags (comma-separated)</label>
            <input type="text" id="postTags" name="tags" placeholder="temp mail, privacy, email"/>
          </div>
        </div>
        <div style="margin-bottom:12px">
          <label>Cover Image URL (optional)</label>
          <input type="text" id="postCover" name="cover" placeholder="https://..."/>
        </div>
        <div style="margin-bottom:12px">
          <label>Content (HTML)</label>
          <textarea id="postContent" name="content" style="min-height:200px" required></textarea>
        </div>
        <div class="row">
          <button class="btn primary" type="submit">Save Post</button>
          <button class="btn" type="button" onclick="hidePostForm()">Cancel</button>
        </div>
        <div id="postMsg" style="margin-top:10px;font-size:13px;color:#00dba4"></div>
      </form>
    </div>

    <!-- Posts list -->
    <div class="card">
      <div style="overflow-x:auto">
      <table>
        <thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Tags</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($postData) as $p): ?>
          <tr>
            <td><a href="/post.php?id=<?= h($p['id']) ?>" target="_blank"><?= h($p['title'] ?? '') ?></a></td>
            <td>
              <?php if (($p['status'] ?? '') === 'published'): ?>
                <span class="badge green">Published</span>
              <?php else: ?>
                <span class="badge">Draft</span>
              <?php endif; ?>
            </td>
            <td><?= h($p['published_at'] ?? '') ?></td>
            <td><?= h(implode(', ', $p['tags'] ?? [])) ?></td>
            <td class="row" style="gap:6px">
              <button class="btn" onclick='editPost(<?= json_encode($p, JSON_HEX_QUOT|JSON_HEX_APOS) ?>)'>Edit</button>
              <button class="btn danger" onclick="deletePost('<?= h($p['id']) ?>')">Delete</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

  <!-- ===== BROADCAST ===== -->
  <div id="broadcast_tab" class="section">
    <div class="card" style="max-width:640px">
      <h3>📣 Broadcast Message</h3>
      <p style="color:#5a6a8a;margin:0 0 14px">Send a message to all <?= $subs ?> subscribed users.</p>
      <form action="broadcast.php" method="get">
        <a class="btn primary" href="broadcast.php">Open Broadcast Panel →</a>
      </form>
    </div>
    <div class="card">
      <h3>📋 Broadcast Jobs</h3>
      <table>
        <thead><tr><th>Job ID</th><th>Status</th><th>Sent</th><th>Failed</th><th>Created</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach (array_reverse($jobs, true) as $j): ?>
          <tr>
            <td class="mono"><?= h($j['id'] ?? '') ?></td>
            <td><span class="badge <?= ($j['status']??'') === 'done' ? 'green' : (($j['status']??'') === 'canceled' ? 'red' : 'blue') ?>"><?= h($j['status'] ?? '') ?></span></td>
            <td><?= h($j['sent'] ?? 0) ?></td>
            <td><?= h($j['failed'] ?? 0) ?></td>
            <td><?= h(gmdate('Y-m-d H:i', $j['created_at'] ?? 0)) ?></td>
            <td>
              <?php if (in_array($j['status'] ?? '', ['queued','sending'])): ?>
                <form method="post" action="job_cancel.php" style="display:inline">
                  <input type="hidden" name="id" value="<?= h($j['id']) ?>">
                  <button class="btn danger" type="submit">Cancel</button>
                </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function showTab(id, el) {
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  el.classList.add('active');
}

function showPostForm() {
  document.getElementById('postForm').style.display = '';
  document.getElementById('postFormTitle').textContent = 'New Post';
  document.getElementById('postFormEl').reset();
  document.getElementById('postId').value = '';
  document.getElementById('postDate').value = new Date().toISOString().slice(0,10);
  document.getElementById('postForm').scrollIntoView({behavior:'smooth'});
}
function hidePostForm() { document.getElementById('postForm').style.display = 'none'; }
function editPost(p) {
  showPostForm();
  document.getElementById('postFormTitle').textContent = 'Edit Post';
  document.getElementById('postId').value = p.id || '';
  document.getElementById('postTitle').value = p.title || '';
  document.getElementById('postStatus').value = p.status || 'published';
  document.getElementById('postDate').value = p.published_at || '';
  document.getElementById('postTags').value = (p.tags||[]).join(', ');
  document.getElementById('postCover').value = p.cover || '';
  document.getElementById('postContent').value = p.content || '';
}
async function submitPost(e) {
  e.preventDefault();
  const fd = new FormData(e.target);
  const data = Object.fromEntries(fd.entries());
  data.tags = data.tags ? data.tags.split(',').map(t=>t.trim()).filter(Boolean) : [];
  const res = await fetch('/api/blog.php?action=save', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  const j = await res.json().catch(()=>null);
  const msg = document.getElementById('postMsg');
  if (j && j.ok) {
    msg.textContent = '✓ Saved!'; msg.style.color='#00dba4';
    setTimeout(()=>location.reload(), 800);
  } else {
    msg.textContent = '⚠️ Error saving post'; msg.style.color='#ff8080';
  }
}
async function deletePost(id) {
  if (!confirm('Delete this post?')) return;
  const res = await fetch('/api/blog.php?action=delete', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({id})
  });
  const j = await res.json().catch(()=>null);
  if (j && j.ok) location.reload();
  else alert('Error deleting post');
}
</script>
</body>
</html>
