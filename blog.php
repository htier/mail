<?php include __DIR__ . "/partials/header.php"; ?>
<div class="hero" style="padding-bottom:0">
  <h1 style="margin-bottom:0">Blog</h1>
  <p class="sub">Guides, updates, and privacy tips.</p>
</div>

<div class="panel">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">
    <div style="color:var(--muted)">Showing latest posts</div>
    <a class="btn secondary" href="/admin/login.php?lang=<?php echo $lang; ?>">Login</a>
  </div>
  <div class="postgrid" id="blogPosts"></div>
</div>

<?php include __DIR__ . "/partials/footer.php"; ?>
