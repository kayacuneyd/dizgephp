<?php

declare(strict_types=1);

return [
    'slug' => 'database',
    'title' => 'Veritabanı ve Query Builder',
    'description' => 'db()->table() query builder, migration sistemi ve PDO boundary.',
    'body' => function (): string { ob_start(); ?>

    <p>DizgePHP, ORM yerine <strong>PDO tabanlı</strong> bir query builder kullanır. Varsayılan veritabanı <strong>SQLite</strong>'tır; <code>DIZGE_DB_DSN</code> ile MySQL veya PostgreSQL'e geçilebilir.</p>

    <h2>Temel sorgulama</h2>

    <pre><code>$tasks = db()-&gt;table('tasks')
    -&gt;where('done', 0)
    -&gt;orderBy('id', 'DESC')
    -&gt;limit(20)
    -&gt;get();</code></pre>

    <h2>Kayıt ekleme</h2>

    <pre><code>$id = db()-&gt;table('tasks')-&gt;insert([
    'title' =&gt; 'Yeni görev',
    'done' =&gt; 0,
    'created_at' =&gt; date('c'),
]);</code></pre>

    <h2>Kayıt güncelleme</h2>

    <pre><code>db()-&gt;table('tasks')
    -&gt;where('id', $id)
    -&gt;update(['done' =&gt; 1]);</code></pre>

    <blockquote><code>update()</code> ve <code>delete()</code> bir <code>where()</code> koşulu <strong>gerektirir</strong>. Koşulsuz çağrılar hata verir.</blockquote>

    <h2>Kayıt silme</h2>

    <pre><code>db()-&gt;table('tasks')
    -&gt;where('id', $id)
    -&gt;delete();</code></pre>

    <h2>Migration</h2>

    <pre><code>bin/dizge make:migration create_tasks_table
# database/migrations/202607231236_create_tasks_table.php oluşturulur</code></pre>

    <p>Migration dosyası <code>up(Database $database)</code> ve <code>down(Database $database)</code> metodlarını içerir:</p>

    <pre><code>public function up(Database $database): void
{
    $database-&gt;pdo()-&gt;exec('CREATE TABLE tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        done INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL
    )');
}</code></pre>

    <pre><code>bin/dizge migrate up    # tüm bekleyen migration'ları uygular
bin/dizge migrate down  # son migration'ı geri alır</code></pre>

    <p><a href="/docs/devinim" class="ck-button">DevinimJS Bridge →</a></p>
<?php return ob_get_clean(); }];
