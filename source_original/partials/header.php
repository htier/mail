<?php
$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';
if(!in_array($lang, ['en','ru'])) $lang = 'en';
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
 <title>Temporary Email — Free Disposable Email Generator | TeMail.pro</title>
<meta name="description"
content="Free temporary email service. Create disposable email addresses instantly. No signup, real-time inbox, spam protection. Guerrilla Mail & Gmail-style email." />
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large"/>
  <link rel="canonical" href="https://<?php echo htmlspecialchars($config['site']['domain']); ?><?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); ?>"/>
  <link rel="alternate" hreflang="en" href="?lang=en"/>
  <link rel="alternate" hreflang="ru" href="?lang=ru"/>
  <link rel="stylesheet" href="/assets/css/styles.css?v=1.0"/>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64'%3E%3Cdefs%3E%3CradialGradient id='g' cx='20%25' cy='20%25'%3E%3Cstop offset='0%25' stop-color='%236fe7ff'/%3E%3Cstop offset='100%25' stop-color='%23c056ff'/%3E%3C/radialGradient%3E%3C/defs%3E%3Crect rx='18' ry='18' width='64' height='64' fill='url(%23g)'/%3E%3Cpath d='M18 24h28v18H18z' fill='none' stroke='%23081218' stroke-width='4'/%3E%3Cpath d='M18 24l14 10 14-10' fill='none' stroke='%23081218' stroke-width='4'/%3E%3C/svg%3E"/>
  <script>
    window.TEMAIL = { lang: "<?php echo $lang; ?>", brand: "<?php echo htmlspecialchars($config['site']['brand']); ?>", contact: "<?php echo htmlspecialchars($config['site']['contact_email']); ?>" };
  </script>
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7442589230691601"
     crossorigin="anonymous"></script>
     <!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js', 'ym');

    ym(94250304, 'init', {webvisor:true, trackHash:true, clickmap:true, accurateTrackBounce:true, trackLinks:true});
</script>

<!-- /Yandex.Metrika counter -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "TeMail.pro",
  "url": "https://temail.pro",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://temail.pro/?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>

<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>
  const tg = window.Telegram?.WebApp;
  tg?.ready();
  // tg?.expand(); // если хочешь на весь экран
</script>

<script src="https://5gvci.com/act/files/tag.min.js?z=10735480" data-cfasync="false" async></script>

<script>(function(s){s.dataset.zone='10735487',s.src='https://nap5k.com/tag.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>

<script>(function(s){s.dataset.zone='10735489',s.src='https://gizokraijaw.net/vignette.min.js'})([document.documentElement, document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>



</head>
<body>
<div class="bg-art"></div>
<div class="container">
  <div class="nav">
    <a class="brand" href="/index.php?lang=<?php echo $lang; ?>">
      <span class="logo">✉</span>
      <span><?php echo htmlspecialchars($config['site']['brand']); ?></span>
    </a>
    <div class="navlinks">
      <a href="/index.php?lang=<?php echo $lang; ?>" data-i18n="nav_home">Home</a>
      <a href="/blog.php?lang=<?php echo $lang; ?>" data-i18n="nav_blog">Blog</a>
      <a href="/privacy.php?lang=<?php echo $lang; ?>" data-i18n="nav_privacy">Privacy</a>
      <a href="/terms.php?lang=<?php echo $lang; ?>" data-i18n="nav_terms">Terms</a>
      <a href="/contact.php?lang=<?php echo $lang; ?>" data-i18n="nav_contact">Contact</a>
    </div>
    <div class="lang">
      <a class="pill <?php echo $lang==='en'?'active':''; ?>" href="<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); ?>?lang=en">EN</a>
      <a class="pill <?php echo $lang==='ru'?'active':''; ?>" href="<?php echo htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)); ?>?lang=ru">RU</a>
    </div>
  </div>
