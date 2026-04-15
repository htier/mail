<?php
/**
 * TeMail.pro — Unified Admin Panel
 *
 * Works in two modes:
 *   1. Web browser  — PHP session auth (login.php → POST → session)
 *   2. Telegram TMA — Telegram initData HMAC-SHA256 validation (JS-only)
 *
 * Both modes share the same HTML/CSS/JS.
 * The panel shows:
 *   Tab 1 — Blog Posts  (create / edit / delete)
 *   Tab 2 — Stats       (users, posts, sessions)
 *   Tab 3 — Broadcast   (TMA-only, hidden in web mode)
 */
session_start();

$isTma = false; // will be determined client-side by JS

// Web auth check
$webAuth = !empty($_SESSION['blog_admin']);
if (!$webAuth) {
    // If no valid session AND not in TMA mode, redirect to login
    // TMA mode is detected client-side; we allow the page to load but
    // JS will call /api/tg_auth.php to verify Telegram identity.
    // If neither session nor valid TMA initData → access denied screen.
    $noSession = true;
}

$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang   = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';
$brand  = $config['site']['brand'] ?? 'TeMail.pro';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="robots" content="noindex, nofollow"/>
  <title>Admin Panel — <?= htmlspecialchars($brand) ?></title>
  <link rel="stylesheet" href="/assets/css/styles.css?v=2.0"/>
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
</head>
<body>
<div class="bg-art" aria-hidden="true"></div>
<div class="container">

  <!-- ── Header ──────────────────────────────────────────────── -->
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:18px 0 14px">
    <div class="brand" style="display:flex;align-items:center;gap:10px;font-weight:800;font-size:18px">
      <span class="logo">✉</span>
      <span><?= htmlspecialchars($brand) ?> Admin</span>
      <span id="modeBadge" style="display:none;padding:3px 10px;border-radius:999px;background:linear-gradient(90deg,#c056ff,#6fe7ff);color:#0a0e1a;font-size:11px;font-weight:700;margin-left:4px">TMA</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a class="btn secondary" href="/blog.php?lang=<?= $lang ?>">View Blog</a>
      <?php if ($webAuth): ?>
        <a class="btn secondary" href="/admin/logout.php">Logout</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Access denied (shown if no auth) ────────────────────── -->
  <div id="screenDenied" style="display:none">
    <div class="panel" style="max-width:480px;margin:40px auto;text-align:center">
      <div style="font-size:48px;margin-bottom:16px">🔒</div>
      <h2>Access Denied</h2>
      <p style="color:var(--muted);margin:10px 0 20px">This panel requires admin access.</p>
      <a class="btn" href="/admin/login.php">Login</a>
    </div>
  </div>

  <!-- ── Main panel ───────────────────────────────────────────── -->
  <div id="screenAdmin" style="display:<?= $webAuth ? 'block' : 'none' ?>">

    <!-- Tabs -->
    <div class="admin-tabs" id="tabs" role="tablist">
      <button class="admin-tab active" data-tab="posts"     role="tab">📝 Posts</button>
      <button class="admin-tab"        data-tab="stats"     role="tab">📊 Stats</button>
      <button class="admin-tab"        data-tab="broadcast" role="tab" id="tabBroadcast" style="display:none">📢 Broadcast</button>
    </div>

    <!-- ── TAB: POSTS ──────────────────────────────────────────── -->
    <div id="tabPosts" class="admin-wrap">
      <div class="kv">

        <!-- Create / Edit form -->
        <div class="panel">
          <h3 id="formTitle" style="margin:0 0 14px">Create Post</h3>
          <form id="postForm" enctype="multipart/form-data" class="admin-form-grid">
            <input type="hidden" id="postId" name="id"/>
            <input class="admin-input" name="title"        id="fTitle"       placeholder="Title *" required/>
            <input class="admin-input" name="tags"         id="fTags"        placeholder="Tags (comma-separated)"/>
            <input class="admin-input" name="published_at" id="fPublishedAt" placeholder="Date YYYY-MM-DD"/>
            <select class="admin-input select" name="status" id="fStatus">
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </select>
            <input class="admin-input" type="file" name="cover" id="fCover" accept="image/*"/>
            <textarea class="admin-input admin-textarea" name="content" id="fContent" placeholder="HTML content *" rows="10" required></textarea>
            <div style="display:flex;gap:8px">
              <button class="btn" type="submit" style="flex:1">💾 Save</button>
              <button class="btn secondary" type="button" id="btnReset">✕ Reset</button>
            </div>
          </form>
          <div id="postMsg" style="display:none" class="admin-msg"></div>
        </div>

        <!-- Post list -->
        <div class="panel">
          <h3 style="margin:0 0 14px">All Posts</h3>
          <div id="postList"><div style="color:var(--muted);font-size:13px">Loading…</div></div>
        </div>
      </div>
    </div>

    <!-- ── TAB: STATS ──────────────────────────────────────────── -->
    <div id="tabStats" class="admin-wrap" style="display:none">
      <div class="admin-stat-grid">
        <div class="admin-stat">
          <div class="admin-stat-val" id="sPosts">—</div>
          <div class="admin-stat-lbl">Total Posts</div>
        </div>
        <div class="admin-stat">
          <div class="admin-stat-val" id="sPublished">—</div>
          <div class="admin-stat-lbl">Published</div>
        </div>
        <div class="admin-stat" id="cardUsers" style="display:none">
          <div class="admin-stat-val" id="sBotUsers">—</div>
          <div class="admin-stat-lbl">Bot Users</div>
        </div>
        <div class="admin-stat" id="cardToday" style="display:none">
          <div class="admin-stat-val" id="sToday">—</div>
          <div class="admin-stat-lbl">Active Today</div>
        </div>
      </div>

      <div class="panel" id="botUserTable" style="display:none">
        <h3 style="margin:0 0 14px">Recent Bot Users</h3>
        <div id="userListWrap"><div style="color:var(--muted);font-size:13px">Loading…</div></div>
      </div>
    </div>

    <!-- ── TAB: BROADCAST (TMA only) ─────────────────────────── -->
    <div id="tabBroadcastContent" class="admin-wrap" style="display:none">
      <div class="panel" style="max-width:620px">
        <h3 style="margin:0 0 14px">📢 Send to All Bot Users</h3>
        <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
          This will send a Telegram message to every user who has interacted with the bot.
        </p>
        <div class="admin-form-grid">
          <textarea class="admin-input admin-textarea" id="bcText" placeholder="Your message (HTML supported)…"></textarea>
          <button class="btn" id="btnBroadcast">📤 Send Broadcast</button>
        </div>
        <div id="bcMsg" style="display:none" class="admin-msg"></div>
      </div>
    </div>

  </div><!-- #screenAdmin -->
</div><!-- .container -->

<div class="notice" id="globalNotice" role="status" aria-live="polite">
  <p class="t" id="noticeTitle">Notification</p>
  <p class="m" id="noticeMsg">…</p>
</div>

<script>
(() => {
"use strict";

// ── Telegram detection ──────────────────────────────────────────
const tg       = window.Telegram?.WebApp;
const initData = tg?.initData || "";
const isTma    = !!initData;

if (isTma) {
  tg.ready();
  tg.expand();
  document.getElementById("modeBadge").style.display = "inline-block";
}

const webAuth = <?= $webAuth ? 'true' : 'false' ?>;

// ── DOM refs ─────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

// ── Notice ───────────────────────────────────────────────────────
let _nt;
function notice(title, msg) {
  $("noticeTitle").textContent = title;
  $("noticeMsg").textContent   = msg;
  $("globalNotice").classList.add("show");
  clearTimeout(_nt);
  _nt = setTimeout(() => $("globalNotice").classList.remove("show"), 4000);
}

function showMsg(el, text, ok) {
  el.textContent  = text;
  el.className    = "admin-msg " + (ok ? "ok" : "err");
  el.style.display = "block";
  setTimeout(() => { el.style.display = "none"; }, 4000);
}

// ── HTML escape ──────────────────────────────────────────────────
function esc(s) {
  return (s || "").replace(/[&<>"']/g, c =>
    ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c])
  );
}

// ── API helper ───────────────────────────────────────────────────
async function api(path, opts = {}) {
  const headers = { ...(opts.headers || {}) };
  if (opts.json) {
    headers["Content-Type"] = "application/json";
    opts.body = JSON.stringify(opts.json);
  }
  const res  = await fetch(path, { credentials: "same-origin", ...opts, headers });
  const data = await res.json().catch(() => ({ error: "Bad JSON" }));
  if (!res.ok) throw data;
  return data;
}

// ── Tab switching ────────────────────────────────────────────────
const tabPanels = {
  posts:     $("tabPosts"),
  stats:     $("tabStats"),
  broadcast: $("tabBroadcastContent")
};

document.getElementById("tabs").addEventListener("click", e => {
  const btn = e.target.closest(".admin-tab");
  if (!btn) return;
  document.querySelectorAll(".admin-tab").forEach(t => t.classList.remove("active"));
  btn.classList.add("active");
  const tab = btn.dataset.tab;
  Object.entries(tabPanels).forEach(([k, el]) => {
    el.style.display = k === tab ? "block" : "none";
  });
  if (tab === "stats") loadStats();
});

// ── AUTH ─────────────────────────────────────────────────────────
async function init() {
  if (webAuth) {
    // Already authenticated via PHP session
    $("screenAdmin").style.display = "block";
    if (isTma) enableTmaFeatures();
    loadPosts();
    return;
  }

  if (isTma) {
    // Verify Telegram identity
    try {
      const data = await api("/api/tg_auth.php?action=auth", {
        json: { initData }
      });
      if (data.admin) {
        $("screenAdmin").style.display = "block";
        enableTmaFeatures();
        loadPosts();
        return;
      }
    } catch (_) {}
  }

  $("screenDenied").style.display = "block";
}

function enableTmaFeatures() {
  $("tabBroadcast").style.display = "block";
  $("cardUsers").style.display    = "block";
  $("cardToday").style.display    = "block";
  $("botUserTable").style.display = "block";
}

// ── POSTS ────────────────────────────────────────────────────────
async function loadPosts() {
  const wrap = $("postList");
  try {
    const d = await api("/api/blog.php?action=list&all=1");
    const posts = d.posts || [];

    // Update stats counters
    $("sPosts").textContent     = posts.length;
    $("sPublished").textContent = posts.filter(p => p.status === "published").length;

    if (posts.length === 0) {
      wrap.innerHTML = `<p style="color:var(--muted);font-size:13px">No posts yet. Create one!</p>`;
      return;
    }

    wrap.innerHTML = posts.map(p => `
      <div class="admin-post-row">
        <div style="flex:1;min-width:0">
          <div class="admin-post-title">${esc(p.title)}</div>
          <div class="admin-post-meta">
            <span class="admin-badge-status ${esc(p.status)}">${esc(p.status)}</span>
            &nbsp;${esc(p.published_at || "")}
          </div>
        </div>
        <div class="admin-actions">
          <button class="btn secondary" data-edit="${esc(p.id)}" style="padding:7px 12px;font-size:12px">Edit</button>
          <button class="btn secondary" data-del="${esc(p.id)}"  style="padding:7px 12px;font-size:12px;color:#ff8a80">Del</button>
        </div>
      </div>`).join("");

    wrap.querySelectorAll("[data-edit]").forEach(b => b.onclick = () => editPost(b.dataset.edit, posts));
    wrap.querySelectorAll("[data-del]").forEach(b => b.onclick  = () => deletePost(b.dataset.del));

  } catch (e) {
    wrap.innerHTML = `<p style="color:#ff8a80;font-size:13px">${esc(e.error || "Failed to load posts")}</p>`;
  }
}

function editPost(id, posts) {
  const p = posts.find(x => x.id === id);
  if (!p) return;
  $("postId").value       = p.id;
  $("fTitle").value       = p.title       || "";
  $("fTags").value        = (p.tags || []).join(", ");
  $("fPublishedAt").value = p.published_at || "";
  $("fStatus").value      = p.status       || "published";
  $("fContent").value     = p.content      || "";
  $("formTitle").textContent = "Edit Post";
  window.scrollTo({ top: 0, behavior: "smooth" });
}

async function deletePost(id) {
  if (!confirm("Delete this post? This cannot be undone.")) return;
  try {
    await api("/api/blog.php?action=delete", { json: { id } });
    notice("Blog", "Post deleted.");
    await loadPosts();
  } catch (e) {
    notice("Error", e.error || "Delete failed");
  }
}

$("postForm").onsubmit = async e => {
  e.preventDefault();
  const btn = $("postForm").querySelector("[type=submit]");
  btn.disabled = true;
  try {
    const fd  = new FormData($("postForm"));
    const res = await fetch("/api/blog.php?action=save", {
      method: "POST", body: fd, credentials: "same-origin"
    });
    const data = await res.json();
    if (!res.ok) throw data;
    showMsg($("postMsg"), "✓ Saved: " + (data.post?.title || ""), true);
    $("postForm").reset();
    $("postId").value = "";
    $("formTitle").textContent = "Create Post";
    await loadPosts();
  } catch (e) {
    showMsg($("postMsg"), "✗ " + (e.error || "Save failed"), false);
  } finally {
    btn.disabled = false;
  }
};

$("btnReset").onclick = () => {
  $("postForm").reset();
  $("postId").value = "";
  $("formTitle").textContent = "Create Post";
  $("postMsg").style.display = "none";
};

// ── STATS ────────────────────────────────────────────────────────
async function loadStats() {
  if (!isTma) return;
  try {
    const data = await api("/api/tg_auth.php?action=stats", { json: { initData } });
    if (data.stats) {
      $("sBotUsers").textContent = data.stats.total_users    ?? "—";
      $("sToday").textContent    = data.stats.active_today   ?? "—";
    }
    // Load user list
    const ud = await api("/api/tg_auth.php?action=users", { json: { initData } });
    const ul = ud.users || [];
    $("userListWrap").innerHTML = ul.length === 0
      ? `<p style="color:var(--muted);font-size:13px">No users yet.</p>`
      : ul.map(u => `
          <div class="admin-post-row">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#c056ff,#6fe7ff);display:grid;place-items:center;font-weight:700;color:#0a0e1a;font-size:14px;flex-shrink:0">
              ${esc((u.first_name || u.username || "?").charAt(0).toUpperCase())}
            </div>
            <div>
              <div style="font-weight:600;font-size:14px">${esc(u.first_name || u.username || "Unknown")}</div>
              <div style="font-size:12px;color:var(--muted)">ID: ${esc(String(u.id))} · ${esc((u.last_seen || "").slice(0, 10))}</div>
            </div>
          </div>`).join("");
  } catch (_) {}
}

// ── BROADCAST ────────────────────────────────────────────────────
$("btnBroadcast")?.addEventListener("click", async () => {
  const text = $("bcText").value.trim();
  if (!text) { notice("Broadcast", "Please enter a message."); return; }
  if (!confirm(`Send to all users?\n\n"${text.slice(0, 80)}${text.length > 80 ? "…" : ""}"`)) return;

  const btn = $("btnBroadcast");
  btn.disabled    = true;
  btn.textContent = "Sending…";
  try {
    const data = await api("/api/tg_auth.php?action=broadcast", {
      json: { initData, message: text }
    });
    showMsg($("bcMsg"), `✓ Sent to ${data.sent ?? 0} users`, true);
    $("bcText").value = "";
    try { tg?.HapticFeedback?.notificationOccurred("success"); } catch (_) {}
  } catch (e) {
    showMsg($("bcMsg"), "✗ " + (e.error || "Broadcast failed"), false);
  } finally {
    btn.disabled    = false;
    btn.textContent = "📤 Send Broadcast";
  }
});

// ── Start ─────────────────────────────────────────────────────────
init();
})();
</script>
</body>
</html>
