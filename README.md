
# TeMail.pro Web Interface (Guerrilla Mail + Mail.tm) — ZIP Package

## What’s included
- Responsive web interface (desktop + mobile)
- Providers:
  - Guerrilla Mail (no signup)
  - "Gmail-style" via Mail.tm (creates an account automatically)
- Functions: generate email → inbox → open message → delete → attachments (if available)
- Background refresh (keeps current address + inbox state in localStorage)
- Global notifications + sound + blinking icon on new mail
- Ad slots (top + bottom) ready for Google AdSense
- SEO-friendly pages: Home, Blog, Post, Privacy, Terms, Contact
- Blog with admin panel (flat-file JSON, no database)

## Install (cPanel)
1) Upload the folder contents to your domain root (public_html) so that:
   - /index.php exists
   - /assets, /api, /admin, /partials, /data, /uploads exist
2) Make sure PHP is enabled on the domain.
3) Set correct permissions:
   - /data and /uploads must be writable by PHP (usually 755 works; if not, 775)
4) Open: https://temail.pro/

## Blog admin
- URL: /admin/login.php
- Default username: admin
- Default password: change-me

IMPORTANT: Change the password:
Open `config/config.json` and replace `admin_pass_sha256` with SHA-256 of your new password.
You can generate SHA-256 quickly here in PHP:
```php
<?php echo hash('sha256', 'NEW_PASSWORD'); ?>
```

## AdSense
- Top slot: `index.php` block with id="adTop"
- Bottom slot: `index.php` block with id="adBottom"
Paste your AdSense code inside those blocks.

## Telegram Mini App (Web App) setup
You **do not** need to put your bot token into this website code.

Steps:
1) Open @BotFather in Telegram
2) Select your bot (e.g. @TeMail_pro_bot)
3) Use: /setdomain → set your domain (temail.pro)
4) Use: /setmenubutton → choose "Web App" and set URL:
   https://temail.pro/index.php
Now your bot will show a Web App button that opens this site inside Telegram.

Security note: keep your bot token secret. Do not embed it in public code or JS.

## Anti-bot / rate limiting
This package has **client-side** rate limit (max 5 "Generate" actions per minute).
For stronger protection, add server-side rate limiting via Cloudflare (recommended).

## Notes / Limitations
- "Mark read" is stored locally in your browser for UX; providers differ in how they track read status.
- Attachment availability depends on the provider and what their API returns.

Enjoy!
