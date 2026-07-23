<?php
declare(strict_types=1);
require dirname(__DIR__) . '/dizge.php';
use Dizge\Config;

function config_expect(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }

$dir = sys_get_temp_dir() . '/dizge-config-' . bin2hex(random_bytes(4));
mkdir($dir, 0700, true);
file_put_contents($dir . '/app.php', "<?php return ['name' => 'base'];\n");
file_put_contents($dir . '/modules.php', "<?php return ['cache' => false, 'jobs' => false];\n");
// "modules.local.php" sorts alphabetically before "modules.php" (glob() default order);
// the override must still win.
file_put_contents($dir . '/modules.local.php', "<?php return ['cache' => true];\n");

$config = new Config($dir);
config_expect($config->get('app.name') === 'base', 'Non-overridden config keys must load normally.');
config_expect($config->get('modules.cache') === true, '*.local.php must override its base file regardless of glob() order.');
config_expect($config->get('modules.jobs') === false, '*.local.php must merge into, not replace, its base file.');

array_map('unlink', glob($dir . '/*') ?: []);
rmdir($dir);
echo "DizgePHP config tests passed.\n";
