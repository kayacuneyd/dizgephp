<?php declare(strict_types=1); $title = 'Hakkında'; $description = 'DizgePHP felsefesi: neden build-free, neden shared-hosting, ADR kültürü ve güvenlik öncelikli tasarım.'; $active = 'about'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:3rem">
    <article class="dp-prose">
      <p class="dp-kicker">FELSEFE</p>
      <h1>Neden build-free?</h1>

      <p>DizgePHP, 2026'da bir PHP framework'ünün <strong>build adımı olmadan</strong> da production kalitesinde olabileceğini kanıtlamak için yazıldı.</p>

      <p>Modern PHP ekosistemi Composer, Node.js, Webpack, Vite ve sayısız araç zinciriyle çevrili. Bunlar büyük ekipler için değerli; ama küçük projeler, kişisel siteler ve shared hosting kullanıcıları için <strong>gereksiz karmaşıklık</strong> yaratıyor.</p>

      <h2>Shared hosting yaşıyor</h2>
      <p>Dünyadaki web sitelerinin büyük çoğunluğu hala shared hosting'de. cPanel, Apache, PHP — bu üçlü hala web'in belkemiği. DizgePHP bu gerçeği kabul eder ve ona göre tasarlanmıştır:</p>
      <ul>
        <li>Tek bir Git checkout veya ZIP indirme ile kurulum</li>
        <li>SQLite varsayılan veritabanı — ayrı bir DB sunucusu gerekmez</li>
        <li>Cron ile job queue — supervisor veya systemd gerekmez</li>
        <li>PHP'nin <code>mail()</code> fonksiyonu ile e-posta — SMTP sunucusu gerekmez</li>
      </ul>

      <h2>AI-first tasarım</h2>
      <p>DizgePHP, LLM'lerin ve kod ajanlarının kolayca anlayabileceği şekilde tasarlanmıştır:</p>
      <ul>
        <li><code>bin/dizge inspect --json</code> ile tam bağlam dışa aktarımı</li>
        <li>Açık (explicit) rotalar, container binding'leri — reflection veya autowiring yok</li>
        <li>Her kararın bir ADR (Architecture Decision Record) kaydı var</li>
        <li>Her güvenlik katmanının bir threat model'i var</li>
      </ul>

      <h2>ADR kültürü</h2>
      <p>Her mimari karar <code>docs/adr/</code> altında kayıtlıdır. Bu, framework'ün neden böyle çalıştığını anlamak isteyen herkes için şeffaf bir geçmiş sağlar. Kararlar sürpriz değil, belgeli ve gerekçelidir.</p>

      <h2>Önce güvenlik</h2>
      <p>DizgePHP'te güvenlik sonradan eklenen bir katman değil, çekirdeğin bir parçasıdır:</p>
      <ul>
        <li>CSRF koruması tüm state-changing rotalarda otomatik</li>
        <li>Yetkilendirme (Gate) deny-by-default — tanımlanmayan yetki reddedilir</li>
        <li>Query builder tüm değerleri parametrize eder — SQL injection kapısı yok</li>
        <li>auth, data, api, bridge, media, operations — her modül için threat model</li>
      </ul>

      <h2>CKCSS + DevinimJS</h2>
      <p>DizgePHP yalnız değil. Aynı build-free felsefeyle yazılmış iki kardeş proje ile çalışır:</p>
      <ul>
        <li><strong>CKCSS</strong> — İznik çinisi renk paleti, Aleo tipografi, karanlık mod. CSS değişkenleri ile temalandırılabilir.</li>
        <li><strong>DevinimJS</strong> — Vanilla JS custom element'ler. Build-free, class API, bridge protokolü ile sunucu-client düzeltme.</li>
      </ul>

      <p>Üçü birlikte, modern web'in tüm katmanlarını build-free olarak kapsar.</p>
    </article>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
