/**
 * TeMail.pro — Telegram Mini App v2
 */
(() => {
  "use strict";

  const tg = window.Telegram?.WebApp;
  tg?.ready();
  tg?.expand();

  // ── Config ──────────────────────────────────────────────────
  const STORE_KEY   = "temail_tma_v2";
  const PREMIUM_KEY = "temail_premium";
  const LANG_KEY    = "temail_lang";
  const GM_PROXY    = "/api/guerrilla.php";
  const MT_API      = "https://api.mail.tm";
  const REFRESH_MS  = 8000;

  // ── i18n ────────────────────────────────────────────────────
  const STRINGS = {
    en: {
      copy: "Copy", new_email: "New", delete: "Delete",
      inbox: "Inbox", no_emails: "No emails yet",
      no_emails_sub: "Emails appear here automatically",
      generating: "Generating…", email_ready: "✅ Email ready!",
      copied: "📋 Copied!", refreshed: "✅ Inbox refreshed",
      delete_confirm: "Delete this email address?",
      msg_delete_confirm: "Delete this message?",
      deleted: "Deleted", error: "Error",
      premium_title: "Premium — No Ads",
      premium_sub_short: "200 Stars/month via Telegram bot",
      premium_active: "Premium Active",
      activate: "Activate", enter_code: "XXXX-XXXX-XXXX-XXXX",
      premium_activated: "⭐ Premium activated! Ads hidden.",
      premium_invalid: "Invalid code.",
      premium_expired_code: "Code has expired.",
      premium_error: "Could not activate.",
    },
    ru: {
      copy: "Копировать", new_email: "Новый", delete: "Удалить",
      inbox: "Входящие", no_emails: "Писем пока нет",
      no_emails_sub: "Письма появятся здесь автоматически",
      generating: "Создаётся…", email_ready: "✅ Email готов!",
      copied: "📋 Скопировано!", refreshed: "✅ Обновлено",
      delete_confirm: "Удалить этот email-адрес?",
      msg_delete_confirm: "Удалить это письмо?",
      deleted: "Удалено", error: "Ошибка",
      premium_title: "Премиум — без рекламы",
      premium_sub_short: "200 звёзд/месяц в боте",
      premium_active: "Премиум активен",
      activate: "Активировать", enter_code: "XXXX-XXXX-XXXX-XXXX",
      premium_activated: "⭐ Премиум активирован!",
      premium_invalid: "Неверный код.",
      premium_expired_code: "Код просрочен.",
      premium_error: "Не удалось активировать.",
    }
  };

  // Detect language
  function detectLang() {
    const saved = localStorage.getItem(LANG_KEY);
    if (saved && ["en","ru"].includes(saved)) return saved;
    const tgLang = tg?.initDataUnsafe?.user?.language_code || "";
    if (tgLang.startsWith("ru")) return "ru";
    const nav = (navigator.language || "").toLowerCase();
    return nav.startsWith("ru") ? "ru" : "en";
  }
  let LANG = detectLang();
  const T  = STRINGS[LANG] || STRINGS.en;

  function applyI18n() {
    document.querySelectorAll("[data-i]").forEach(el => {
      const k = el.getAttribute("data-i");
      if (T[k] !== undefined) el.textContent = T[k];
    });
    document.querySelectorAll("[data-i-ph]").forEach(el => {
      const k = el.getAttribute("data-i-ph");
      if (T[k] !== undefined) el.placeholder = T[k];
    });
    document.documentElement.lang = LANG;
    const lb = $("langBtn");
    if (lb) lb.textContent = LANG.toUpperCase();
  }

  // ── State ────────────────────────────────────────────────────
  let state = {
    provider: "mailtm",
    email: null, genTime: null,
    gm: { sid: null },
    mt: { address: null, password: null, token: null },
    readIds: {}, seenIds: [], genTimes: []
  };

  const $ = id => document.getElementById(id);
  const emailAddress  = $("emailAddress");
  const emailActions  = $("emailActions");
  const emailTimer    = $("emailTimer");
  const timerText     = $("timerText");
  const inboxSection  = $("inboxSection");
  const inboxList     = $("inboxList");
  const countBadge    = $("countBadge");
  const headerUser    = $("headerUser");
  const btnCopy       = $("btnCopy");
  const btnNew        = $("btnNew");
  const btnDelete     = $("btnDelete");
  const btnRefresh    = $("btnRefresh");
  const langBtn       = $("langBtn");

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
  let _tt;
  function toast(msg, type = "") {
    const el = $("toast");
    el.textContent = msg;
    el.className = "toast show" + (type ? " " + type : "");
    clearTimeout(_tt);
    _tt = setTimeout(() => el.classList.remove("show"), 2800);
  }
  function haptic(s = "light") {
    try { tg?.HapticFeedback?.impactOccurred(s); } catch (_) {}
  }
  function esc(s) {
    return (s || "").replace(/[&<>"']/g, c => ({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
  }
  function fmtDate(d) {
    if (!d) return "";
    const dt = new Date(d);
    if (isNaN(dt)) return String(d).slice(0, 16);
    const now = new Date();
    if (dt.toDateString() === now.toDateString())
      return dt.toLocaleTimeString([], { hour:"2-digit", minute:"2-digit" });
    return dt.toLocaleDateString([], { month:"short", day:"numeric" });
  }
  function canGenerate() {
    const now = Date.now();
    state.genTimes = (state.genTimes || []).filter(t => now - t < 60000);
    if (state.genTimes.length >= 15) return false;
    state.genTimes.push(now); saveState(); return true;
  }

  // ── Premium ──────────────────────────────────────────────────
  function getPremium() {
    try {
      const p = JSON.parse(localStorage.getItem(PREMIUM_KEY) || "null");
      if (!p) return null;
      if (p.expires_at && Date.now() / 1000 > p.expires_at) {
        localStorage.removeItem(PREMIUM_KEY); return null;
      }
      return p;
    } catch (_) { return null; }
  }
  function setPremium(data) { localStorage.setItem(PREMIUM_KEY, JSON.stringify(data)); }

  function applyPremiumUI() {
    const isPrem = !!getPremium();
    const banner = $("premiumBanner");
    const active = $("premiumActive");
    const adSlot = $("adSlot");
    if (isPrem) {
      if (banner) banner.style.display = "none";
      if (active) {
        active.style.display = "";
        const p = getPremium();
        const exp = $("premiumExpiry");
        if (exp && p && p.expires_at)
          exp.textContent = " · " + new Date(p.expires_at * 1000).toLocaleDateString(LANG === "ru" ? "ru-RU" : "en-US");
      }
      if (adSlot) adSlot.style.display = "none";
    } else {
      if (banner) banner.style.display = "";
      if (active) active.style.display = "none";
      if (adSlot) adSlot.style.display = "";
    }
  }

  async function redeemCode(code) {
    try {
      const res = await fetch("/api/premium.php", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({action:"redeem", code: code.trim().toUpperCase().replace(/\s+/g,"")})
      });
      const data = await res.json();
      if (data.ok) {
        setPremium({code, expires_at: data.expires_at, user_id: data.user_id});
        applyPremiumUI();
        toast(T.premium_activated, "success"); haptic("medium");
        return true;
      }
      const errs = {invalid_code: T.premium_invalid, expired_code: T.premium_expired_code};
      toast(errs[data.error] || T.premium_error, "error");
      return false;
    } catch (_) {
      toast(T.premium_error, "error"); return false;
    }
  }

  // ── Guerrilla Mail ───────────────────────────────────────────
  async function gmCall(p) {
    const res = await fetch(GM_PROXY + "?" + new URLSearchParams(p), {cache:"no-store"});
    if (!res.ok) throw new Error("GM error");
    return res.json();
  }
  async function gmGenerate(force) {
    if (!canGenerate()) throw new Error("Rate limited");
    if (state.email && state.provider === "guerrilla" && state.gm.sid && !force) return;
    const d = await gmCall({f:"get_email_address"});
    state.gm.sid = d.sid_token; state.email = d.email_addr;
    state.provider = "guerrilla"; state.genTime = Date.now();
    state.seenIds = []; state.readIds = {}; saveState();
  }
  async function gmList() {
    if (!state.gm?.sid) return [];
    const d = await gmCall({f:"get_email_list", offset:0, sid_token:state.gm.sid});
    return (d.list||[]).map(m=>({id:String(m.mail_id),from:m.mail_from||"",subject:m.mail_subject||"",date:m.mail_date||"",snippet:m.mail_excerpt||""}));
  }
  async function gmFetch(id) {
    const d = await gmCall({f:"fetch_email", email_id:id, sid_token:state.gm.sid});
    return {id, from:d.mail_from||"", subject:d.mail_subject||"", date:d.mail_date||"", body_html:d.mail_body_html||null, body:d.mail_body||""};
  }
  async function gmDelete(id) { await gmCall({f:"del_email", email_ids:id, sid_token:state.gm.sid}); }

  // ── Mail.tm ──────────────────────────────────────────────────
  async function mtCall(path, opts = {}) {
    const headers = {...(opts.headers||{})};
    if (state.mt?.token) headers["Authorization"] = "Bearer " + state.mt.token;
    if (opts.json) { headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(opts.json); }
    const res = await fetch(MT_API + path, {...opts, headers});
    const data = await res.json().catch(()=>null);
    if (!res.ok) throw data || {error:"Mail.tm error"};
    return data;
  }
  function rand(n=10) {
    const a="abcdefghijklmnopqrstuvwxyz0123456789";
    let s=""; for(let i=0;i<n;i++) s+=a[Math.floor(Math.random()*a.length)]; return s;
  }
  async function mtGenerate(force) {
    if (!canGenerate()) throw new Error("Rate limited");
    // Try re-auth first
    if (!force && state.mt?.address && state.mt?.password && state.provider === "mailtm") {
      if (state.mt.token) return;
      try {
        const tok = await mtCall("/token", {method:"POST", json:{address:state.mt.address, password:state.mt.password}});
        if (tok?.token) { state.mt.token = tok.token; state.email = state.mt.address; saveState(); return; }
      } catch(_) {}
    }
    if (state.email && state.provider === "mailtm" && state.mt?.token && !force) return;
    const dom = await mtCall("/domains?page=1");
    const domain = dom["hydra:member"]?.[0]?.domain || "mail.tm";
    const address = `user${rand(10)}@${domain}`;
    const password = rand(14) + "Aa1!";
    await mtCall("/accounts", {method:"POST", json:{address, password}});
    const tok = await mtCall("/token", {method:"POST", json:{address, password}});
    state.mt = {address, password, token:tok.token};
    state.email = address; state.provider = "mailtm";
    state.genTime = Date.now(); state.seenIds = []; state.readIds = {}; saveState();
  }
  async function mtList() {
    const d = await mtCall("/messages?page=1");
    return (d["hydra:member"]||[]).map(m=>({id:String(m.id),from:m.from?.address||m.from?.name||"",subject:m.subject||"",date:m.createdAt||"",snippet:m.intro||""}));
  }
  async function mtFetch(id) {
    const m = await mtCall("/messages/"+encodeURIComponent(id));
    return {id, from:m.from?.address||m.from?.name||"", subject:m.subject||"", date:m.createdAt||"", body_html:m.html?m.html.join("<br/>"):null, body:m.text||""};
  }
  async function mtDelete(id) { await mtCall("/messages/"+encodeURIComponent(id),{method:"DELETE"}); }

  const generate = (f=false) => state.provider==="guerrilla" ? gmGenerate(f) : mtGenerate(f);
  const listMsgs = ()  => state.provider==="guerrilla" ? gmList()    : mtList();
  const fetchMsg = id  => state.provider==="guerrilla" ? gmFetch(id) : mtFetch(id);
  const deleteMsg= id  => state.provider==="guerrilla" ? gmDelete(id): mtDelete(id);

  // ── UI helpers ───────────────────────────────────────────────
  let _timerInterval;
  function startTimer() {
    clearInterval(_timerInterval);
    if (!state.genTime) return;
    if (emailTimer) emailTimer.style.display = "flex";
    function tick() {
      const s = Math.floor((Date.now() - state.genTime) / 1000);
      if (timerText) timerText.textContent =
        s < 60 ? `Active ${s}s` : s < 3600 ? `Active ${Math.floor(s/60)}m` : `Active ${Math.floor(s/3600)}h`;
    }
    tick(); _timerInterval = setInterval(tick, 1000);
  }
  function showEmailUI(email) {
    if (emailAddress) { emailAddress.innerHTML = ""; emailAddress.textContent = email; }
    if (emailActions)  emailActions.style.display = "flex";
    if (inboxSection)  inboxSection.style.display = "block";
    updateMainButton(); startTimer();
  }
  function hideEmailUI() {
    if (emailAddress) emailAddress.innerHTML = `<span class="email-placeholder">${T.generating || "Generating…"}</span>`;
    if (emailActions) emailActions.style.display = "none";
    if (emailTimer)   emailTimer.style.display = "none";
    if (inboxSection) inboxSection.style.display = "none";
    clearInterval(_timerInterval);
    tg?.MainButton?.hide?.();
  }
  function updateMainButton() {
    if (!tg?.MainButton) return;
    tg.MainButton.setText("📋 Copy Email");
    tg.MainButton.show();
    tg.MainButton.onClick(() => btnCopy?.click());
  }

  // ── Inbox rendering ─────────────────────────────────────────
  function renderInbox(list) {
    if (countBadge) countBadge.textContent = list.length;
    if (!inboxList) return;
    if (list.length === 0) {
      inboxList.innerHTML = `
        <div class="inbox-empty">
          <div class="inbox-empty-ico">✉️</div>
          <div class="inbox-empty-title">${T.no_emails}</div>
          <div class="inbox-empty-sub">${T.no_emails_sub}</div>
        </div>`; return;
    }
    inboxList.innerHTML = list.map(m => `
      <div class="mail-item" data-id="${esc(m.id)}">
        <div class="mail-dot ${!state.readIds[m.id]?"unread":""}"></div>
        <div class="mail-body">
          <div class="mail-from">${esc(m.from||(LANG==="ru"?"(неизвестно)":"(unknown)"))}</div>
          <div class="mail-subject">${esc(m.subject||(LANG==="ru"?"(без темы)":"(no subject)"))}</div>
        </div>
        <div class="mail-date">${esc(fmtDate(m.date))}</div>
      </div>`).join("");
    inboxList.querySelectorAll(".mail-item").forEach(row =>
      row.addEventListener("click", () => openMsg(row.dataset.id))
    );
  }
  async function refreshInbox(silent=true) {
    if (!state.email) return;
    try {
      const list = await listMsgs();
      const ids = list.map(x=>x.id);
      const newIds = ids.filter(id=>!state.seenIds.includes(id));
      if (newIds.length && !silent) {
        haptic("medium");
        toast(`📬 ${newIds.length} new email${newIds.length>1?"s":""}!`, "success");
      }
      state.seenIds = ids; saveState(); renderInbox(list);
    } catch(e) { if (!silent) toast(T.error, "error"); }
  }

  // ── Viewer ───────────────────────────────────────────────────
  function openSheet() {
    const overlay = $("viewerOverlay");
    if (!overlay) return;
    overlay.classList.add("open");
    overlay.addEventListener("click", function h(e) {
      if (e.target === overlay) { closeSheet(); overlay.removeEventListener("click",h); }
    });
  }
  function closeSheet() { $("viewerOverlay")?.classList.remove("open"); }
  async function openMsg(id) {
    haptic("light");
    const content = $("viewerContent");
    if (!content) return;
    openSheet();
    content.innerHTML = `<div style="text-align:center;padding:30px 20px;color:#5a6a8a">⏳ Loading…</div>`;
    try {
      const msg = await fetchMsg(id);
      state.readIds[id] = true; saveState();
      const dot = inboxList?.querySelector(`.mail-item[data-id="${CSS.escape(id)}"] .mail-dot`);
      dot?.classList.remove("unread");
      const bodyHtml = msg.body_html
        ? `<div class="viewer-body">${msg.body_html}</div>`
        : `<div class="viewer-body"><pre style="white-space:pre-wrap;font-family:inherit;margin:0">${esc(msg.body||"")}</pre></div>`;
      content.innerHTML = `
        <div class="viewer-head">
          <div class="viewer-subject">${esc(msg.subject||(LANG==="ru"?"(без темы)":"(no subject)"))}</div>
          <div class="viewer-meta">
            <span><strong>From:</strong> ${esc(msg.from||"")}</span>
            <span><strong>Date:</strong> ${esc(fmtDate(msg.date))}</span>
          </div>
        </div>
        <div class="viewer-actions">
          <button class="action-btn" id="btnClose">✕ Close</button>
          <button class="action-btn danger" id="btnDelMsg">🗑 ${T.delete}</button>
        </div>
        ${bodyHtml}`;
      $("btnClose").addEventListener("click", closeSheet);
      $("btnDelMsg").addEventListener("click", async () => {
        if (!confirm(T.msg_delete_confirm)) return;
        try { await deleteMsg(id); closeSheet(); await refreshInbox(false); toast(T.deleted,"success"); haptic("light"); }
        catch(_) { toast(T.error,"error"); }
      });
    } catch(_) {
      content.innerHTML = `<div style="text-align:center;padding:30px;color:#5a6a8a">Failed to load.<br/><br/><button class="action-btn" onclick="document.getElementById('viewerOverlay').classList.remove('open')">Close</button></div>`;
    }
  }

  // ── Event handlers ───────────────────────────────────────────
  // Provider tabs
  document.querySelectorAll(".provider-tab").forEach(tab => {
    tab.addEventListener("click", () => {
      if (tab.dataset.p === state.provider) return;
      haptic("light");
      document.querySelectorAll(".provider-tab").forEach(t=>t.classList.remove("active"));
      tab.classList.add("active");
      state.provider = tab.dataset.p;
      state.email = null; state.genTime = null;
      state.seenIds = []; state.readIds = {};
      saveState(); hideEmailUI();
    });
  });

  // Copy
  btnCopy?.addEventListener("click", async () => {
    if (!state.email) return;
    haptic("light");
    try { await navigator.clipboard.writeText(state.email); }
    catch(_) {
      const inp = Object.assign(document.createElement("input"),{value:state.email});
      document.body.appendChild(inp); inp.select(); document.execCommand("copy"); inp.remove();
    }
    toast(T.copied, "success"); haptic("medium");
  });

  // New email
  btnNew?.addEventListener("click", async () => {
    if (!confirm(T.delete_confirm)) return;
    haptic("light"); btnNew.disabled = true;
    try {
      await generate(true); showEmailUI(state.email);
      await refreshInbox(true); toast(T.email_ready,"success"); haptic("medium");
    } catch(e) { toast(e?.message||T.error,"error"); }
    finally { btnNew.disabled = false; }
  });

  // Delete email address
  btnDelete?.addEventListener("click", () => {
    if (!confirm(T.delete_confirm)) return;
    haptic("light");
    state.email = null; state.gm = {sid:null};
    state.mt = {address:null,password:null,token:null};
    state.readIds = {}; state.seenIds = []; saveState();
    hideEmailUI();
    toast("🗑 " + (LANG==="ru" ? "Email удалён" : "Email deleted"), "success");
  });

  // Refresh
  btnRefresh?.addEventListener("click", async () => {
    btnRefresh.classList.add("spinning"); haptic("light");
    await refreshInbox(false);
    setTimeout(()=>btnRefresh.classList.remove("spinning"),600);
  });

  // Language switch
  langBtn?.addEventListener("click", () => {
    LANG = LANG === "en" ? "ru" : "en";
    localStorage.setItem(LANG_KEY, LANG);
    location.reload();
  });

  // Premium activation
  $("btnActivatePremium")?.addEventListener("click", async () => {
    const inp = $("premiumCodeInput");
    if (!inp?.value.trim()) { toast(T.enter_code || "Enter code","error"); return; }
    const btn = $("btnActivatePremium");
    btn.disabled = true; btn.textContent = "…";
    const ok = await redeemCode(inp.value);
    if (!ok) inp.value = "";
    btn.disabled = false; btn.textContent = T.activate;
  });
  $("premiumCodeInput")?.addEventListener("keydown", e => {
    if (e.key === "Enter") $("btnActivatePremium")?.click();
  });

  // ── Auto-refresh ─────────────────────────────────────────────
  let _ri;
  function startAutoRefresh() {
    clearInterval(_ri);
    _ri = setInterval(() => { if (state.email) refreshInbox(false); }, REFRESH_MS);
  }

  // ── Init ─────────────────────────────────────────────────────
  async function init() {
    loadState();
    applyI18n();
    applyPremiumUI();

    // Show user
    const user = tg?.initDataUnsafe?.user;
    if (user?.first_name && headerUser) headerUser.textContent = `Hi, ${user.first_name}`;

    // Restore provider tab
    document.querySelectorAll(".provider-tab").forEach(t =>
      t.classList.toggle("active", t.dataset.p === state.provider)
    );

    if (state.email) {
      // Restore existing email
      showEmailUI(state.email);
      // Fix Mail.tm token if missing
      if (state.provider === "mailtm" && !state.mt?.token && state.mt?.address) {
        mtGenerate(false).then(()=>refreshInbox(true)).catch(()=>refreshInbox(true));
      } else {
        refreshInbox(true);
      }
    } else {
      // Auto-generate on first open
      try {
        await generate(false);
        showEmailUI(state.email);
        await refreshInbox(true);
        toast(T.email_ready, "success");
      } catch(_) {}
    }

    startAutoRefresh();
  }

  init();
})();
