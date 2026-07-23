<?php

declare(strict_types=1);

return [
    'slug' => 'devinim',
    'title' => 'DevinimJS Bridge',
    'description' => 'Sunucu-client durum düzeltme protokolü ve dv() bileşen sistemi.',
    'body' => function (): string { ob_start(); ?>

    <p>DevinimJS, DizgePHP'in tarayıcı etkileşim katmanıdır. Custom element'ler sunucu tarafında <code>dv()</code> ile render edilir; gerektiğinde sunucu, bridge protokolü ile client state'ini düzeltir.</p>

    <h2><code>dv()</code> ile bileşen render</h2>

    <pre><code>&lt;?= dv('counter', start: 3, step: 1) ?&gt;
&lt;?= dv('data-table', columns: ['ID', 'Ad'], rows: $rows, label: 'Liste') ?&gt;
&lt;?= dv('field', id: 'email', name: 'email', label: 'E-posta', control: 'input', attrs: ['type' =&gt; 'email']) ?&gt;</code></pre>

    <p><code>dv()</code> tüm prop değerlerini otomatik kaçırır. Bileşen ismi allowlist ile doğrulanır.</p>

    <h2>Bridge protokolü</h2>

    <p>Client tarafında yapılan bir değişikliğin sunucu tarafından düzeltilmesi gerektiğinde:</p>

    <pre><code>dv_bridge('counter', '#my-counter', 'change', function (array $detail): ?Patch {
    $count = (int) ($detail['count'] ?? 0);
    return $count &gt; 100 ? dv_patch(['start' =&gt; 100]) : null;
});</code></pre>

    <p>Bridge handler'ı <code>null</code> döndürürse düzeltme yapılmaz. <code>dv_patch([...])</code> ile client state'i güncellenir.</p>

    <h2>Bridge script'leri</h2>

    <pre><code>&lt;?= dv_bridge_scripts() ?&gt;</code></pre>

    <p>Bu çağrı bridge endpoint'ini, CSRF token'ını ve tüm binding'leri JSON olarak sayfaya gömer, ardından bridge istemcisini yükler.</p>

    <p><a href="/docs/cli" class="ck-button">CLI Araçları →</a></p>
<?php return ob_get_clean(); }];
