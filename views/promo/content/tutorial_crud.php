<?php

declare(strict_types=1);

return [
    'slug' => 'crud',
    'title' => 'CRUD Kaynak Oluşturmak',
    'description' => 'make:resource ile tek komutta tam CRUD iskelesi.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP'in <code>make:resource</code> komutu, bir CRUD kaynağı için gereken her şeyi tek seferde üretir.</p>

    <h2>1. Resource oluşturun</h2>
    <pre><code>bin/dizge make:resource Product</code></pre>
    <p>Bu komut şunları üretir:</p>
    <ul>
      <li><code>app/Http/Controllers/ProductController.php</code> — tam CRUD metodları</li>
      <li><code>database/migrations/..._create_products.php</code> — CREATE TABLE migration</li>
      <li><code>views/products/index.php</code> — liste görünümü</li>
      <li><code>views/products/form.php</code> — oluşturma/düzenleme formu</li>
    </ul>
    <p>Komut ayrıca eklemeniz gereken route ve container binding kodlarını da ekrana yazdırır.</p>

    <h2>2. Binding'i kaydedin</h2>
    <p><code>routes/services.php</code> dosyasına:</p>
    <pre><code>app()-&gt;container()-&gt;singleton(ProductController::class,
    fn (Container $c): ProductController =&gt; new ProductController());</code></pre>

    <h2>3. Rotaları ekleyin</h2>
    <p><code>routes/web.php</code> dosyasına:</p>
    <pre><code>app()-&gt;get('/products', [ProductController::class, 'index']);
app()-&gt;get('/products/create', [ProductController::class, 'create']);
app()-&gt;post('/products', [ProductController::class, 'store']);
app()-&gt;get('/products/{id}', [ProductController::class, 'show']);
app()-&gt;get('/products/{id}/edit', [ProductController::class, 'edit']);
app()-&gt;post('/products/{id}', [ProductController::class, 'update']);
app()-&gt;post('/products/{id}/delete', [ProductController::class, 'destroy']);</code></pre>

    <h2>4. Migration'ı çalıştırın</h2>
    <pre><code>bin/dizge migrate up</code></pre>

    <h2>5. Test edin</h2>
    <p><code>http://localhost:8080/products</code> adresini ziyaret edin. Yeni ürün ekleyin, düzenleyin ve silin.</p>

    <h2>Üretilen controller yapısı</h2>
    <p>Controller şu metodları içerir:</p>
    <ul>
      <li><code>index()</code> — tüm kayıtları listeler, <code>dv('data-table')</code> ile render eder</li>
      <li><code>create()</code> — oluşturma formunu gösterir</li>
      <li><code>store()</code> — yeni kayıt oluşturur (CSRF korumalı)</li>
      <li><code>show()</code> — tek kayıt detayı</li>
      <li><code>edit()</code> — düzenleme formunu gösterir</li>
      <li><code>update()</code> — kaydı günceller (CSRF korumalı)</li>
      <li><code>destroy()</code> — kaydı siler (CSRF korumalı)</li>
    </ul>

    <p><a href="/tutorials/deployment" class="ck-button">Deployment →</a></p>
<?php return ob_get_clean(); }];
