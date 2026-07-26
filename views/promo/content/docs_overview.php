<?php

declare(strict_types=1);

return [
    'slug' => 'overview',
    'title' => 'Genel Bakış',
    'description' => 'DizgePHP HTTP katmanı, request/response döngüsü ve temel mimari.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP, PHP 8.1+ ile yazılmış <strong>build-free</strong> bir full-stack framework'tür. Shared hosting'de çalışmak üzere tasarlanmıştır; Composer ve Node.js opsiyoneldir, zorunlu değildir.</p>

    <h2>Üçlü mimari</h2>

    <table class="ck-table-wrap" style="margin:1.5rem 0">
      <table class="ck-table">
        <thead><tr><th>Katman</th><th>Sahibi</th><th>Görevi</th></tr></thead>
        <tbody>
          <tr><td><strong>HTTP, güvenlik, veri</strong></td><td>DizgePHP</td><td>Rotalama, controller, validasyon, veritabanı, oturum, CSRF, yetkilendirme</td></tr>
          <tr><td><strong>Sunum</strong></td><td>CKCSS</td><td>CSS değişkenleri, layout, bileşen stilleri, İznik renk paleti</td></tr>
          <tr><td><strong>Tarayıcı etkileşimi</strong></td><td>DevinimJS</td><td>Custom element'ler, form doğrulama, veri tablosu, bridge protokolü</td></tr>
        </tbody>
      </table>
    </table>

    <h2>Request döngüsü</h2>

    <ol>
      <li><code>public/index.php</code> → <code>dizge.php</code> ile uygulama başlatılır</li>
      <li><code>routes/web.php</code> (HTML) veya <code>routes/api.php</code> (JSON) okunur</li>
      <li>URL ile eşleşen ilk rota çalıştırılır</li>
      <li>State-changing isteklere (POST, PUT, DELETE) otomatik CSRF kontrolü yapılır</li>
      <li>Controller/closure <strong>mutlaka</strong> bir <code>Dizge\Http\Response</code> döndürür</li>
      <li><code>Response::html()</code>, <code>Response::json()</code>, <code>Response::redirect()</code> veya <code>Response::notFound()</code></li>
    </ol>

    <h2>Temel kavramlar</h2>

    <ul>
      <li><strong>Rotalar açık ve sıralıdır.</strong> Dosya-tabanlı veya anotasyon-tabanlı routing yoktur.</li>
      <li><strong>Container binding'leri el iledir.</strong> Autowiring veya reflection yoktur.</li>
      <li><strong>PDO ve query builder</strong> ORM yerine kullanılır. Her değer parametrize edilir.</li>
      <li><strong>Migration</strong> dosyaları <code>database/migrations/</code> altında sıralı uygulanır.</li>
      <li><strong>Modüller</strong> (<code>config/modules.php</code>): cache, jobs, mail, api_tokens.</li>
    </ul>

    <p><a href="/docs/routing" class="ck-button">Rotalama →</a></p>
<?php return ob_get_clean(); }];
