
  <footer class="footer">
    <div><?php echo htmlspecialchars($config['site']['brand']); ?> — Free Temporary Email Service &nbsp;·&nbsp;
      <a href="/privacy.php?lang=<?php echo $lang; ?>" data-i18n="nav_privacy">Privacy</a> &nbsp;·&nbsp;
      <a href="/terms.php?lang=<?php echo $lang; ?>" data-i18n="nav_terms">Terms</a> &nbsp;·&nbsp;
      <a href="https://t.me/temail_pro_bot" target="_blank" rel="noopener">Telegram Bot</a>
    </div>
    <div style="margin-top:6px;opacity:.55">© 2021–<?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site']['brand']); ?> · Powered by Mail.tm &amp; Guerrilla Mail</div>
  </footer>
</div><!-- /container -->

<div class="notice" id="globalNotice" role="status" aria-live="polite">
  <p class="t" id="noticeTitle">Notification</p>
  <p class="m" id="noticeMsg">...</p>
</div>

<script src="/assets/js/app.js?v=2.0"></script>
</body>
</html>
