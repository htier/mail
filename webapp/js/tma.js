/**
 * TeMail.pro — Telegram Mini App
 * Main application logic
 */
(() => {
  "use strict";

  // ── Telegram WebApp ──────────────────────────────────────────
  const tg = window.Telegram?.WebApp;
  tg?.ready();
  tg?.expand();

  // ── Config ───────────────────────────────────────────────────
  const STORE_KEY = "temail_tma_v2";
  const GM_PROXY  = "/api/guerrilla.php";   // PHP proxy (same-origin)
  const MT_API    = "https://api.mail.tm";   // direct CORS API
  const REFRESH_INTERVAL = 8000;            // ms

  // ── State ────────────────────────────────────────────────────
  let state = {
    provider:  "guerrilla",
    email:     null,
    genTime:   null,
    gm:        { sid: null },
    mt:        { address: null, password: null, token: null },
    readIds:   {},
    seenIds:   [],
    genTimes:  []
  };

  // ── DOM helpers ──────────────────────────────────────────────
  const $ = id => document.getElementById(id);
  const emailAddress   = $("emailAddress");
  const emailTimer     = $("emailTimer");
  const timerText      = $("timerText");
  const emailActions   = $("emailActions");
  const generateSection = $("generateSection");
  const inboxSection   = $("inboxSection");
  const inboxList      = $("inboxList");
  const countBadge     = $("countBadge");
  const headerUser     = $("headerUser");
  const btnGenerate    = $("btnGenerate");
  const btnCopy        = $("btnCopy");
  const btnNew         = $("btnNew");
  const btnRefresh     = $("btnRefresh");

  // ── State persistence ────────────────────────────────────────
  function loadState() {
    try {
      const s = JSON.parse(localStorage.getItem(STORE_KEY) || "null");
      if (s && typeof s === "object") state = { ...state, ...s };
    } catch (_) {}
  }
  function saveState() {
    try { localStorage.setItem(STORE_KEY, JSON.stringify(state)); } catch (_) {}
  }

  // ── Toast ────────────────────────────────────────────────────
  let _toastTimer;
  function toast(msg, type = "") {
    const el = $("toast");
    el.textContent = msg;
    el.className = "toast show" + (type ? " " + type : "");
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => el.classList.remove("show"), 2800);
  }

  // ── Haptic ───────────────────────────────────────────────────
  function haptic(style = "light") {
    try { tg?.HapticFeedback?.impactOccurred(style); } catch (_) {}
  }

  // ── HTML escape ─────────────────────────────────────────────
  function esc(s) {
    return (s || "").replace(/[&<>"']/g, c => (
      { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]
    ));
  }

  // ── Date formatter ───────────────────────────────────────────
  function fmtDate(d) {
    if (!d) return "";
    const dt = new Date(d);
    if (isNaN(dt)) return String(d).slice(0, 16);
    const now = new Date();
    if (dt.toDateString() === now.toDateString())
      return dt.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    return dt.toLocaleDateString([], { month: "short", day: "numeric" });
  }

  // ── Rate limiter (max 15 generates/min client-side) ──────────
  function canGenerate() {
    const now = Date.now();
    state.genTimes = (state.genTimes || []).filter(t => now - t < 60_000);
    if (state.genTimes.length >= 15) return false;
    state.genTimes.push(now);
    saveState();
    return true;
  }

  // ──────────────────────────────────────────────────────────────
  // GUERRILLA MAIL API
  // ──────────────────────────────────────────────────────────────
  async function gmCall(params) {
    const res = await fetch(GM_PROXY + "?" + new URLSearchParams(params), {
      cache: "no-store"
    });
    if (!res.ok) throw new Error("GuerrillaMail error " + res.status);
    return res.json();
  }

  async function gmGenerate(forceNew) {
    if (!canGenerate()) throw new Error("Too many requests. Wait a moment.");
    if (state.email && state.provider === "guerrilla" && state.gm.sid && !forceNew) return;
    const d = await gmCall({ f: "get_email_address" });
    state.gm.sid  = d.sid_token;
    state.email   = d.email_addr;
    state.provider = "guerrilla";
    state.genTime  = Date.now();
    state.seenIds  = [];
    state.readIds  = {};
    saveState();
  }

  async function gmList() {
    if (!state.gm?.sid) return [];
    const d = await gmCall({ f: "get_email_list", offset: 0, sid_token: state.gm.sid });
    return (d.list || []).map(m => ({
      id:      String(m.mail_id),
      from:    m.mail_from    || "",
      subject: m.mail_subject || "",
      date:    m.mail_date    || "",
      snippet: m.mail_excerpt || ""
    }));
  }

  async function gmFetch(id) {
    const d = await gmCall({ f: "fetch_email", email_id: id, sid_token: state.gm.sid });
    return {
      id,
      from:      d.mail_from         || "",
      subject:   d.mail_subject       || "",
      date:      d.mail_date          || "",
      body_html: d.mail_body_html     || null,
      body:      d.mail_body          || ""
    };
  }

  async function gmDelete(id) {
    await gmCall({ f: "del_email", email_ids: id, sid_token: state.gm.sid });
  }

  // ──────────────────────────────────────────────────────────────
  // MAIL.TM API
  // ──────────────────────────────────────────────────────────────
  async function mtCall(path, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    if (state.mt?.token) headers["Authorization"] = "Bearer " + state.mt.token;
    if (opts.json) {
      headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(opts.json);
    }
    const res = await fetch(MT_API + path, { ...opts, headers });
    const data = await res.json().catch(() => null);
    if (!res.ok) throw data || { error: "Mail.tm error " + res.status };
    return data;
  }

  function rand(n = 10) {
    const a = "abcdefghijklmnopqrstuvwxyz0123456789";
    let s = "";
    for (let i = 0; i < n; i++) s += a[Math.floor(Math.random() * a.length)];
    return s;
  }

  async function mtGenerate(forceNew) {
    if (!canGenerate()) throw new Error("Too many requests. Wait a moment.");
    if (state.email && state.provider === "mailtm" && state.mt?.token && !forceNew) return;
    const dom    = await mtCall("/domains?page=1");
    const domain = dom["hydra:member"]?.[0]?.domain || "mail.tm";
    const address  = `user${rand(10)}@${domain}`;
    const password = rand(14) + "Aa1!";
    await mtCall("/accounts", { method: "POST", json: { address, password } });
    const tok = await mtCall("/token", { method: "POST", json: { address, password } });
    state.mt      = { address, password, token: tok.token };
    state.email   = address;
    state.provider = "mailtm";
    state.genTime  = Date.now();
    state.seenIds  = [];
    state.readIds  = {};
    saveState();
  }

  async function mtList() {
    const d = await mtCall("/messages?page=1");
    return (d["hydra:member"] || []).map(m => ({
      id:      String(m.id),
      from:    m.from?.address || m.from?.name || "",
      subject: m.subject    || "",
      date:    m.createdAt  || "",
      snippet: m.intro      || ""
    }));
  }

  async function mtFetch(id) {
    const m = await mtCall("/messages/" + encodeURIComponent(id));
    return {
      id,
      from:      m.from?.address || m.from?.name || "",
      subject:   m.subject   || "",
      date:      m.createdAt || "",
      body_html: m.html ? m.html.join("<br/>") : null,
      body:      m.text || ""
    };
  }

  async function mtDelete(id) {
    await mtCall("/messages/" + encodeURIComponent(id), { method: "DELETE" });
  }

  // ── Provider-agnostic wrappers ───────────────────────────────
  const generate = (force = false) =>
    state.provider === "guerrilla" ? gmGenerate(force) : mtGenerate(force);
  const listMsgs = () =>
    state.provider === "guerrilla" ? gmList()    : mtList();
  const fetchMsg = id =>
    state.provider === "guerrilla" ? gmFetch(id) : mtFetch(id);
  const deleteMsg = id =>
    state.provider === "guerrilla" ? gmDelete(id): mtDelete(id);

  // ──────────────────────────────────────────────────────────────
  // UI UPDATES
  // ──────────────────────────────────────────────────────────────
  let _timerInterval;

  function startTimer() {
    clearInterval(_timerInterval);
    if (!state.genTime) return;
    emailTimer.style.display = "flex";
    function tick() {
      const s = Math.floor((Date.now() - state.genTime) / 1000);
      if      (s < 60)   timerText.textContent = `Active ${s}s`;
      else if (s < 3600) timerText.textContent = `Active ${Math.floor(s / 60)}m`;
      else               timerText.textContent = `Active ${Math.floor(s / 3600)}h`;
    }
    tick();
    _timerInterval = setInterval(tick, 1000);
  }

  function showEmailUI(email) {
    emailAddress.innerHTML = "";
    emailAddress.textContent = email;
    emailActions.style.display  = "flex";
    generateSection.style.display = "none";
    inboxSection.style.display  = "block";
    updateMainButton();
    startTimer();
  }

  function clearEmailUI() {
    emailAddress.innerHTML = '<span class="email-placeholder">Tap "Generate" to create an address</span>';
    emailActions.style.display  = "none";
    emailTimer.style.display    = "none";
    generateSection.style.display = "block";
    inboxSection.style.display  = "none";
    clearInterval(_timerInterval);
    tg?.MainButton?.hide?.();
  }

  function updateMainButton() {
    if (!tg?.MainButton) return;
    tg.MainButton.setText("📋 Copy Email Address");
    tg.MainButton.show();
    tg.MainButton.onClick(() => btnCopy.click());
  }

  // ──────────────────────────────────────────────────────────────
  // INBOX RENDERING
  // ──────────────────────────────────────────────────────────────
  function renderInbox(list) {
    countBadge.textContent = list.length;
    if (list.length === 0) {
      inboxList.innerHTML = `
        <div class="inbox-empty">
          <div class="inbox-empty-ico">✉️</div>
          <div class="inbox-empty-title">No emails yet</div>
          <div class="inbox-empty-sub">Emails will appear here automatically</div>
        </div>`;
      return;
    }
    inboxList.innerHTML = list.map(m => `
      <div class="mail-item" data-id="${esc(m.id)}">
        <div class="mail-dot ${!state.readIds[m.id] ? "unread" : ""}"></div>
        <div class="mail-body">
          <div class="mail-from">${esc(m.from || "(unknown)")}</div>
          <div class="mail-subject">${esc(m.subject || "(no subject)")}</div>
        </div>
        <div class="mail-date">${esc(fmtDate(m.date))}</div>
      </div>`).join("");

    inboxList.querySelectorAll(".mail-item").forEach(row =>
      row.addEventListener("click", () => openMsg(row.dataset.id))
    );
  }

  async function refreshInbox(silent = true) {
    try {
      const list   = await listMsgs();
      const ids    = list.map(x => x.id);
      const newIds = ids.filter(id => !state.seenIds.includes(id));

      if (newIds.length && !silent) {
        haptic("medium");
        toast(`📬 ${newIds.length} new email${newIds.length > 1 ? "s" : ""}!`, "success");
      }
      state.seenIds = ids;
      saveState();
      renderInbox(list);
    } catch (e) {
      if (!silent) toast("Could not refresh inbox", "error");
    }
  }

  // ──────────────────────────────────────────────────────────────
  // EMAIL VIEWER (bottom sheet)
  // ──────────────────────────────────────────────────────────────
  function openSheet() {
    const overlay = $("viewerOverlay");
    overlay.classList.add("open");
    // Close on backdrop tap
    overlay.addEventListener("click", function handler(e) {
      if (e.target === overlay) {
        closeSheet();
        overlay.removeEventListener("click", handler);
      }
    });
  }

  function closeSheet() {
    $("viewerOverlay").classList.remove("open");
  }

  async function openMsg(id) {
    haptic("light");
    const content = $("viewerContent");
    openSheet();
    content.innerHTML = `
      <div class="p-20 text-center mt-14">
        <div class="spinner"></div>
        <div class="text-hint mt-14" style="font-size:13px">Loading…</div>
      </div>`;

    try {
      const msg = await fetchMsg(id);
      state.readIds[id] = true;
      saveState();

      // Mark dot as read
      const dot = inboxList.querySelector(`.mail-item[data-id="${CSS.escape(id)}"] .mail-dot`);
      dot?.classList.remove("unread");

      const bodyHtml = msg.body_html
        ? `<div class="viewer-body">${msg.body_html}</div>`
        : `<div class="viewer-body"><pre>${esc(msg.body || "")}</pre></div>`;

      content.innerHTML = `
        <div class="viewer-head">
          <div class="viewer-subject">${esc(msg.subject || "(no subject)")}</div>
          <div class="viewer-meta">
            <span><strong>From:</strong> ${esc(msg.from || "")}</span>
            <span><strong>Date:</strong> ${esc(fmtDate(msg.date))}</span>
          </div>
        </div>
        <div class="viewer-actions">
          <button class="action-btn" id="btnClose">✕ Close</button>
          <button class="action-btn" id="btnDelMsg">🗑 Delete</button>
        </div>
        ${bodyHtml}`;

      $("btnClose").addEventListener("click", closeSheet);
      $("btnDelMsg").addEventListener("click", async () => {
        if (!confirm("Delete this email?")) return;
        try {
          await deleteMsg(id);
          closeSheet();
          await refreshInbox(false);
          toast("Deleted", "success");
          haptic("light");
        } catch (_) {
          toast("Could not delete", "error");
        }
      });
    } catch (_) {
      content.innerHTML = `
        <div class="p-20 text-center text-hint" style="font-size:14px">
          Failed to load email.
          <br/><br/>
          <button class="action-btn" onclick="document.getElementById('viewerOverlay').classList.remove('open')">Close</button>
        </div>`;
    }
  }

  // ──────────────────────────────────────────────────────────────
  // EVENT HANDLERS
  // ──────────────────────────────────────────────────────────────

  // Provider tabs
  document.querySelectorAll(".provider-tab").forEach(tab => {
    tab.addEventListener("click", () => {
      if (tab.dataset.p === state.provider) return;
      haptic("light");
      document.querySelectorAll(".provider-tab")
        .forEach(t => t.classList.remove("active"));
      tab.classList.add("active");
      // Reset to fresh state for new provider
      state.provider = tab.dataset.p;
      state.email    = null;
      state.genTime  = null;
      state.seenIds  = [];
      state.readIds  = {};
      saveState();
      clearEmailUI();
    });
  });

  // Generate
  btnGenerate.addEventListener("click", async () => {
    btnGenerate.disabled    = true;
    btnGenerate.textContent = "Generating…";
    haptic("light");
    try {
      await generate(false);
      showEmailUI(state.email);
      await refreshInbox(true);
      toast("✅ Email ready!", "success");
      haptic("medium");
    } catch (e) {
      toast(e?.message || "Error generating email", "error");
    } finally {
      btnGenerate.disabled    = false;
      btnGenerate.textContent = "✨ Generate Email";
    }
  });

  // New email
  btnNew.addEventListener("click", async () => {
    if (!confirm("Generate a new address?\nYour current inbox will be cleared.")) return;
    haptic("light");
    btnNew.disabled = true;
    try {
      await generate(true);
      showEmailUI(state.email);
      await refreshInbox(true);
      toast("✅ New email ready!", "success");
      haptic("medium");
    } catch (e) {
      toast(e?.message || "Error", "error");
    } finally {
      btnNew.disabled = false;
    }
  });

  // Copy
  btnCopy.addEventListener("click", async () => {
    if (!state.email) return;
    haptic("light");
    try {
      await navigator.clipboard.writeText(state.email);
    } catch (_) {
      // Fallback for older browsers
      const inp = Object.assign(document.createElement("input"), { value: state.email });
      document.body.appendChild(inp);
      inp.select();
      document.execCommand("copy");
      inp.remove();
    }
    toast("📋 Copied to clipboard!", "success");
    haptic("medium");
  });

  // Refresh inbox
  btnRefresh.addEventListener("click", async () => {
    btnRefresh.classList.add("spinning");
    haptic("light");
    await refreshInbox(false);
    setTimeout(() => btnRefresh.classList.remove("spinning"), 600);
  });

  // ──────────────────────────────────────────────────────────────
  // AUTO-REFRESH
  // ──────────────────────────────────────────────────────────────
  let _refreshInterval;
  function startAutoRefresh() {
    clearInterval(_refreshInterval);
    _refreshInterval = setInterval(() => {
      if (state.email) refreshInbox(false);
    }, REFRESH_INTERVAL);
  }

  // ──────────────────────────────────────────────────────────────
  // INIT
  // ──────────────────────────────────────────────────────────────
  function init() {
    loadState();

    // Show Telegram username in header
    const user = tg?.initDataUnsafe?.user;
    if (user?.first_name) {
      headerUser.textContent = `Hi, ${user.first_name}`;
    }

    // Restore active provider tab
    document.querySelectorAll(".provider-tab").forEach(t =>
      t.classList.toggle("active", t.dataset.p === state.provider)
    );

    // Restore email state
    if (state.email) {
      showEmailUI(state.email);
      refreshInbox(true);
    }

    startAutoRefresh();
  }

  init();
})();
