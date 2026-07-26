<?php declare(strict_types=1); $title = 'DizgeCMS hakkında'; $description = 'DizgeCMS felsefesi: shared hosting, build-free kurulum ve güvenlik öncelikli açık kaynak CMS.'; $active = 'about'; ob_start(); ?>
<section class="dp-section">
  <div class="ck-container ck-stack" style="--ck-stack-space:3rem">
    <article class="dp-prose">
      <p class="dp-kicker">FELSEFE</p>
      <h1>Neden DizgeCMS?</h1>

      <p>DizgeCMS, WordPress’in erişilebilir yayın deneyimini <strong>build adımı olmadan</strong>, shared hostingte ve geliştirici dostu API sınırlarıyla sunmak için tasarlandı.</p>

      <p>Modern PHP ekosistemi Composer, Node.js, Webpack, Vite ve sayısız araç zinciriyle çevrili. Bunlar büyük ekipler için değerli; ama küçük projeler, kişisel siteler ve shared hosting kullanıcıları için <strong>gereksiz karmaşıklık</strong> yaratabiliyor. DizgeCMS, bu gereksinimi azaltırken profesyonel içerik araçlarından vazgeçmez.</p>

      <h2>Shared hosting yaşıyor</h2>
      <p>cPanel, Apache ve PHP hâlâ pek çok proje için yeterli çalışma zemini. DizgeCMS bu gerçeği kabul eder ve ona göre tasarlanmıştır:</p>
      <ul>
        <li>Tek bir Git checkout veya ZIP indirme ile kurulum</li>
        <li>SQLite varsayılan veritabanı — ayrı bir DB sunucusu gerekmez</li>
        <li>Cron ile job queue — supervisor veya systemd gerekmez</li>
        <li>PHP'nin <code>mail()</code> fonksiyonu ile e-posta — SMTP sunucusu gerekmez</li>
      </ul>

      <h2>İçerik ekibi ve geliştirici aynı yerde</h2>
      <p>Studio; yazı, sayfa, medya, menü, site kimliği ve yayın akışını içerik ekibi için erişilebilir kılar. Geliştirici tarafında REST API, tema ve eklenti sınırları aynı içeriği özel deneyimlere taşır:</p>
      <ul>
        <li>Taslak, inceleme, onay ve planlı yayın akışı</li>
        <li>Kapak görseli, alt metin ve açıklama içeren medya kütüphanesi</li>
        <li>Site ayarları, birincil/alt menüler ve sosyal bağlantılar</li>
        <li>DevinimJS veya tercih edilen frontend için versioned public API</li>
      </ul>

      <h2>Açık kaynak ve geliştirilebilirlik</h2>
      <p>Kurulum paketi, CLI komutları, tema manifestleri, eklenti manifestleri ve mimari karar kayıtları repoda yer alır. Amaç kapalı bir panel üretmek değil; sürdürülebilir biçimde genişletilebilen bir yayın platformu sağlamaktır.</p>

      <h2>Önce güvenlik</h2>
      <p>DizgeCMS’te güvenlik sonradan eklenen bir katman değil, çekirdeğin bir parçasıdır:</p>
      <ul>
        <li>CSRF koruması tüm state-changing rotalarda otomatik</li>
        <li>Yetkilendirme (Gate) deny-by-default — tanımlanmayan yetki reddedilir</li>
        <li>Query builder tüm değerleri parametrize eder — SQL injection kapısı yok</li>
        <li>Preview tokenları ve API origin’leri için açık izin sınırları</li>
      </ul>

      <h2>DizgePHP + DevinimJS</h2>
      <p>DizgeCMS, build-free felsefeyle yazılmış iki bileşenden yararlanır:</p>
      <ul>
        <li><strong>CKCSS</strong> — İznik çinisi renk paleti, Aleo tipografi, karanlık mod. CSS değişkenleri ile temalandırılabilir.</li>
        <li><strong>DevinimJS</strong> — Vanilla JS custom element'ler. Build-free, class API, bridge protokolü ile sunucu-client düzeltme.</li>
      </ul>

      <p>Bu bileşenler birlikte, shared hosting sınırları içinde modern bir içerik deneyimi kurar.</p>
    </article>
  </div>
</section>
<?php $slot = ob_get_clean(); require __DIR__ . '/layout.php';
