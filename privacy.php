<?php include __DIR__ . "/partials/header.php"; ?>
<div class="panel" style="margin-top:22px">
  <h2>Privacy Policy</h2>
  <p style="color:var(--muted);line-height:1.7">
    This website provides a browser interface for temporary email services and aims to minimize data collection.
    We do not require user accounts. Technical logs may be processed for security and abuse prevention.
  </p>
  <p style="color:var(--muted);line-height:1.7">
    Important: Inbox data is stored in your browser (localStorage) unless you delete it. Email delivery and mailbox storage are handled by the selected provider (Guerrilla Mail or Mail.tm) under their own policies.
  </p>
  <p style="color:var(--muted);line-height:1.7">
    Contact: <a href="mailto:<?php echo htmlspecialchars($config['site']['contact_email']); ?>"><?php echo htmlspecialchars($config['site']['contact_email']); ?></a>
  </p>
</div>
<?php include __DIR__ . "/partials/footer.php"; ?>
