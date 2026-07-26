<?php

declare(strict_types=1);

return [
    'slug' => 'deployment',
    'title' => 'Shared Hosting\'e Deployment',
    'description' => 'DizgePHP uygulamasını cPanel veya benzeri shared hosting\'e yükleme.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP shared hosting için tasarlanmıştır. Bu eğitim, tipik bir cPanel hosting'e deployment sürecini adım adım gösterir.</p>

    <h2>1. Uygulamayı hazırlayın</h2>
    <pre><code>bin/dizge verify     # her şey yeşil mi?
bin/dizge migrate up # migration'ları uygulayın</code></pre>

    <h2>2. GitHub Release oluşturun</h2>
    <p>Uygulamanızı GitHub'a push'layın ve bir release oluşturun. Alternatif olarak doğrudan ZIP olarak indirin.</p>

    <h2>3. Hosting'e yükleyin</h2>
    <ol>
      <li>cPanel → Dosya Yöneticisi → <code>public_html</code> <strong>dışında</strong> bir dizine (ör: <code>/home/user/dizgephp</code>) ZIP'i açın</li>
      <li><code>.env.example</code> → <code>.env</code> olarak kopyalayın</li>
      <li><code>.env</code> dosyasında ortam değişkenlerini ayarlayın</li>
    </ol>

    <h2>4. Document root'u ayarlayın</h2>
    <p>Hosting panelinden document root'u <code>public/</code> dizinine yönlendirin. cPanel için:</p>
    <ul>
      <li>Addon Domain veya Subdomain oluşturun</li>
      <li>Document Root: <code>/home/user/dizgephp/public</code></li>
    </ul>

    <h2>5. .htaccess kontrolü</h2>
    <p><code>public/.htaccess</code> dosyası zaten gereken tüm kuralları içerir: URL rewriting, güvenlik başlıkları, asset cache.</p>

    <h2>6. Cron job ekleyin</h2>
    <p>cPanel → Cron Jobs:</p>
    <pre><code>* * * * * /home/user/dizgephp/bin/dizge job:work 25 &gt;/dev/null 2&gt;&amp;1</code></pre>

    <h2>7. Test edin</h2>
    <p>Alan adınızı ziyaret edin. Her şey çalışıyorsa anasayfayı görmelisiniz.</p>

    <h2>Sorun giderme</h2>
    <ul>
      <li><strong>500 hatası:</strong> <code>.env</code> dosyasında <code>DIZGE_DEBUG=1</code> ayarlayıp hatayı görün</li>
      <li><strong>PHP sürümü:</strong> Hosting panelinden PHP 8.1+ seçin</li>
      <li><strong>SQLite:</strong> PDO SQLite eklentisinin aktif olduğundan emin olun</li>
      <li><strong>mod_rewrite:</strong> Apache'de <code>mod_rewrite</code> açık olmalı</li>
    </ul>

    <p><a href="/tutorials" class="ck-button">← Tüm eğitimler</a></p>
<?php return ob_get_clean(); }];
