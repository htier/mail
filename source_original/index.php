<?php include __DIR__ . "/partials/header.php"; ?>
<div class="hero">
  <h1><?php echo htmlspecialchars($config['site']['brand']); ?> ✨</h1>
  <p class="sub" data-i18n="headline">Generate temporary email addresses instantly</p>
  <div class="chips" id="chips"></div>
</div>
<div class="card" id="mailCard">
  <h2 data-i18n="card_title">Temporary Email Inbox</h2>
  <p data-i18n="card_sub">Create an address, receive emails, open messages — no account needed.</p>

  <div class="controls">
    <label style="display:flex;gap:10px;align-items:center">
      <span style="color:var(--muted);font-size:13px" data-i18n="provider_label">Provider</span>
      <select class="select" id="provider">
        <option value="guerrilla" data-i18n="provider_gm">Guerrilla Mail</option>
        <option value="mailtm" data-i18n="provider_gmail">Gmail‑style (Mail.tm)</option>
      </select>
    </label>

    <button class="btn" id="btnGenerate" data-i18n="btn_generate">Generate Email</button>
    <button class="btn secondary" id="btnNew" data-i18n="btn_new">Generate New Email</button>
    <button class="btn secondary" id="btnRefresh" data-i18n="btn_refresh">Refresh</button>
  </div>

  <div class="emailbox" id="emailBox" style="display:none">
    <div>
      <div style="color:var(--muted);font-size:12px;margin-bottom:6px">Your temporary email:</div>
      <code id="currentEmail"></code>
    </div>
    <div class="right" style="display:flex;gap:10px;align-items:center">
      <button class="iconbtn" id="btnCopy" title="Copy" aria-label="Copy">
  📋 <span data-i18n="copy">Copy</span>
</button>
    </div>
  </div>

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
      <div style="text-align:center;padding:22px">
        <div style="font-size:48px;opacity:.9">✉️</div>
        <h3 data-i18n="no_emails">No emails yet</h3>
        <p class="hdr" data-i18n="no_emails_sub">Your inbox is empty. Emails will appear here when received.</p>
      </div>
    </div>
  </div>
</div>

<h2 class="section-title" data-i18n="faq_title">Frequently Asked Questions</h2>
<p class="section-sub">Quick answers about disposable email and how this inbox works.</p>
<section class="panel">
<h2>What is a Temporary Email?</h2>
<p>
A temporary email address is a disposable inbox that allows you to receive emails
without exposing your real email address. TeMail.pro helps you stay anonymous,
avoid spam, and protect your privacy online.
</p>
</section>

<div class="panel">
  <details>
    <summary><strong>What is a temporary email address?</strong></summary>
    <p style="color:var(--muted);line-height:1.6">
      It’s an email address you can use for sign‑ups and verification codes without exposing your personal mailbox.
    </p>
  </details>
  <details>
    <summary><strong>Does this require a login?</strong></summary>
    <p style="color:var(--muted);line-height:1.6">
      No. The inbox lives in your browser. If you don’t delete it, we keep your current address in localStorage so page refresh won’t lose it.
    </p>
  </details>
  <details>
    <summary><strong>Can I download attachments?</strong></summary>
    <p style="color:var(--muted);line-height:1.6">
      If the provider includes attachments, we show download links inside the message view.
    </p>
  </details>
</div>

<h2 class="section-title" data-i18n="blog_latest">Latest posts</h2>
<p class="section-sub">Tips about privacy, spam protection, and using disposable email safely.</p>

<div class="postgrid" id="latestPosts"></div>

<div class="adslot" id="adBottom" aria-label="Ad slot bottom">
  <div data-i18n="ad_bottom">Ad slot (bottom)</div>
  <!-- Paste Google AdSense code here (bottom slot). -->
</div>

<?php include __DIR__ . "/partials/footer.php"; ?>
