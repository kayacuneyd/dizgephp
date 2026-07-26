<?php
declare(strict_types=1);
function installer_expect(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$root=dirname(__DIR__); $dir=sys_get_temp_dir().'/dizge-install-'.bin2hex(random_bytes(4)); mkdir($dir,0700,true); $db=$dir.'/app.sqlite';
$cmd='DIZGE_DB_DSN='.escapeshellarg('sqlite:'.$db).' DIZGE_ADMIN_PASSWORD='.escapeshellarg('installer-password-123').' php '.escapeshellarg($root.'/bin/dizge').' install admin@example.test 2>&1';
exec($cmd,$out,$status); installer_expect($status===0,'Installer must succeed on an empty SQLite database: '.implode("\n",$out));
$pdo=new PDO('sqlite:'.$db); installer_expect((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'Installer must create one admin.'); installer_expect((int)$pdo->query('SELECT COUNT(*) FROM content_items WHERE type = "page"')->fetchColumn()===4,'Installer must seed four default pages.'); installer_expect((int)$pdo->query('SELECT COUNT(*) FROM menu_items')->fetchColumn()===3,'Installer must seed primary navigation.');
exec($cmd,$out2,$status2); installer_expect($status2===0&&(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM content_items')->fetchColumn()===4,'Installer must be idempotent.');
foreach([$db,$db.'-wal',$db.'-shm'] as $f)if(is_file($f))unlink($f);rmdir($dir);echo "DizgeCMS installer tests passed.\n";
