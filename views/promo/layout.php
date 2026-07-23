<!doctype html>
<html lang="tr">
<head>
  <?php
    $siteUrl = rtrim((string) (getenv('DIZGECMS_SITE_URL') ?: 'https://dizgecms.digitaltamam.com'), '/');
    $canonicalPath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
    $canonicalUrl = $siteUrl . ($canonicalPath === '/' ? '/' : '/' . ltrim($canonicalPath, '/'));
  ?>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> &middot; DizgeCMS</title>
  <meta name="description" content="<?= e($description) ?>">
  <link rel="canonical" href="<?= e($canonicalUrl) ?>">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="DizgeCMS">
  <meta property="og:title" content="<?= e($title) ?> &middot; DizgeCMS">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($canonicalUrl) ?>">
  <meta name="twitter:card" content="summary">
  <script type="application/ld+json"><?= (string) json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'WebSite',
      'name' => 'DizgeCMS',
      'url' => $siteUrl,
      'description' => $description,
      'inLanguage' => 'tr-TR',
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Aleo:ital,wght@0,400;0,700;1,400;1,700&display=swap">
  <link rel="stylesheet" href="/assets/ckcss.min.css">
  <link rel="stylesheet" href="/assets/dizgepromo.css">
  <script>
    (function(){var t=localStorage.getItem('ck-theme');if(t)document.documentElement.dataset.ckTheme=t;})();
    function toggleTheme(){var d=document.documentElement;var c=d.dataset.ckTheme==='dark'?'light':'dark';d.dataset.ckTheme=c;localStorage.setItem('ck-theme',c);}
  </script>
</head>
<body>
  <a href="#main" class="ck-skip-link">İçeriğe atla</a>
  <header class="dp-header">
    <div class="ck-container">
      <nav class="dp-nav" aria-label="Ana menü">
        <a href="/" class="dp-brand">Dizge<span>CMS</span></a>
        <ul class="dp-nav-links">
          <li><a href="/docs"<?= $active === 'docs' ? ' aria-current="page"' : '' ?>>Doküman</a></li>
          <li><a href="/tutorials"<?= $active === 'tutorials' ? ' aria-current="page"' : '' ?>>Eğitim</a></li>
          <li><a href="/download"<?= $active === 'download' ? ' aria-current="page"' : '' ?>>İndir</a></li>
          <li><a href="/about"<?= $active === 'about' ? ' aria-current="page"' : '' ?>>Hakkında</a></li>
          <li><a href="/contact"<?= $active === 'contact' ? ' aria-current="page"' : '' ?>>İletişim</a></li>
        </ul>
        <button class="dp-theme-toggle" onclick="toggleTheme()" aria-label="Karanlık modu aç/kapat" title="Tema değiştir">&#9790;</button>
      </nav>
    </div>
  </header>
  <main id="main">
    <?= $slot ?>
  </main>
  <footer class="dp-footer">
    <div class="ck-container ck-cluster ck-cluster--spread">
      <p class="ck-text-small ck-text-muted">DizgeCMS &mdash; Build-free, plugin-ready publishing platform.</p>
      <nav aria-label="Alt menü">
        <ul class="dp-footer-links">
          <li><a href="https://github.com/kayacuneyd/dizgephp">GitHub</a></li>
          <li><a href="/docs">Doküman</a></li>
          <li><a href="/tutorials">Eğitim</a></li>
          <li><a href="/contact">İletişim</a></li>
        </ul>
      </nav>
    </div>
  </footer>
</body>
</html>
