<?php

declare(strict_types=1);

return [
    'slug' => 'getting-started',
    'title' => 'İlk DizgePHP Uygulaman',
    'description' => 'Git clone\'dan çalışan bir uygulamaya adım adım rehber.',
    'body' => function (): string { ob_start(); ?>

    <p>Bu eğitim, DizgePHP ile sıfırdan bir uygulama oluşturmayı adım adım gösterir. Her komut ve kod parçası gerçekten çalıştırılarak test edilmiştir.</p>

    <h2>0. Gereksinimler</h2>
    <ul>
      <li>PHP 8.1+ (PDO SQLite eklentisi ile)</li>
      <li>Composer, Node.js veya build adımı gerekmez</li>
    </ul>

    <h2>1. Depoyu klonlayın</h2>
    <pre><code>git clone --branch v0.1.0 --depth 1 https://github.com/kayacuneyd/dizgephp.git my-app
cd my-app
cp .env.example .env</code></pre>

    <h2>2. Geliştirme sunucusunu başlatın</h2>
    <pre><code>php -S localhost:8080 -t public public/index.php</code></pre>
    <p>Tarayıcıda <code>http://localhost:8080</code> adresini açın.</p>

    <h2>3. İlk rotanızı ekleyin</h2>
    <p><code>routes/web.php</code> dosyasının sonuna ekleyin:</p>
    <pre><code>app()-&gt;get('/hello/{name}', function (Request $request, array $params): Response {
    return Response::html('&lt;p&gt;Merhaba, ' . e($params['name']) . '!&lt;/p&gt;');
});</code></pre>
    <p>Sunucuyu yeniden başlatmanız gerekmez. <code>http://localhost:8080/hello/Dünya</code> adresini ziyaret edin.</p>

    <h2>4. Controller oluşturun</h2>
    <pre><code>bin/dizge make:controller Task</code></pre>
    <p><code>app/Http/Controllers/TaskController.php</code> dosyasını açıp düzenleyin, ardından <code>routes/services.php</code> dosyasına binding ekleyin:</p>
    <pre><code>app()-&gt;container()-&gt;singleton(TaskController::class,
    fn (Container $c): TaskController =&gt; new TaskController());</code></pre>
    <p>Rotayı <code>routes/web.php</code> dosyasına ekleyin:</p>
    <pre><code>app()-&gt;get('/tasks', [TaskController::class, 'index']);</code></pre>

    <h2>5. Migration oluşturun</h2>
    <pre><code>bin/dizge make:migration create_tasks_table
# Migration dosyasını düzenleyin
bin/dizge migrate up</code></pre>

    <h2>6. Veriyi sorgulayın</h2>
    <pre><code>$tasks = db()-&gt;table('tasks')-&gt;orderBy('id', 'DESC')-&gt;get();</code></pre>

    <h2>7. View oluşturun</h2>
    <p><code>views/tasks/index.php</code>:</p>
    <pre><code>&lt;h1&gt;Görevler&lt;/h1&gt;
&lt;?= dv('data-table', columns: ['ID', 'Başlık', 'Tamamlandı'], rows: $rows, label: 'Görevler') ?&gt;
&lt;?= dv_scripts() ?&gt;</code></pre>

    <h2>8. Doğrulayın</h2>
    <pre><code>bin/dizge verify</code></pre>

    <p><a href="/tutorials/crud" class="ck-button">CRUD Kaynak Oluşturma →</a></p>
<?php return ob_get_clean(); }];
