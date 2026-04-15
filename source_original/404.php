<?php
http_response_code(404);
require __DIR__ . '/partials/header.php';
?>

<main class="wrap" style="max-width: var(--max); margin: 0 auto; padding: 24px 16px;">
  <section class="panel" style="position:relative; overflow:hidden;">
    <div class="notfound-grid">
      <div class="notfound-left">
        <div class="nf-code">404</div>
        <h1 class="nf-title" data-i18n="nf_title">Oops… this page went missing</h1>
        <p class="nf-sub" data-i18n="nf_sub">
          The link may be broken, the page was moved, or it never existed.
          Don’t worry — your inbox still works.
        </p>
  </script>
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7442589230691601"
     crossorigin="anonymous"></script>
        <div class="nf-actions">
          <a class="btn primary" href="/"
             data-i18n="nf_home">Go to homepage</a>

          <a class="btn" href="/#inbox"
             data-i18n="nf_inbox">Open inbox</a>

          <button class="btn ghost" id="btnStop"
                  data-i18n="nf_stop">Redirect stopped</button>
        </div>

        <div class="nf-timer">
          <div class="nf-timer-row">
            <span data-i18n="nf_redirect">Redirecting in</span>
            <strong><span id="nfSec">10</span>s</strong>
            <span class="nf-dot"></span>
            <span class="nf-badge" data-i18n="nf_protected">Protected</span>
          </div>
          <div class="nf-bar">
            <div class="nf-bar-fill" id="nfBar"></div>
          </div>
        </div>
      </div>

      <div class="notfound-right" aria-hidden="true">
        <div class="nf-card">
          <div class="nf-icon">📩</div>
          <div class="nf-mini">
            <span>⚡ <span data-i18n="nf_fast">Fast</span></span>
            <span>🗑️ <span data-i18n="nf_disposable">Disposable</span></span>
            <span>🔒 <span data-i18n="nf_private">Private</span></span>
          </div>
        </div>
      </div>
    </div>

    <!-- фоновая анимация -->
    <div class="nf-glow"></div>
  </section>
</main>

<script>
(function(){
  let sec = 10;
  let stopped = false;

  const elSec = document.getElementById('nfSec');
  const bar = document.getElementById('nfBar');
  const btnStop = document.getElementById('btnStop');

  // progress bar: от 100% до 0%
  function setBar(){
    const pct = (sec/10)*100;
    bar.style.width = pct + '%';
  }
  setBar();

  btnStop.addEventListener('click', function(){
    stopped = true;
    btnStop.classList.add('active-stop');
  });

  const t = setInterval(()=>{
    if(stopped) return;
    sec--;
    if(sec < 0){
      clearInterval(t);
      location.href = '/';
      return;
    }
    elSec.textContent = String(sec);
    setBar();
  }, 1000);
})();
</script>

<?php
require __DIR__ . '/partials/footer.php';
?>
