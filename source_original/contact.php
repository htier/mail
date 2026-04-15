<?php include __DIR__ . "/partials/header.php"; ?>
<div class="panel" style="margin-top:22px">
  <h2>Contact</h2>
  <p style="color:var(--muted);line-height:1.7">
    For support, partnerships, or legal inquiries, contact us:
  </p>
  <p style="font-size:18px">
    <a class="btn secondary" href="mailto:<?php echo htmlspecialchars($config['site']['contact_email']); ?>">
      <?php echo htmlspecialchars($config['site']['contact_email']); ?>
    </a>
  </p>

  <div class="kv">
    <div class="panel">
      <h3>Telegram</h3>
      <p style="color:var(--muted);line-height:1.7">
        You can open this website inside Telegram as a Mini App (Web App).
      </p>
      <p><a class="btn secondary" href="https://t.me/TeMail_pro_bot" target="_blank" rel="noopener">@TeMail_pro_bot</a></p>
    </div>
    <div class="panel">
      <h3>Ads & Partnerships</h3>
      <p style="color:var(--muted);line-height:1.7">
        For advertising requests, please email us with your proposal.
      </p>
    </div>
  </div>
</div>
<?php include __DIR__ . "/partials/footer.php"; ?>
