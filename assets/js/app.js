
(() => {
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => Array.from(document.querySelectorAll(s));

  const LANG = (window.TEMAIL && window.TEMAIL.lang) || "en";
  const STORE_KEY = "temail_state_v1";

  let T = {};
  let state = {
    provider: "guerrilla",
    email: null,
    gm: { sid: null, seq: 0 },
    mt: { address: null, password: null, token: null },
    readIds: {},
    lastSeenIds: [],
    genTimestamps: []
  };

  function loadState(){
    try{
      const s = JSON.parse(localStorage.getItem(STORE_KEY) || "null");
      if(s && typeof s === "object") state = {...state, ...s};
    }catch(e){}
  }
  function saveState(){
    localStorage.setItem(STORE_KEY, JSON.stringify(state));
  }

  async function loadI18n(){
    const res = await fetch("/assets/i18n.json");
    const all = await res.json();
    T = all[LANG] || all.en || {};
    // chips
    const chips = $("#chips");
    if(chips && T.chips){
      chips.innerHTML = "";
      T.chips.forEach(c=>{
        const el = document.createElement("span");
        el.className="chip";
        el.textContent = c;
        chips.appendChild(el);
      });
    }
    // replace i18n nodes
    $$("[data-i18n]").forEach(el=>{
      const k = el.getAttribute("data-i18n");
      if(T[k]) el.textContent = T[k];
    });
  }

  // Notification (all pages)
  function showNotice(title, msg){
    const n = $("#globalNotice");
    if(!n) return;
    $("#noticeTitle").textContent = title;
    $("#noticeMsg").textContent = msg;
    n.classList.add("show");
    setTimeout(()=>n.classList.remove("show"), 4500);
  }
  function beep(){
    try{
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = "sine";
      o.frequency.value = 880;
      g.gain.value = 0.03;
      o.connect(g); g.connect(ctx.destination);
      o.start();
      setTimeout(()=>{ o.stop(); ctx.close(); }, 160);
    }catch(e){}
  }

  // Rate limit: max 20 generate actions per minute (client-side)
  function canGenerate(){
    const now = Date.now();
    state.genTimestamps = (state.genTimestamps || []).filter(t => now - t < 60000);
    if(state.genTimestamps.length >= 20) return false;
    state.genTimestamps.push(now);
    saveState();
    return true;
  }

  // ===== Guerrilla Mail =====
  const GM_BASE = "/api/guerrilla.php";
  async function gmCall(params){
    const url = GM_BASE + "?" + new URLSearchParams(params).toString();
    const res = await fetch(url, {cache:"no-store"});
    if(!res.ok) throw new Error("GuerrillaMail error");
    return res.json();
  }
  async function gmEnsureAddress(forceNew=false){
    if(!canGenerate()) throw new Error(T.rate_limited || "Rate limited");
    // If we already have an address and not forcing, keep it
    if(state.email && state.provider==="guerrilla" && state.gm.sid && !forceNew) return;
    const d = await gmCall({f:"get_email_address"});
    state.gm.sid = d.sid_token;
    state.email = d.email_addr;
    state.provider = "guerrilla";
    state.gm.seq = 0;
    state.lastSeenIds = [];
    saveState();
  }
  async function gmList(){
    if(!state.gm.sid) return [];
    const d = await gmCall({f:"get_email_list", offset:0, sid_token: state.gm.sid});
    return (d.list || []).map(m=>({
      id: String(m.mail_id),
      from: m.mail_from || "",
      subject: m.mail_subject || "",
      date: m.mail_date || "",
      snippet: m.mail_excerpt || ""
    }));
  }
  async function gmFetch(id){
    const d = await gmCall({f:"fetch_email", email_id: id, sid_token: state.gm.sid});
    // d.mail_body is plain, d.mail_body is html? guerrilla returns mail_body, mail_body_html
    return {
      id,
      from: d.mail_from || "",
      subject: d.mail_subject || "",
      date: d.mail_date || "",
      body_html: d.mail_body_html || null,
      body: d.mail_body || "",
      attachments: (d.attach || []).map(a=>({
        name: a.filename || "attachment",
        // best effort: guerrilla uses "attachment" endpoint, may require parameters:
        url: a.download_url || a.url || null
      }))
    };
  }
  async function gmDelete(id){
    await gmCall({f:"del_email", email_ids: id, sid_token: state.gm.sid});
  }

  // ===== Mail.tm (Gmail-style) =====
  const MT_BASE = "https://api.mail.tm";
  async function mtCall(path, opts={}){
    const headers = opts.headers || {};
    if(state.mt.token) headers["Authorization"] = "Bearer " + state.mt.token;
    if(opts.json){
      headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(opts.json);
    }
    const res = await fetch(MT_BASE + path, {...opts, headers});
    const data = await res.json().catch(()=>null);
    if(!res.ok) throw (data || {error:"Mail.tm error"});
    return data;
  }
  function randStr(n=12){
    const a="abcdefghijklmnopqrstuvwxyz0123456789";
    let s=""; for(let i=0;i<n;i++) s += a[Math.floor(Math.random()*a.length)];
    return s;
  }
  async function mtEnsureAddress(forceNew=false){
    if(!canGenerate()) throw new Error(T.rate_limited || "Rate limited");
    if(state.email && state.provider==="mailtm" && state.mt.token && !forceNew) return;
    // get domain
    const dom = await mtCall("/domains?page=1");
    const domain = (dom["hydra:member"] && dom["hydra:member"][0] && dom["hydra:member"][0].domain) || "mail.tm";
    const local = "user" + randStr(10);
    const address = `${local}@${domain}`;
    const password = randStr(16) + "A1!";
    // create account
    await mtCall("/accounts", {method:"POST", json:{address, password}});
    // token
    const tok = await mtCall("/token", {method:"POST", json:{address, password}});
    state.mt.address = address;
    state.mt.password = password;
    state.mt.token = tok.token;
    state.email = address;
    state.provider = "mailtm";
    state.lastSeenIds = [];
    saveState();
  }
  async function mtList(){
    const d = await mtCall("/messages?page=1");
    const msgs = d["hydra:member"] || [];
    return msgs.map(m=>({
      id: String(m.id),
      from: (m.from && (m.from.address || m.from.name)) || "",
      subject: m.subject || "",
      date: m.createdAt || "",
      snippet: m.intro || ""
    }));
  }
  async function mtFetch(id){
    const m = await mtCall("/messages/" + encodeURIComponent(id));
    const atts = (m.attachments || []).map(a=>({
      name: a.filename || "attachment",
      url: MT_BASE + "/messages/" + encodeURIComponent(id) + "/attachments/" + encodeURIComponent(a.id)
    }));
    return {
      id,
      from: (m.from && (m.from.address || m.from.name)) || "",
      subject: m.subject || "",
      date: m.createdAt || "",
      body_html: m.html ? m.html.join("<br/>") : null,
      body: m.text || "",
      attachments: atts
    };
  }
  async function mtDelete(id){
    await mtCall("/messages/" + encodeURIComponent(id), {method:"DELETE"});
  }

  // ===== UI wiring (only if email UI exists on page) =====
  function wireEmailUI(){
    const providerSel = $("#provider");
    const btnGenerate = $("#btnGenerate");
    const btnNew = $("#btnNew");
    const btnRefresh = $("#btnRefresh");
    const btnRefresh2 = $("#btnRefresh2");
    const emailBox = $("#emailBox");
    const currentEmail = $("#currentEmail");
    const btnCopy = $("#btnCopy");
    const inboxWrap = $("#inboxWrap");
    const mailList = $("#mailList");
    const viewer = $("#viewer");
    const emptyState = $("#emptyState");
    const countBadge = $("#countBadge");

    if(!providerSel) return;

    providerSel.value = state.provider || "guerrilla";

    function setEmail(email){
      if(!email) return;
      emailBox.style.display = "";
      inboxWrap.style.display = "";
      currentEmail.textContent = email;
    }

    function renderList(list){
      countBadge.textContent = String(list.length);
      mailList.innerHTML = "";
      if(list.length === 0){
        emptyState.classList.add("active");
        viewer.classList.remove("active");
        return;
      }
      emptyState.classList.remove("active");
      list.forEach(m=>{
        const isNew = !state.readIds[m.id] && state.lastSeenIds.indexOf(m.id) === -1;
        const item = document.createElement("div");
        item.className="item";
        item.innerHTML = `
          <div style="display:flex;align-items:flex-start;min-width:0;gap:10px">
            <span class="dot ${isNew ? "new":""}"></span>
            <div class="meta">
              <div class="from">${escapeHtml(m.from || "(unknown)")}</div>
              <div class="subj">${escapeHtml(m.subject || "(no subject)")}</div>
              <div class="subj" style="font-size:12px">${escapeHtml(m.snippet || "")}</div>
            </div>
          </div>
          <div class="badge">${escapeHtml((m.date||"").toString().slice(0,19).replace('T',' '))}</div>
        `;
        item.onclick = ()=>openMessage(m.id);
        mailList.appendChild(item);
      });
    }

    function escapeHtml(s){
      return (s||"").replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[m]));
    }

    async function openMessage(id){
      viewer.classList.add("active");
      emptyState.classList.remove("active");
      viewer.innerHTML = `<div class="hdr">Loading...</div>`;
      try{
        const msg = await fetchMessage(id);
        state.readIds[id] = true;
        saveState();
        const bodyHtml = msg.body_html ? msg.body_html : `<pre style="white-space:pre-wrap;margin:0">${escapeHtml(msg.body||"")}</pre>`;
        const atts = (msg.attachments||[]).filter(a=>a.url);
        viewer.innerHTML = `
          <h3>${escapeHtml(msg.subject || "(no subject)")}</h3>
          <div class="hdr">
            <div><strong>From:</strong> ${escapeHtml(msg.from||"")}</div>
            <div><strong>Date:</strong> ${escapeHtml((msg.date||"").toString())}</div>
          </div>
          <div class="actions">
            <button class="btn secondary" id="btnMark">${escapeHtml(T.mark_read||"Mark read")}</button>
            <button class="btn secondary" id="btnDel">${escapeHtml(T.delete||"Delete")}</button>
          </div>
          ${atts.length ? `<div style="margin-top:12px">${atts.map(a=>`<a class="tag" href="${a.url}" target="_blank" rel="noopener">${escapeHtml(T.download||"Download")}: ${escapeHtml(a.name)}</a>`).join("")}</div>` : ""}
          <div class="body">${bodyHtml}</div>
        `;
        $("#btnMark").onclick = ()=>{ showNotice(T.card_title||"Inbox", "Marked as read"); };
        $("#btnDel").onclick = async ()=>{
          if(!confirm("Delete this email?")) return;
          await deleteMessage(id);
          showNotice(T.card_title||"Inbox", "Deleted");
          await refreshInbox(true);
          viewer.classList.remove("active");
        };
      }catch(e){
        viewer.innerHTML = `<div class="hdr">Failed to open message.</div>`;
      }
    }

    async function fetchMessage(id){
      if(state.provider === "guerrilla") return gmFetch(id);
      return mtFetch(id);
    }
    async function deleteMessage(id){
      if(state.provider === "guerrilla") return gmDelete(id);
      return mtDelete(id);
    }

    async function ensureAddress(forceNew=false){
      if(state.provider === "guerrilla") return gmEnsureAddress(forceNew);
      return mtEnsureAddress(forceNew);
    }
    async function listMessages(){
      if(state.provider === "guerrilla") return gmList();
      return mtList();
    }

    // Blink icon + title badge
    let blinkTimer = null;
    function startBlink(){
      btnCopy.classList.add("blink");
      const baseTitle = document.title.replace(/^\(\d+\)\s*/,'');
      let on = false;
      if(blinkTimer) clearInterval(blinkTimer);
      blinkTimer = setInterval(()=>{
        on = !on;
        document.title = on ? "(1) " + baseTitle : baseTitle;
      }, 900);
      setTimeout(()=>stopBlink(), 12000);
    }
    function stopBlink(){
      btnCopy.classList.remove("blink");
      if(blinkTimer) clearInterval(blinkTimer);
      blinkTimer = null;
      document.title = document.title.replace(/^\(\d+\)\s*/,'');
    }

    async function refreshInbox(silent=false){
      try{
        const list = await listMessages();
        // detect new
        const ids = list.map(x=>x.id);
        const newOnes = ids.filter(id => state.lastSeenIds.indexOf(id) === -1);
        if(newOnes.length && !silent){
          showNotice(T.card_title||"Inbox", T.new_mail || "New email received!");
          beep();
          startBlink();
        }
        state.lastSeenIds = ids;
        saveState();
        renderList(list);
      }catch(e){
        if(!silent) showNotice("Error", "Failed to refresh inbox.");
      }
    }

    function setProvider(p){
      state.provider = p;
      saveState();
    }

    providerSel.onchange = async () => {
      setProvider(providerSel.value);
      // keep existing email if provider matches; otherwise show generate hint
      if(state.email && ((state.provider==="guerrilla" && state.gm.sid) || (state.provider==="mailtm" && state.mt.token))){
        setEmail(state.email);
        await refreshInbox(true);
      } else {
        emailBox.style.display = "none";
        inboxWrap.style.display = "none";
      }
    };

    btnGenerate.onclick = async ()=>{
      try{
        await ensureAddress(false);
        setEmail(state.email);
        await refreshInbox(true);
      }catch(e){
        showNotice("Info", (e && e.message) ? e.message : (T.rate_limited||"Rate limited"));
      }
    };

    btnNew.onclick = async ()=>{
      try{
        await ensureAddress(true);
        setEmail(state.email);
        await refreshInbox(true);
      }catch(e){
        showNotice("Info", (e && e.message) ? e.message : (T.rate_limited||"Rate limited"));
      }
    };

    function doRefresh(){ refreshInbox(false); }
    btnRefresh.onclick = doRefresh;
    btnRefresh2 && (btnRefresh2.onclick = doRefresh);

    btnCopy.onclick = async ()=>{
      try{
        await navigator.clipboard.writeText(state.email || "");
        stopBlink();
      }catch(e){}
    };

    // Restore state on load
    if(state.provider) providerSel.value = state.provider;
    if(state.email){
      setEmail(state.email);
      refreshInbox(true);
    }

    // Background refresh every 5 seconds (no data loss)
    setInterval(()=>refreshInbox(true), 5000);
    // Check for new mail every 10 seconds with notification
    setInterval(()=>refreshInbox(false), 10000);
  }

  // Blog UI: list posts on blog.php and latest 20 on index
  async function loadPosts(limit=20, all=false){
    const url = "/api/blog.php?action=list&limit=" + encodeURIComponent(limit) + (all ? "&all=1" : "");
    const res = await fetch(url, {cache:"no-store"});
    const data = await res.json();
    return data.posts || [];
  }
  function renderPostCards(posts, targetSel){
    const wrap = $(targetSel);
    if(!wrap) return;
    wrap.innerHTML = "";
    posts.forEach(p=>{
      const a = document.createElement("a");
      a.className="postcard";
      a.href = "/post.php?id=" + encodeURIComponent(p.id) + "&lang=" + encodeURIComponent(LANG);
      const cover = p.cover || "https://images.unsplash.com/photo-1516387938699-a93567ec168e?auto=format&fit=crop&w=1200&q=70";
      const excerpt = stripHtml(p.content || "").slice(0, 120) + (stripHtml(p.content||"").length>120 ? "…" : "");
      a.innerHTML = `
        <img src="${cover}" alt="${escapeHtml(p.title)}" loading="lazy"/>
        <div class="p">
          <div class="meta">${escapeHtml(p.published_at || "")}</div>
          <h3>${escapeHtml(p.title || "")}</h3>
          <div class="excerpt">${escapeHtml(excerpt)}</div>
          <div>${(p.tags||[]).slice(0,3).map(t=>`<span class="tag">${escapeHtml(t)}</span>`).join("")}</div>
        </div>
      `;
      wrap.appendChild(a);
    });
  }
  function stripHtml(html){
    const d = document.createElement("div");
    d.innerHTML = html || "";
    return (d.textContent || d.innerText || "").trim();
  }
  function escapeHtml(s){
    return (s||"").replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[m]));
  }

  async function wirePostView(){
    const wrap = $("#postView");
    if(!wrap) return;
    const params = new URLSearchParams(location.search);
    const id = params.get("id");
    if(!id){ wrap.innerHTML = `<p style="color:var(--muted)">Missing post id.</p>`; return; }
    const res = await fetch("/api/blog.php?action=get&id=" + encodeURIComponent(id), {cache:"no-store"});
    const data = await res.json();
    if(!res.ok){ wrap.innerHTML = `<p style="color:var(--muted)">Not found.</p>`; return; }
    const p = data.post;
    document.title = (p.title || "Post") + " — " + document.title;
    const cover = p.cover ? `<img src="${p.cover}" alt="${escapeHtml(p.title)}" style="width:100%;border-radius:18px;max-height:360px;object-fit:cover;margin-bottom:14px"/>` : "";
    wrap.innerHTML = `
      ${cover}
      <h1 style="margin:0 0 8px">${escapeHtml(p.title||"")}</h1>
      <div style="color:var(--muted);font-size:13px">${escapeHtml(p.published_at||"")}</div>
      <div style="margin-top:12px">${(p.tags||[]).map(t=>`<span class="tag">${escapeHtml(t)}</span>`).join("")}</div>
      <div style="margin-top:16px;line-height:1.8;color:var(--text)">${p.content||""}</div>
      <div style="margin-top:18px"><a class="btn secondary" href="/blog.php?lang=${encodeURIComponent(LANG)}">← Back to blog</a></div>
    `;
  }

  async function init(){
    loadState();
    await loadI18n();

    // Email UI
    wireEmailUI();

    // Latest posts on homepage
    const latest = $("#latestPosts");
    if(latest){
      const posts = await loadPosts(20,false);
      renderPostCards(posts, "#latestPosts");
    }
    // Blog list
    const blog = $("#blogPosts");
    if(blog){
      const posts = await loadPosts(200,false);
      renderPostCards(posts, "#blogPosts");
    }
    await wirePostView();
  }

  init().catch(()=>{});
})();
