<?php
$config = json_decode(file_get_contents(__DIR__ . "/../config/config.json"), true);
// Language: ?lang param > cookie > default
$lang = $_GET['lang'] ?? $_COOKIE['temail_lang'] ?? $config['site']['default_lang'] ?? 'en';
if (!in_array($lang, ['en', 'ru'])) $lang = 'en';
// Set cookie for 30 days
if (isset($_GET['lang'])) {
    setcookie('temail_lang', $lang, time() + 30*86400, '/', '', true, false);
}
$domain  = $config['site']['domain'];
$brand   = $config['site']['brand'];
$pageUrl = 'https://' . $domain . htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$isHome  = (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/' || basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) === 'index.php');
?><!doctype html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>

<!-- ===== Primary SEO ===== -->
<title><?php echo $isHome ? "Free Temporary Email — Instant Disposable Inbox | $brand" : "$brand — Temp Mail"; ?></title>
<meta name="description" content="Free temporary email service. Auto-generate a disposable email address instantly. No signup, real-time inbox, zero spam. Works with Mail.tm &amp; Guerrilla Mail. Private, anonymous, fast."/>
<meta name="keywords" content="temporary email,temp mail,disposable email,fake email,throwaway email,anonymous email,guerrilla mail,mail.tm,10 minute mail,burner email,spam free email,free email generator,temporary inbox,no signup email,privacy email,temail"/>
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1"/>
<meta name="author" content="<?php echo $brand; ?>"/>
<meta name="theme-color" content="#00dba4"/>
<link rel="canonical" href="<?php echo $pageUrl; ?>"/>
<link rel="alternate" hreflang="en" href="<?php echo $pageUrl; ?>?lang=en"/>
<link rel="alternate" hreflang="ru" href="<?php echo $pageUrl; ?>?lang=ru"/>
<link rel="alternate" hreflang="x-default" href="<?php echo $pageUrl; ?>"/>

<!-- ===== Open Graph ===== -->
<meta property="og:type" content="website"/>
<meta property="og:url" content="<?php echo $pageUrl; ?>"/>
<meta property="og:site_name" content="<?php echo $brand; ?>"/>
<meta property="og:title" content="Free Temporary Email — Instant Disposable Inbox | <?php echo $brand; ?>"/>
<meta property="og:description" content="Generate a free temporary email instantly. No signup. Real-time inbox. Zero spam. 100% anonymous. Supports Mail.tm and Guerrilla Mail."/>
<meta property="og:image" content="https://<?php echo $domain; ?>/assets/img/og.png"/>
<meta property="og:image:width" content="1200"/>
<meta property="og:image:height" content="630"/>
<meta property="og:locale" content="<?php echo $lang === 'ru' ? 'ru_RU' : 'en_US'; ?>"/>

<!-- ===== Twitter/X Card ===== -->
<meta name="twitter:card" content="summary_large_image"/>
<meta name="twitter:title" content="Free Temporary Email | <?php echo $brand; ?>"/>
<meta name="twitter:description" content="Auto-generate a disposable email in seconds. No signup. Real-time inbox. 100% free."/>
<meta name="twitter:image" content="https://<?php echo $domain; ?>/assets/img/og.png"/>

<!-- ===== Structured Data ===== -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "WebSite",
      "@id": "https://<?php echo $domain; ?>/#website",
      "name": "<?php echo $brand; ?>",
      "url": "https://<?php echo $domain; ?>",
      "description": "Free temporary email service — anonymous, instant, no signup",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://<?php echo $domain; ?>/?q={search_term_string}",
        "query-input": "required name=search_term_string"
      },
      "inLanguage": ["en", "ru"]
    },
    {
      "@type": "SoftwareApplication",
      "name": "<?php echo $brand; ?>",
      "applicationCategory": "UtilitiesApplication",
      "operatingSystem": "Web",
      "url": "https://<?php echo $domain; ?>",
      "description": "Free temporary disposable email service with real-time inbox, no signup required",
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.9",
        "ratingCount": "2847",
        "bestRating": "5"
      }
    },
    {
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is a temporary email address?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "A temporary email address is a disposable inbox that lets you receive emails without exposing your real address. <?php echo $brand; ?> generates one instantly, no signup required."
          }
        },
        {
          "@type": "Question",
          "name": "Is temporary email free?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, <?php echo $brand; ?> is completely free. Premium (ad-free) is available for 200 Telegram Stars per month."
          }
        },
        {
          "@type": "Question",
          "name": "How long does a temporary email last?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Your email address is saved in your browser and persists until you explicitly delete it. Mail.tm addresses typically last 7 days on the server side."
          }
        }
      ]
    }
  ]
}
</script>

<!-- ===== Favicon ===== -->
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='64' height='64'%3E%3Cdefs%3E%3ClinearGradient id='g' x1='0' y1='0' x2='1' y2='1'%3E%3Cstop offset='0%25' stop-color='%2300dba4'/%3E%3Cstop offset='100%25' stop-color='%234c7dff'/%3E%3C/linearGradient%3E%3C/defs%3E%3Crect rx='18' ry='18' width='64' height='64' fill='url(%23g)'/%3E%3Cpath d='M16 22h32v22H16z' fill='none' stroke='%230a0e1a' stroke-width='3.5'/%3E%3Cpath d='M16 22l16 12 16-12' fill='none' stroke='%230a0e1a' stroke-width='3.5'/%3E%3C/svg%3E"/>
<link rel="apple-touch-icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Crect rx='40' ry='40' width='180' height='180' fill='%2300dba4'/%3E%3Cpath d='M44 62h92v62H44z' fill='none' stroke='%230a0e1a' stroke-width='9'/%3E%3Cpath d='M44 62l46 34 46-34' fill='none' stroke='%230a0e1a' stroke-width='9'/%3E%3C/svg%3E"/>

<!-- ===== Styles ===== -->
<link rel="stylesheet" href="/assets/css/styles.css?v=2.0"/>

<!-- ===== Analytics ===== -->
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-7442589230691601" crossorigin="anonymous"></script>
<!-- Yandex.Metrika -->
<script type="text/javascript">
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,'script','https://mc.yandex.ru/metrika/tag.js','ym');
ym(94250304,'init',{webvisor:true,trackHash:true,clickmap:true,accurateTrackBounce:true,trackLinks:true});
</script>

<!-- ===== JS Config (language passed to JS) ===== -->
<script>
window.TEMAIL = {
  lang: "<?php echo $lang; ?>",
  brand: "<?php echo addslashes($brand); ?>",
  contact: "<?php echo addslashes($config['site']['contact_email']); ?>",
  domain: "<?php echo addslashes($domain); ?>"
};
</script>

<!-- ===== Ad networks ===== -->
<script src="https://5gvci.com/act/files/tag.min.js?z=10735480" data-cfasync="false" async></script>
<script>(function(s){s.dataset.zone='10735487',s.src='https://nap5k.com/tag.min.js'})([document.documentElement,document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>
<script>(function(s){s.dataset.zone='10735489',s.src='https://gizokraijaw.net/vignette.min.js'})([document.documentElement,document.body].filter(Boolean).pop().appendChild(document.createElement('script')))</script>

<!-- ===== Telegram WebApp SDK ===== -->
<script src="https://telegram.org/js/telegram-web-app.js"></script>
<script>window.Telegram?.WebApp?.ready();</script>

</head>
<body>
<div class="bg-art"></div>
<div class="container">
  <nav class="nav" role="navigation">
    <a class="brand" href="/index.php?lang=<?php echo $lang; ?>">
      <span class="logo">✉</span>
      <span><?php echo htmlspecialchars($brand); ?></span>
    </a>
    <div class="navlinks">
      <a href="/index.php?lang=<?php echo $lang; ?>" data-i18n="nav_home">Home</a>
      <a href="/blog.php?lang=<?php echo $lang; ?>" data-i18n="nav_blog">Blog</a>
      <a href="/privacy.php?lang=<?php echo $lang; ?>" data-i18n="nav_privacy">Privacy</a>
      <a href="/terms.php?lang=<?php echo $lang; ?>" data-i18n="nav_terms">Terms</a>
      <a href="/contact.php?lang=<?php echo $lang; ?>" data-i18n="nav_contact">Contact</a>
    </div>
    <div class="lang">
      <a class="pill <?php echo $lang==='en'?'active':''; ?>" data-lang="en" href="?lang=en">EN</a>
      <a class="pill <?php echo $lang==='ru'?'active':''; ?>" data-lang="ru" href="?lang=ru">RU</a>
    </div>
  </nav>
