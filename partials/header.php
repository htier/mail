<?php
/**
 * TeMail.pro — Global Header Partial
 * Clean, SEO-optimised, no malicious third-party scripts
 * Set $pageMeta['title'], $pageMeta['description'], $pageMeta['image'] before include for per-page overrides
 */
$config  = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
$lang    = $_GET['lang'] ?? $config['site']['default_lang'] ?? 'en';
if (!in_array($lang, ['en', 'ru'], true)) $lang = 'en';

$brand   = $config['site']['brand']         ?? 'TeMail.pro';
$domain  = $config['site']['domain']        ?? 'temail.pro';
$contact = $config['site']['contact_email'] ?? 'info@temail.pro';

$path         = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$canonicalUrl = "https://{$domain}" . htmlspecialchars($path, ENT_QUOTES);

$metaTitle = $pageMeta['title']       ?? "Temporary Email — Free Disposable Inbox | {$brand}";
$metaDesc  = $pageMeta['description'] ?? "Free temporary email service. Create a disposable address instantly — no signup, real-time inbox, spam-free. Powered by Guerrilla Mail & Mail.tm.";
$metaImg   = $pageMeta['image']       ?? "https://{$domain}/assets/og-image.png";
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
  <meta name="theme-color" content="#151829"/>

  <!-- Primary SEO -->
  <title><?= htmlspecialchars($metaTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>"/>
  <meta name="robots"      content="index, follow, max-snippet:-1, max-image-preview:large"/>
  <link rel="canonical"    href="<?= $canonicalUrl ?>"/>
  <link rel="alternate" hreflang="en"        href="https://<?= $domain ?><?= htmlspecialchars($path) ?>?lang=en"/>
  <link rel="alternate" hreflang="ru"        href="https://<?= $domain ?><?= htmlspecialchars($path) ?>?lang=ru"/>
  <link rel="alternate" hreflang="x-default" href="https://<?= $domain ?><?= htmlspecialchars($path) ?>"/>

  <!-- Open Graph -->
  <meta property="og:type"        content="website"/>
  <meta property="og:url"         content="<?= $canonicalUrl ?>"/>
  <meta property="og:title"       content="<?= htmlspecialchars($metaTitle) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>"/>
  <meta property="og:image"       content="<?= htmlspecialchars($metaImg) ?>"/>
  <meta property="og:site_name"   content="<?= htmlspecialchars($brand) ?>"/>
  <meta property="og:locale"      content="<?= $lang === 'ru' ? 'ru_RU' : 'en_US' ?>"/>

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image"/>
  <meta name="twitter:title"       content="<?= htmlspecialchars($metaTitle) ?>"/>
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDesc) ?>"/>
  <meta name="twitter:image"       content="<?= htmlspecialchars($metaImg) ?>"/>

  <!-- Structured Data: WebApplication -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "<?= htmlspecialchars($brand) ?>",
    "url": "https://<?= $domain ?>",
    "description": "Free temporary email service. Create disposable addresses instantly.",
    "applicationCategory": "UtilitiesApplication",
    "operatingSystem": "All",
    "inLanguage": ["en", "ru"],
    "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://<?= $domain ?>/?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>

  <!-- CSS -->
  <link rel="stylesheet" href="/assets/css/styles.css?v=2.0"/>

  <!-- SVG Favicon -->
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64'%3E%3Cdefs%3E%3CradialGradient id='g' cx='20%25' cy='20%25'%3E%3Cstop offset='0%25' stop-color='%236fe7ff'/%3E%3Cstop offset='100%25' stop-color='%23c056ff'/%3E%3C/radialGradient%3E%3C/defs%3E%3Crect rx='18' ry='18' width='64' height='64' fill='url(%23g)'/%3E%3Cpath d='M18 24h28v18H18z' fill='none' stroke='%23081218' stroke-width='4'/%3E%3Cpath d='M18 24l14 10 14-10' fill='none' stroke='%23081218' stroke-width='4'/%3E%3C/svg%3E"/>

  <!-- Google AdSense -->
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7442589230691601" crossorigin="anonymous"></script>

  <!-- Yandex Metrika -->
  <script>
    (function(m,e,t,r,i,k,a){
      m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
      m[i].l=1*new Date();
      for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
      k=e.createElement(t),a=e.getElementsByTagName(t)[0];
      k.async=1;k.src=r;a.parentNode.insertBefore(k,a);
    })(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');
    ym(94250304,'init',{webvisor:true,trackHash:true,clickmap:true,accurateTrackBounce:true,trackLinks:true});
  </script>

  <!-- Telegram WebApp SDK (enables Mini App mode) -->
  <script src="https://telegram.org/js/telegram-web-app.js"></script>
  <script>
    window.TEMAIL = {
      lang:    "<?= $lang ?>",
      brand:   "<?= htmlspecialchars($brand,   ENT_QUOTES) ?>",
      contact: "<?= htmlspecialchars($contact, ENT_QUOTES) ?>"
    };
    window.Telegram?.WebApp?.ready();
  </script>
</head>
<body>
<div class="bg-art" aria-hidden="true"></div>
<div class="container">

  <nav class="nav" role="navigation" aria-label="Main navigation">
    <a class="brand" href="/index.php?lang=<?= $lang ?>" aria-label="<?= htmlspecialchars($brand) ?> home">
      <span class="logo" aria-hidden="true">✉</span>
      <span><?= htmlspecialchars($brand) ?></span>
    </a>

    <div class="navlinks">
      <a href="/index.php?lang=<?= $lang ?>"   data-i18n="nav_home">Home</a>
      <a href="/blog.php?lang=<?= $lang ?>"    data-i18n="nav_blog">Blog</a>
      <a href="/privacy.php?lang=<?= $lang ?>" data-i18n="nav_privacy">Privacy</a>
      <a href="/terms.php?lang=<?= $lang ?>"   data-i18n="nav_terms">Terms</a>
      <a href="/contact.php?lang=<?= $lang ?>" data-i18n="nav_contact">Contact</a>
    </div>

    <div class="lang" role="group" aria-label="Language selector">
      <a class="pill <?= $lang === 'en' ? 'active' : '' ?>"
         href="<?= htmlspecialchars($path) ?>?lang=en" hreflang="en">EN</a>
      <a class="pill <?= $lang === 'ru' ? 'active' : '' ?>"
         href="<?= htmlspecialchars($path) ?>?lang=ru" hreflang="ru">RU</a>
    </div>
  </nav>
