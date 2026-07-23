<?php

declare(strict_types=1);

return [
    'slug' => 'views',
    'title' => 'Görünümler ve Şablonlar',
    'description' => 'DizgePHP view sistemi, e() kaçış fonksiyonu ve dv() bileşenleri.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP view sistemi <strong>PHP tabanlıdır</strong>. Şablonlar <code>views/</code> dizininde yer alır, veriler <code>extract()</code> ile aktarılır.</p>

    <h2>View render etmek</h2>

    <pre><code>return view('tasks/index.php', ['rows' =&gt; $rows, 'title' =&gt; 'Görevler']);</code></pre>

    <h2>Güvenli çıktı: <code>e()</code></h2>

    <p><strong>Her kullanıcı kaynaklı değer</strong> HTML'e yazılmadan önce <code>e()</code> ile kaçırılmalıdır:</p>

    <pre><code>&lt;h1&gt;&lt;?= e($title) ?&gt;&lt;/h1&gt;
&lt;p&gt;&lt;?= e($user['name']) ?&gt;&lt;/p&gt;</code></pre>

    <p><code>e()</code> UTF-8, <code>ENT_QUOTES</code> ve <code>ENT_SUBSTITUTE</code> ile <code>htmlspecialchars()</code> uygular.</p>

    <h2>DevinimJS bileşenleri: <code>dv()</code></h2>

    <p>Sunucu tarafında render edilen custom element'ler:</p>

    <pre><code>&lt;?= dv('data-table', columns: ['ID', 'Başlık', 'Durum'], rows: $rows, label: 'Görevler') ?&gt;
&lt;?= dv('counter', start: 10, step: 5) ?&gt;
&lt;?= dv('field', id: 'title', name: 'title', label: 'Başlık', control: 'input') ?&gt;</code></pre>

    <p><strong>Önemli:</strong> <code>dv()</code> prop değerlerini <strong>otomatik kaçırır</strong>. <code>dv()</code> içinde <code>e()</code> kullanmayın — çift kaçırmaya yol açar.</p>

    <h2>Bileşen script'leri</h2>

    <pre><code>&lt;?= dv_scripts() ?&gt;
&lt;?= dv_bridge_scripts() ?&gt;</code></pre>

    <ul>
      <li><code>dv_scripts()</code> — sayfada kullanılan her bileşen için bir <code>&lt;script type="module"&gt;</code> etiketi üretir (tekilleştirilmiş)</li>
      <li><code>dv_bridge_scripts()</code> — sunucu-client durum düzeltme köprüsünü kurar</li>
    </ul>

    <h2>CSRF koruması</h2>

    <p>State-changing formlarda <code>csrf_field()</code> kullanın:</p>

    <pre><code>&lt;form method="post" action="/contact"&gt;
  &lt;?= csrf_field() ?&gt;
  &lt;!-- form alanları --&gt;
  &lt;button type="submit"&gt;Gönder&lt;/button&gt;
&lt;/form&gt;</code></pre>

    <p><a href="/docs/security" class="ck-button">Güvenlik →</a></p>
<?php return ob_get_clean(); }];
