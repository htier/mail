/* TeMail.pro — app.js v2 */
(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  /* ===== Language ===== */
  const LANG_KEY = 'temail_lang';
  function detectLang() {
    const saved = localStorage.getItem(LANG_KEY);
    if (saved && ['en', 'ru'].includes(saved)) return saved;
    const nav = (navigator.language || navigator.userLanguage || 'en').toLowerCase();
    return nav.startsWith('ru') ? 'ru' : 'en';
  }
  // window.TEMAIL.lang set by PHP from ?lang= param; 'auto' means JS should detect
  const LANG = (window.TEMAIL && window.TEMAIL.lang && window.TEMAIL.lang !== 'auto')
    ? window.TEMAIL.lang
    : detectLang();

  /* ===== State ===== */
  const STORE_KEY = 'temail_state_v1';
  const PREMIUM_KEY = 'temail_premium';

  let T = {};
  let state = {
    provider: 'mailtm',
    email: null,
    gm: { sid: null, seq: 0 },
    mt: { address: null, password: null, token: null },
    readIds: {},
    lastSeenIds: [],
    genTimestamps: []
  };

  function loadState() {
    try {
      const s = JSON.parse(localStorage.getItem(STORE_KEY) || 'null');
      if (s && typeof s === 'object') state = { ...state, ...s };
    } catch (e) {}
  }
  function saveState() {
    localStorage.setItem(STORE_KEY, JSON.stringify(state));
  }

  /* ===== i18n ===== */
  async function loadI18n() {
    try {
      const res = await fetch('/assets/i18n.json');
      const all = await res.json();
      T = all[LANG] || all.en || {};
    } catch (e) { T = {}; }

    // Chips
    const chipsEl = $('#chips');
    if (chipsEl && T.chips) {
      chipsEl.innerHTML = '';
      T.chips.forEach(c => {
        const el = document.createElement('span');
        el.className = 'chip';
        el.textContent = c;
        chipsEl.appendChild(el);
      });
    }
    // Text nodes
    $$('[data-i18n]').forEach(el => {
      const k = el.getAttribute('data-i18n');
      if (T[k] !== undefined) el.textContent = T[k];
    });
    // Placeholders
    $$('[data-i18n-ph]').forEach(el => {
      const k = el.getAttribute('data-i18n-ph');
      if (T[k] !== undefined) el.placeholder = T[k];
    });
  }

  /* ===== Notifications ===== */
  function showNotice(title, msg) {
    const n = $('#globalNotice');
    if (!n) return;
    $('#noticeTitle').textContent = title;
    $('#noticeMsg').textContent = msg;
    n.classList.add('show');
    clearTimeout(n._t);
    n._t = setTimeout(() => n.classList.remove('show'), 4500);
  }
  function beep() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.03;
      o.connect(g); g.connect(ctx.destination);
      o.start();
      setTimeout(() => { o.stop(); ctx.close(); }, 160);
    } catch (e) {}
  }

  /* ===== Rate limit ===== */
  function canGenerate() {
    const now = Date.now();
    state.genTimestamps = (state.genTimestamps || []).filter(t => now - t < 60000);
    if (state.genTimestamps.length >= 20) return false;
    state.genTimestamps.push(now);
    saveState();
    return true;
  }

  /* ===== Premium ===== */
  function getPremium() {
    try {
      const p = JSON.parse(localStorage.getItem(PREMIUM_KEY) || 'null');
      if (!p) return null;
      if (p.expires_at && Date.now() / 1000 > p.expires_at) {
        localStorage.removeItem(PREMIUM_KEY);
        return null;
      }
      return p;
    } catch (e) { return null; }
  }
  function setPremium(data) {
    localStorage.setItem(PREMIUM_KEY, JSON.stringify(data));
  }
  function applyPremiumUI() {
    const isPremium = !!getPremium();
    // Hide/show ad slots
    $$('.adslot, [data-ad-slot]').forEach(el => {
      el.style.display = isPremium ? 'none' : '';
    });
    const premiumForm = $('#premiumForm');
    const premiumActive = $('#premiumActive');
    const premiumRenew = $('#premiumRenew');
    if (isPremium) {
      const p = getPremium();
      if (premiumForm) premiumForm.style.display = 'none';
      if (premiumActive) {
        premiumActive.style.display = '';
        const expEl = $('#premiumExpiry');
        if (expEl && p && p.expires_at) {
          expEl.textContent = new Date(p.expires_at * 1000)
            .toLocaleDateString(LANG === 'ru' ? 'ru-RU' : 'en-US');
        }
      }
      if (premiumRenew) premiumRenew.style.display = '';
    } else {
      if (premiumForm) premiumForm.style.display = '';
      if (premiumActive) premiumActive.style.display = 'none';
      if (premiumRenew) premiumRenew.style.display = 'none';
    }
  }
  async function redeemCode(code) {
    try {
      const res = await fetch('/api/premium.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'redeem', code: code.trim().toUpperCase().replace(/\s+/g, '') })
      });
      const data = await res.json();
      if (data.ok) {
        setPremium({ code: code, expires_at: data.expires_at, user_id: data.user_id });
        applyPremiumUI();
        showNotice('⭐ Premium', T.premium_activated || 'Premium activated! Ads are now hidden.');
        return true;
      }
      const errMap = {
        'invalid_code': T.premium_invalid || 'Invalid code. Check and try again.',
        'expired_code': T.premium_expired_code || 'This code has already expired.',
        'already_redeemed': T.premium_already || 'Code already used.'
      };
      showNotice('⚠️', errMap[data.error] || (T.premium_error || 'Could not activate.'));
      return false;
    } catch (e) {
      showNotice('⚠️', T.network_error || 'Network error. Try again.');
      return false;
    }
  }

  /* ===== Guerrilla Mail ===== */
  const GM_BASE = '/api/guerrilla.php';
  async function gmCall(params) {
    const res = await fetch(GM_BASE + '?' + new URLSearchParams(params), { cache: 'no-store' });
    if (!res.ok) throw new Error('GuerrillaMail error');
    return res.json();
  }
  async function gmEnsureAddress(forceNew = false) {
    if (!canGenerate()) throw new Error(T.rate_limited || 'Rate limited');
    if (state.email && state.provider === 'guerrilla' && state.gm.sid && !forceNew) return;
    const d = await gmCall({ f: 'get_email_address' });
    state.gm.sid = d.sid_token;
    state.email = d.email_addr;
    state.provider = 'guerrilla';
    state.gm.seq = 0;
    state.lastSeenIds = [];
    saveState();
  }
  async function gmList() {
    if (!state.gm.sid) return [];
    const d = await gmCall({ f: 'get_email_list', offset: 0, sid_token: state.gm.sid });
    return (d.list || []).map(m => ({
      id: String(m.mail_id), from: m.mail_from || '',
      subject: m.mail_subject || '', date: m.mail_date || '', snippet: m.mail_excerpt || ''
    }));
  }
  async function gmFetch(id) {
    const d = await gmCall({ f: 'fetch_email', email_id: id, sid_token: state.gm.sid });
    return {
      id, from: d.mail_from || '', subject: d.mail_subject || '', date: d.mail_date || '',
      body_html: d.mail_body_html || null, body: d.mail_body || '',
      attachments: (d.attach || []).map(a => ({ name: a.filename || 'attachment', url: a.download_url || a.url || null }))
    };
  }
  async function gmDelete(id) {
    await gmCall({ f: 'del_email', email_ids: id, sid_token: state.gm.sid });
  }

  /* ===== Mail.tm ===== */
  const MT_BASE = 'https://api.mail.tm';
  async function mtCall(path, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    if (state.mt.token) headers['Authorization'] = 'Bearer ' + state.mt.token;
    if (opts.json) {
      headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(opts.json);
    }
    const res = await fetch(MT_BASE + path, { ...opts, headers });
    const data = await res.json().catch(() => null);
    if (!res.ok) throw (data || { error: 'Mail.tm error' });
    return data;
  }
  function randStr(n = 12) {
    const a = 'abcdefghijklmnopqrstuvwxyz0123456789';
    let s = '';
    for (let i = 0; i < n; i++) s += a[Math.floor(Math.random() * a.length)];
    return s;
  }
  async function mtEnsureAddress(forceNew = false) {
    if (!canGenerate()) throw new Error(T.rate_limited || 'Rate limited');
    // Restore: re-auth with saved credentials if token missing/expired
    if (!forceNew && state.mt.address && state.mt.password && state.provider === 'mailtm') {
      if (state.mt.token) return; // already have token
      try {
        const tok = await mtCall('/token', { method: 'POST', json: { address: state.mt.address, password: state.mt.password } });
        if (tok && tok.token) {
          state.mt.token = tok.token;
          state.email = state.mt.address;
          saveState();
          return;
        }
      } catch (e) { /* fall through to create new */ }
    }
    if (state.email && state.provider === 'mailtm' && state.mt.token && !forceNew) return;
    // Create new account
    const dom = await mtCall('/domains?page=1');
    const domain = (dom['hydra:member'] && dom['hydra:member'][0] && dom['hydra:member'][0].domain) || 'mail.tm';
    const local = 'user' + randStr(10);
    const address = `${local}@${domain}`;
    const password = randStr(16) + 'A1!';
    await mtCall('/accounts', { method: 'POST', json: { address, password } });
    const tok = await mtCall('/token', { method: 'POST', json: { address, password } });
    state.mt.address = address;
    state.mt.password = password;
    state.mt.token = tok.token;
    state.email = address;
    state.provider = 'mailtm';
    state.lastSeenIds = [];
    saveState();
  }
  async function mtList() {
    const d = await mtCall('/messages?page=1');
    return (d['hydra:member'] || []).map(m => ({
      id: String(m.id),
      from: (m.from && (m.from.address || m.from.name)) || '',
      subject: m.subject || '', date: m.createdAt || '', snippet: m.intro || ''
    }));
  }
  async function mtFetch(id) {
    const m = await mtCall('/messages/' + encodeURIComponent(id));
    return {
      id, from: (m.from && (m.from.address || m.from.name)) || '',
      subject: m.subject || '', date: m.createdAt || '',
      body_html: m.html ? m.html.join('<br/>') : null, body: m.text || '',
      attachments: (m.attachments || []).map(a => ({
        name: a.filename || 'attachment',
        url: MT_BASE + '/messages/' + encodeURIComponent(id) + '/attachments/' + encodeURIComponent(a.id)
      }))
    };
  }
  async function mtDelete(id) {
    await mtCall('/messages/' + encodeURIComponent(id), { method: 'DELETE' });
  }

  /* ===== Helpers ===== */
  function escapeHtml(s) {
    return (s || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }

  /* ===== Email UI ===== */
  function wireEmailUI() {
    const providerSel = $('#provider');
    if (!providerSel) return null;

    const btnGenerate = $('#btnGenerate');
    const btnNew = $('#btnNew');
    const btnRefresh = $('#btnRefresh');
    const btnRefresh2 = $('#btnRefresh2');
    const btnDelete = $('#btnDeleteEmail');
    const emailBox = $('#emailBox');
    const currentEmail = $('#currentEmail');
    const btnCopy = $('#btnCopy');
    const inboxWrap = $('#inboxWrap');
    const mailList = $('#mailList');
    const viewer = $('#viewer');
    const emptyState = $('#emptyState');
    const countBadge = $('#countBadge');

    providerSel.value = state.provider || 'mailtm';

    function setEmail(email) {
      if (!email) return;
      if (emailBox) emailBox.style.display = '';
      if (inboxWrap) inboxWrap.style.display = '';
      if (currentEmail) currentEmail.textContent = email;
    }
    function hideEmailUI() {
      if (emailBox) emailBox.style.display = 'none';
      if (inboxWrap) inboxWrap.style.display = 'none';
      if (viewer) viewer.classList.remove('active');
    }

    function renderList(list) {
      if (countBadge) countBadge.textContent = String(list.length);
      if (!mailList) return;
      mailList.innerHTML = '';
      if (list.length === 0) {
        emptyState && emptyState.classList.add('active');
        viewer && viewer.classList.remove('active');
        return;
      }
      emptyState && emptyState.classList.remove('active');
      list.forEach(m => {
        const isNew = !state.readIds[m.id] && !state.lastSeenIds.includes(m.id);
        const item = document.createElement('div');
        item.className = 'item';
        item.innerHTML = `
          <div style="display:flex;align-items:flex-start;min-width:0;gap:10px">
            <span class="dot ${isNew ? 'new' : ''}"></span>
            <div class="meta">
              <div class="from">${escapeHtml(m.from || '(unknown)')}</div>
              <div class="subj">${escapeHtml(m.subject || '(no subject)')}</div>
              <div class="subj" style="font-size:12px">${escapeHtml(m.snippet || '')}</div>
            </div>
          </div>
          <div class="badge">${escapeHtml((m.date || '').toString().slice(0, 19).replace('T', ' '))}</div>`;
        item.onclick = () => openMessage(m.id);
        mailList.appendChild(item);
      });
    }

    async function openMessage(id) {
      if (!viewer) return;
      viewer.classList.add('active');
      emptyState && emptyState.classList.remove('active');
      viewer.innerHTML = `<div class="hdr" style="padding:8px 0">⏳ Loading…</div>`;
      try {
        const msg = state.provider === 'guerrilla' ? await gmFetch(id) : await mtFetch(id);
        state.readIds[id] = true;
        saveState();
        const bodyHtml = msg.body_html
          ? msg.body_html
          : `<pre style="white-space:pre-wrap;margin:0;font-family:inherit">${escapeHtml(msg.body || '')}</pre>`;
        const atts = (msg.attachments || []).filter(a => a.url);
        viewer.innerHTML = `
          <h3>${escapeHtml(msg.subject || '(no subject)')}</h3>
          <div class="hdr">
            <div><strong>From:</strong> ${escapeHtml(msg.from || '')}</div>
            <div><strong>Date:</strong> ${escapeHtml((msg.date || '').toString())}</div>
          </div>
          <div class="actions">
            <button class="btn secondary" id="btnMark">${escapeHtml(T.mark_read || 'Mark read')}</button>
            <button class="btn danger" id="btnDel">${escapeHtml(T.delete || 'Delete')}</button>
          </div>
          ${atts.length ? `<div style="margin-top:12px">${atts.map(a => `<a class="tag" href="${a.url}" target="_blank" rel="noopener">⬇ ${escapeHtml(T.download || 'Download')}: ${escapeHtml(a.name)}</a>`).join('')}</div>` : ''}
          <div class="body">${bodyHtml}</div>`;
        $('#btnMark').onclick = () => showNotice('✓', T.mark_read || 'Marked as read');
        $('#btnDel').onclick = async () => {
          if (!confirm(T.delete_confirm || 'Delete this message?')) return;
          try {
            if (state.provider === 'guerrilla') await gmDelete(id);
            else await mtDelete(id);
          } catch (e) {}
          showNotice('✓', T.deleted || 'Deleted');
          await refreshInbox(true);
          viewer.classList.remove('active');
        };
      } catch (e) {
        viewer.innerHTML = `<div class="hdr">⚠️ Failed to load message.</div>`;
      }
    }

    function ensureAddress(forceNew = false) {
      return state.provider === 'guerrilla' ? gmEnsureAddress(forceNew) : mtEnsureAddress(forceNew);
    }
    function listMessages() {
      return state.provider === 'guerrilla' ? gmList() : mtList();
    }

    // Blink on new mail
    let blinkTimer = null;
    function startBlink() {
      if (!btnCopy) return;
      btnCopy.classList.add('blink');
      const baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
      let on = false;
      if (blinkTimer) clearInterval(blinkTimer);
      blinkTimer = setInterval(() => {
        on = !on;
        document.title = on ? '(1) ' + baseTitle : baseTitle;
      }, 900);
      setTimeout(() => stopBlink(), 12000);
    }
    function stopBlink() {
      if (btnCopy) btnCopy.classList.remove('blink');
      if (blinkTimer) { clearInterval(blinkTimer); blinkTimer = null; }
      document.title = document.title.replace(/^\(\d+\)\s*/, '');
    }

    async function refreshInbox(silent = false) {
      if (!state.email) return;
      try {
        const list = await listMessages();
        const ids = list.map(x => x.id);
        const newOnes = ids.filter(id => !state.lastSeenIds.includes(id));
        if (newOnes.length && !silent) {
          showNotice('📬 ' + (T.card_title || 'Inbox'), T.new_mail || 'New email received!');
          beep();
          startBlink();
        }
        state.lastSeenIds = ids;
        saveState();
        renderList(list);
      } catch (e) {
        if (!silent) showNotice('⚠️', 'Failed to refresh inbox.');
      }
    }

    /* Delete entire email address */
    if (btnDelete) {
      btnDelete.onclick = () => {
        if (!confirm(T.delete_email_confirm || 'Delete this email address? All messages will be lost.')) return;
        state.email = null;
        state.gm = { sid: null, seq: 0 };
        state.mt = { address: null, password: null, token: null };
        state.readIds = {};
        state.lastSeenIds = [];
        saveState();
        hideEmailUI();
        showNotice('🗑', T.email_deleted || 'Email deleted. Click "Generate" for a new one.');
      };
    }

    providerSel.onchange = async () => {
      state.provider = providerSel.value;
      saveState();
      const hasEmail = state.email &&
        ((state.provider === 'guerrilla' && state.gm.sid) ||
         (state.provider === 'mailtm' && state.mt.token));
      if (hasEmail) {
        setEmail(state.email);
        await refreshInbox(true);
      } else {
        hideEmailUI();
      }
    };

    btnGenerate.onclick = async () => {
      btnGenerate.disabled = true;
      try {
        await ensureAddress(false);
        setEmail(state.email);
        await refreshInbox(true);
      } catch (e) {
        showNotice('ℹ️', (e && e.message) ? e.message : (T.rate_limited || 'Rate limited'));
      } finally {
        btnGenerate.disabled = false;
      }
    };

    btnNew.onclick = async () => {
      if (!confirm(T.new_email_confirm || 'Generate a new email? The current address will be lost.')) return;
      btnNew.disabled = true;
      try {
        await ensureAddress(true);
        setEmail(state.email);
        await refreshInbox(true);
      } catch (e) {
        showNotice('ℹ️', (e && e.message) ? e.message : (T.rate_limited || 'Rate limited'));
      } finally {
        btnNew.disabled = false;
      }
    };

    const doRefresh = () => refreshInbox(false);
    if (btnRefresh) btnRefresh.onclick = doRefresh;
    if (btnRefresh2) btnRefresh2.onclick = doRefresh;

    if (btnCopy) {
      btnCopy.onclick = async () => {
        try {
          await navigator.clipboard.writeText(state.email || '');
          stopBlink();
          showNotice('✓', T.copied || 'Copied to clipboard!');
        } catch (e) {}
      };
    }

    // Polling
    setInterval(() => refreshInbox(true), 5000);
    setInterval(() => refreshInbox(false), 10000);

    return { setEmail, hideEmailUI, refreshInbox, ensureAddress };
  }

  /* ===== Premium UI wiring ===== */
  function wirePremiumUI() {
    const codeInput = $('#premiumCodeInput');
    const btnActivate = $('#btnActivatePremium');
    const btnRenew = $('#btnRenewPremium');

    if (!codeInput || !btnActivate) return;

    btnActivate.onclick = async () => {
      const code = codeInput.value.trim();
      if (!code) { showNotice('ℹ️', T.premium_enter_code || 'Enter your premium code'); return; }
      btnActivate.disabled = true;
      btnActivate.textContent = '…';
      const ok = await redeemCode(code);
      if (!ok) codeInput.value = '';
      btnActivate.disabled = false;
      btnActivate.textContent = T.premium_activate || 'Activate';
    };

    // Allow Enter key to submit
    codeInput.onkeydown = e => { if (e.key === 'Enter') btnActivate.click(); };

    if (btnRenew) {
      btnRenew.onclick = () => {
        window.open('https://t.me/temail_pro_bot?start=premium', '_blank');
      };
    }
  }

  /* ===== Language switcher ===== */
  function wireLangSwitcher() {
    $$('.pill[data-lang]').forEach(pill => {
      pill.onclick = e => {
        e.preventDefault();
        const lng = pill.getAttribute('data-lang');
        localStorage.setItem(LANG_KEY, lng);
        const url = new URL(location.href);
        url.searchParams.set('lang', lng);
        location.href = url.toString();
      };
    });
  }

  /* ===== Blog ===== */
  async function loadPosts(limit = 20) {
    const url = '/api/blog.php?action=list&limit=' + limit;
    const res = await fetch(url, { cache: 'no-store' });
    const data = await res.json().catch(() => ({ posts: [] }));
    return data.posts || [];
  }
  function stripHtml(html) {
    const d = document.createElement('div');
    d.innerHTML = html || '';
    return (d.textContent || d.innerText || '').trim();
  }
  function renderPostCards(posts, sel) {
    const wrap = $(sel);
    if (!wrap) return;
    wrap.innerHTML = '';
    posts.forEach(p => {
      const a = document.createElement('a');
      a.className = 'postcard';
      a.href = '/post.php?id=' + encodeURIComponent(p.id) + '&lang=' + encodeURIComponent(LANG);
      const cover = p.cover || 'https://images.unsplash.com/photo-1516387938699-a93567ec168e?auto=format&fit=crop&w=1200&q=70';
      const excerpt = stripHtml(p.content || '').slice(0, 120) + (stripHtml(p.content || '').length > 120 ? '…' : '');
      a.innerHTML = `
        <img src="${cover}" alt="${escapeHtml(p.title)}" loading="lazy"/>
        <div class="p">
          <div class="meta">${escapeHtml(p.published_at || '')}</div>
          <h3>${escapeHtml(p.title || '')}</h3>
          <div class="excerpt">${escapeHtml(excerpt)}</div>
          <div>${(p.tags || []).slice(0, 3).map(t => `<span class="tag">${escapeHtml(t)}</span>`).join('')}</div>
        </div>`;
      wrap.appendChild(a);
    });
  }

  async function wirePostView() {
    const wrap = $('#postView');
    if (!wrap) return;
    const id = new URLSearchParams(location.search).get('id');
    if (!id) { wrap.innerHTML = `<p style="color:var(--muted)">Missing post id.</p>`; return; }
    const res = await fetch('/api/blog.php?action=get&id=' + encodeURIComponent(id), { cache: 'no-store' });
    const data = await res.json().catch(() => null);
    if (!res.ok || !data) { wrap.innerHTML = `<p style="color:var(--muted)">Not found.</p>`; return; }
    const p = data.post;
    document.title = (p.title || 'Post') + ' — ' + document.title;
    const cover = p.cover ? `<img src="${p.cover}" alt="${escapeHtml(p.title)}" style="width:100%;border-radius:18px;max-height:360px;object-fit:cover;margin-bottom:14px"/>` : '';
    wrap.innerHTML = `
      ${cover}
      <h1 style="margin:0 0 8px">${escapeHtml(p.title || '')}</h1>
      <div style="color:var(--muted);font-size:13px">${escapeHtml(p.published_at || '')}</div>
      <div style="margin-top:12px">${(p.tags || []).map(t => `<span class="tag">${escapeHtml(t)}</span>`).join('')}</div>
      <div style="margin-top:16px;line-height:1.8;color:var(--text)">${p.content || ''}</div>
      <div style="margin-top:18px"><a class="btn secondary" href="/blog.php?lang=${encodeURIComponent(LANG)}">← Back to blog</a></div>`;
  }

  /* ===== Init ===== */
  async function init() {
    loadState();
    await loadI18n();
    wireLangSwitcher();
    applyPremiumUI();

    const emailUI = wireEmailUI();
    wirePremiumUI();

    if (emailUI) {
      if (state.email) {
        // Restore existing email
        emailUI.setEmail(state.email);
        // Fix Mail.tm: token may be gone after page reload — re-auth
        if (state.provider === 'mailtm' && !state.mt.token && state.mt.address && state.mt.password) {
          mtEnsureAddress(false)
            .then(() => emailUI.refreshInbox(true))
            .catch(() => emailUI.refreshInbox(true));
        } else {
          emailUI.refreshInbox(true);
        }
      } else {
        // Auto-generate email on first visit
        try {
          if (state.provider === 'mailtm') await mtEnsureAddress(false);
          else await gmEnsureAddress(false);
          emailUI.setEmail(state.email);
          await emailUI.refreshInbox(true);
        } catch (e) { /* silent fail */ }
      }
    }

    if ($('#latestPosts')) renderPostCards(await loadPosts(20), '#latestPosts');
    if ($('#blogPosts')) renderPostCards(await loadPosts(200), '#blogPosts');
    await wirePostView();
  }

  init().catch(() => {});
})();
