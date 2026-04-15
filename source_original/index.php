<?php include __DIR__ . "/partials/header.php"; ?>

<div class="hero">
  <div class="hero-badge">
    <span class="pulse-dot"></span>
    <span>Free · Anonymous · Instant</span>
  </div>
  <h1><?php echo htmlspecialchars($config['site']['brand']); ?></h1>
  <p class="sub" data-i18n="headline">Instant free temporary email — no signup needed</p>
  <div class="chips" id="chips"></div>
</div>

<!-- ===== Main Email Card ===== -->
<div class="card" id="mailCard">
  <h2 data-i18n="card_title">Temporary Email Inbox</h2>
  <p data-i18n="card_sub">Auto-generated email ready. Receive emails, open messages — completely anonymous.</p>

  <div class="controls">
    <label style="display:flex;gap:10px;align-items:center">
      <span style="color:var(--muted);font-size:13px" data-i18n="provider_label">Provider</span>
      <select class="select" id="provider">
        <option value="mailtm" selected data-i18n="provider_gmail">Mail.tm (Gmail-style)</option>
        <option value="guerrilla" data-i18n="provider_gm">Guerrilla Mail</option>
      </select>
    </label>
    <button class="btn" id="btnGenerate" data-i18n="btn_generate">Get Email</button>
    <button class="btn secondary" id="btnNew" data-i18n="btn_new">New Email</button>
    <button class="btn secondary" id="btnRefresh" data-i18n="btn_refresh">Refresh</button>
  </div>

  <!-- Email address display -->
  <div class="emailbox" id="emailBox" style="display:none">
    <div style="min-width:0">
      <div style="color:var(--muted);font-size:12px;margin-bottom:5px">Your temporary email:</div>
      <code id="currentEmail"></code>
    </div>
    <div class="right" style="display:flex;gap:8px;align-items:center;flex-shrink:0">
      <button class="iconbtn" id="btnCopy" title="Copy" aria-label="Copy">
        📋 <span data-i18n="copy">Copy</span>
      </button>
      <button class="iconbtn delete-btn" id="btnDeleteEmail" title="Delete this email" aria-label="Delete email">
        🗑
      </button>
    </div>
  </div>

  <!-- Inbox -->
  <div class="inbox" id="inboxWrap" style="display:none">
    <div class="inbox-head">
      <div class="left">
        <span>📥</span>
        <span data-i18n="inbox">Inbox</span>
        <span class="badge" id="countBadge">0</span>
      </div>
      <button class="btn secondary" id="btnRefresh2" data-i18n="btn_refresh">Refresh</button>
    </div>
    <div class="list" id="mailList"></div>
    <div class="viewer" id="viewer"></div>
    <div class="viewer active" id="emptyState">
      <div style="text-align:center;padding:26px">
        <div style="font-size:48px;opacity:.85">✉️</div>
        <h3 data-i18n="no_emails">No emails yet</h3>
        <p class="hdr" data-i18n="no_emails_sub">Your inbox is empty. Emails will appear here automatically.</p>
      </div>
    </div>
  </div>
</div>

<!-- ===== Premium Card ===== -->
<div class="card premium-card" id="premiumCard" style="margin-top:16px">
  <h2 data-i18n="premium_title">⭐ Premium — Ad-Free</h2>

  <!-- Not premium: show activation form -->
  <div id="premiumForm">
    <p data-i18n="premium_sub">Pay 200 Telegram Stars via our bot and get a unique code to remove all ads for 1 month.</p>
    <div class="code-input-row">
      <input type="text" class="input" id="premiumCodeInput" maxlength="24"
        data-i18n-ph="premium_enter_code"
        placeholder="XXXX-XXXX-XXXX-XXXX"
        autocomplete="off" spellcheck="false"/>
      <button class="btn gold" id="btnActivatePremium" data-i18n="premium_activate">Activate</button>
    </div>
    <div style="margin-top:14px;display:flex;flex-wrap:wrap;gap:10px">
      <a class="btn secondary" href="https://t.me/temail_pro_bot?start=premium" target="_blank" rel="noopener">
        ⭐ <span data-i18n="premium_get">Get Premium in Telegram Bot</span>
      </a>
    </div>
    <div style="margin-top:16px;color:var(--muted);font-size:13px">
      <strong data-i18n="premium_how">How it works:</strong>
      <ol style="margin:8px 0 0 18px;padding:0;line-height:1.8">
        <li data-i18n="premium_step1">Open our Telegram bot</li>
        <li data-i18n="premium_step2">Tap ⭐ Premium and pay 200 Stars</li>
        <li data-i18n="premium_step3">You receive a unique code</li>
        <li data-i18n="premium_step4">Enter it here — ads disappear for 30 days</li>
      </ol>
    </div>
  </div>

  <!-- Premium active -->
  <div id="premiumActive" style="display:none">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:4px">
      <div class="premium-status">
        <span class="dot-gold"></span>
        <span data-i18n="premium_active_label">Premium Active</span>
      </div>
      <div style="color:var(--muted);font-size:14px">
        <span data-i18n="premium_expires">Valid until:</span>
        <strong id="premiumExpiry" style="color:var(--text);margin-left:6px"></strong>
      </div>
    </div>
    <div id="premiumRenew" style="display:none;margin-top:14px">
      <button class="btn gold" id="btnRenewPremium" data-i18n="premium_renew">🔄 Renew via Telegram Bot</button>
    </div>
  </div>
</div>

<!-- ===== Ad Slot (hidden for premium users) ===== -->
<div class="adslot" id="adTop">
  <!-- Google AdSense / other ad networks here -->
  <!-- This slot is hidden automatically when user has active premium -->
</div>

<!-- ===== FAQ ===== -->
<h2 class="section-title" data-i18n="faq_title">Frequently Asked Questions</h2>
<p class="section-sub">Quick answers about disposable email.</p>
<div class="panel">
  <details>
    <summary><strong>What is a temporary email address?</strong></summary>
    <p style="color:var(--muted);line-height:1.7;margin-top:8px">
      A disposable email address you can use for sign-ups and verification codes without exposing your real inbox.
      No registration required — your address is auto-generated and ready instantly.
    </p>
  </details>
  <details>
    <summary><strong>Will my email be saved after refresh?</strong></summary>
    <p style="color:var(--muted);line-height:1.7;margin-top:8px">
      Yes. Your email address is saved in your browser and automatically restored on every page visit.
      It only disappears when you explicitly click the 🗑 delete button or generate a new one.
    </p>
  </details>
  <details>
    <summary><strong>What is Premium and how does it work?</strong></summary>
    <p style="color:var(--muted);line-height:1.7;margin-top:8px">
      Premium costs 200 Telegram Stars (≈ $2) per month and removes all ads from the website and Telegram Mini App.
      Pay in our bot, receive a unique code, enter it above — ads are hidden for 30 days.
      Renewal extends your subscription (no duplicate codes).
    </p>
  </details>
  <details>
    <summary><strong>Can I download attachments?</strong></summary>
    <p style="color:var(--muted);line-height:1.7;margin-top:8px">
      If the provider includes attachments, download links are shown inside the message view.
    </p>
  </details>
</div>

<!-- ===== Latest Blog Posts ===== -->
<h2 class="section-title" data-i18n="blog_latest">Latest articles</h2>
<p class="section-sub">Tips about privacy, spam protection, and using disposable email safely.</p>
<div class="postgrid" id="latestPosts"></div>

<!-- ===== Ad Slot Bottom (hidden for premium) ===== -->
<div class="adslot" id="adBottom">
  <!-- Bottom ad slot — configure your AdSense/Yandex code here -->
</div>

<?php include __DIR__ . "/partials/footer.php"; ?>
