<?php

declare(strict_types=1);

return [
    'slug' => 'deployment',
    'title' => 'Deployment',
    'description' => 'DizgePHP uygulamasını shared hosting veya VPS\'e dağıtma.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP, <strong>shared hosting</strong> için özel olarak tasarlanmıştır. Composer, Node.js veya build adımı gerektirmez.</p>

    <h2>GitHub Release ile kurulum</h2>

    <ol>
      <li><a href="https://github.com/kayacuneyd/dizgephp/releases">GitHub Releases</a> sayfasından <code>dizgephp-vX.Y.Z.zip</code> dosyasını indirin</li>
      <li>ZIP'i hosting hesabınıza (document root dışına) açın</li>
      <li>Hosting panelinden document root'u <code>public/</code> dizinine yönlendirin</li>
      <li><code>.env.example</code> dosyasını <code>.env</code> olarak kopyalayıp yapılandırın</li>
      <li><code>bin/dizge verify</code> ile kurulumu doğrulayın</li>
    </ol>

    <h2>Git ile kurulum</h2>

    <pre><code>git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app
cp .env.example .env
bin/dizge verify</code></pre>

    <h2>Apache yapılandırması</h2>

    <pre><code>&lt;VirtualHost *:80&gt;
    DocumentRoot /var/www/my-app/public
    &lt;Directory /var/www/my-app/public&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;</code></pre>

    <p><code>public/.htaccess</code> URL rewriting, güvenlik başlıkları ve asset cache'leme kurallarını içerir.</p>

    <h2>Nginx yapılandırması</h2>

    <pre><code>server {
    root /var/www/my-app/public;
    index index.php;
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}</code></pre>

    <h2>Cron job'lar</h2>

    <p>Job kuyruğunu shared hosting cron'u ile işleyin:</p>

    <pre><code>* * * * * /var/www/my-app/bin/dizge job:work 25 &gt;/dev/null 2&gt;&amp;1</code></pre>

    <h2>Ortam değişkenleri</h2>

    <table class="ck-table-wrap" style="margin:1.5rem 0">
      <table class="ck-table">
        <thead><tr><th>Değişken</th><th>Açıklama</th></tr></thead>
        <tbody>
          <tr><td><code>DIZGE_ENV</code></td><td>production / development</td></tr>
          <tr><td><code>DIZGE_DEBUG</code></td><td>1 = hata detayları gösterilir</td></tr>
          <tr><td><code>DIZGE_DB_DSN</code></td><td>PDO DSN (varsayılan: SQLite)</td></tr>
          <tr><td><code>DIZGE_DB_USER</code></td><td>Veritabanı kullanıcısı</td></tr>
          <tr><td><code>DIZGE_DB_PASSWORD</code></td><td>Veritabanı şifresi</td></tr>
        </tbody>
      </table>
    </table>

    <p><a href="/docs" class="ck-button">← Dokümantasyon ana sayfa</a></p>
<?php return ob_get_clean(); }];
