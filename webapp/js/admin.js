/**
 * TeMail.pro — Admin Panel JS
 * Verifies Telegram identity, loads stats and users, handles broadcast
 */
(() => {
  "use strict";

  const tg = window.Telegram?.WebApp;
  tg?.ready();
  tg?.expand();

  const AUTH_API = "/api/tg_auth.php";

  // ── DOM ──────────────────────────────────────────────────────
  const $ = id => document.getElementById(id);
  const screenLoading = $("screenLoading");
  const screenDenied  = $("screenDenied");
  const screenAdmin   = $("screenAdmin");

  // ── Toast ────────────────────────────────────────────────────
  let _tt;
  function toast(msg, type = "") {
    const el = $("toast");
    el.textContent = msg;
    el.className = "toast show" + (type ? " " + type : "");
    clearTimeout(_tt);
    _tt = setTimeout(() => el.classList.remove("show"), 3200);
  }

  // ── HTML escape ──────────────────────────────────────────────
  function esc(s) {
    return (s || "").replace(/[&<>"']/g, c => (
      { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]
    ));
  }

  // ── API call helper ──────────────────────────────────────────
  async function api(action, extra = {}) {
    const res = await fetch(`${AUTH_API}?action=${action}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        initData: tg?.initData || "",
        ...extra
      })
    });
    return res.json();
  }

  // ── Auth ─────────────────────────────────────────────────────
  async function verifyAdmin() {
    try {
      const data = await api("auth");
      return data.admin === true;
    } catch (_) {
      return false;
    }
  }

  // ── Stats ────────────────────────────────────────────────────
  async function loadStats() {
    try {
      const data = await api("stats");
      if (data.stats) {
        $("statUsers").textContent = data.stats.total_users    ?? "—";
        $("statToday").textContent = data.stats.active_today   ?? "—";
        $("statGm").textContent    = data.stats.guerrilla_sessions ?? "—";
        $("statMt").textContent    = data.stats.mailtm_sessions   ?? "—";
      }
    } catch (_) {
      ["statUsers","statToday","statGm","statMt"].forEach(id => $[id] && ($[id].textContent = "err"));
    }
  }

  // ── Users ────────────────────────────────────────────────────
  async function loadUsers() {
    const wrap = $("userList");
    try {
      const data  = await api("users");
      const users = data.users || [];

      if (users.length === 0) {
        wrap.innerHTML = `<div class="p-20 text-center text-hint" style="font-size:14px">No users yet</div>`;
        return;
      }

      wrap.innerHTML = users.map(u => {
        const name   = u.first_name || u.username || "Unknown";
        const letter = name.charAt(0).toUpperCase();
        return `
          <div class="user-row">
            <div class="user-avatar">${esc(letter)}</div>
            <div>
              <div class="user-name">${esc(name)}</div>
              <div class="user-meta">
                ID: ${esc(String(u.id))}
                ${u.last_seen ? " · " + esc(u.last_seen.slice(0, 10)) : ""}
              </div>
            </div>
          </div>`;
      }).join("");
    } catch (_) {
      wrap.innerHTML = `<div class="p-20 text-center text-hint" style="font-size:14px">Failed to load users</div>`;
    }
  }

  // ── Broadcast ────────────────────────────────────────────────
  $("btnBroadcast")?.addEventListener("click", async () => {
    const text = $("broadcastText").value.trim();
    if (!text) { toast("Please enter a message first", "error"); return; }

    const preview = text.length > 60 ? text.slice(0, 60) + "…" : text;
    if (!confirm(`Send to all users?\n\n"${preview}"`)) return;

    const btn = $("btnBroadcast");
    btn.disabled    = true;
    btn.textContent = "Sending…";

    try {
      const data = await api("broadcast", { message: text });
      if (data.ok) {
        toast(`✅ Sent to ${data.sent ?? 0} user${data.sent !== 1 ? "s" : ""}`, "success");
        $("broadcastText").value = "";
        try { tg?.HapticFeedback?.notificationOccurred("success"); } catch (_) {}
      } else {
        toast(data.error || "Broadcast failed", "error");
      }
    } catch (_) {
      toast("Network error", "error");
    } finally {
      btn.disabled    = false;
      btn.textContent = "Send to All Users";
    }
  });

  // ── Init ─────────────────────────────────────────────────────
  async function init() {
    const isAdmin = await verifyAdmin();
    screenLoading.style.display = "none";

    if (!isAdmin) {
      screenDenied.style.display = "block";
      return;
    }

    screenAdmin.style.display = "block";
    await Promise.all([loadStats(), loadUsers()]);
  }

  init();
})();
