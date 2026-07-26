<?php

declare(strict_types=1);

return [
    'slug' => 'cli',
    'title' => 'CLI Araçları',
    'description' => 'bin/dizge komut satırı arayüzü ve geliştirme araçları.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP tek bir CLI giriş noktası sunar: <code>bin/dizge</code>. Tüm geliştirme ve yönetim işlemleri buradan yapılır.</p>

    <h2>Komut listesi</h2>

    <table class="ck-table-wrap" style="margin:1.5rem 0">
      <table class="ck-table">
        <thead><tr><th>Komut</th><th>Açıklama</th></tr></thead>
        <tbody>
          <tr><td><code>verify</code></td><td>Tüm .php dosyalarını lint'ler ve test suite'i çalıştırır</td></tr>
          <tr><td><code>benchmark</code></td><td>Performans benchmark'larını çalıştırır</td></tr>
          <tr><td><code>migrate [up|down]</code></td><td>Migration'ları uygular/geri alır</td></tr>
          <tr><td><code>route:list</code></td><td>Kayıtlı tüm rotaları listeler</td></tr>
          <tr><td><code>inspect [--json|--markdown]</code></td><td>Framework durumunu dışa aktarır</td></tr>
          <tr><td><code>assets:check</code></td><td>DevinimJS bileşenlerinin yayınlandığını doğrular</td></tr>
          <tr><td><code>make:controller Ad</code></td><td>Controller sınıfı üretir</td></tr>
          <tr><td><code>make:action Ad</code></td><td>Action sınıfı üretir</td></tr>
          <tr><td><code>make:migration ad</code></td><td>Migration dosyası üretir</td></tr>
          <tr><td><code>make:resource Ad</code></td><td>Tam CRUD iskelesi (controller + migration + views)</td></tr>
          <tr><td><code>module:enable ad</code></td><td>Modül etkinleştirir (cache|jobs|mail|api_tokens)</td></tr>
          <tr><td><code>job:work [adet]</code></td><td>Kuyruktaki job'ları işler</td></tr>
          <tr><td><code>cache:gc</code></td><td>Süresi geçmiş cache girdilerini temizler</td></tr>
          <tr><td><code>token:issue konu isim [yetki,...]</code></td><td>API token'ı oluşturur (bir kere gösterilir)</td></tr>
        </tbody>
      </table>
    </table>

    <h2>Geliştirme döngüsü</h2>

    <pre><code>bin/dizge make:resource Product   # CRUD iskelesi
# → routes/web.php ve routes/services.php'ye binding ekle
bin/dizge migrate up               # tabloyu oluştur
php -S localhost:8080 -t public    # geliştirme sunucusu
bin/dizge verify                   # her değişiklikten sonra</code></pre>

    <p><a href="/docs/deployment" class="ck-button">Deployment →</a></p>
<?php return ob_get_clean(); }];
