<?php
declare(strict_types=1);
require __DIR__ . '/../admin/app/bootstrap.php';
set_error_handler(static function($level,$message,$file,$line){ throw new ErrorException($message,0,$level,$file,$line); });
$uid=(int)(getenv('APP_UID')?:1000); $gid=(int)(getenv('APP_GID')?:1000);
foreach (['data/audit','data/recovery','data/control','data/sessions','data/site/sessions','data/site/tmp','data/site/uploads','backups'] as $dir) {
    $path = BASE_PATH . '/' . $dir;
    if (!is_dir($path)) mkdir($path, 0700, true);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST) as $item) {
        if ($item->isLink()) {
            if ($dir==='backups') continue; // Historical snapshots may contain links; never follow or chown their targets.
            throw new RuntimeException('Unexpected symlink in app-owned storage.');
        }
        chown($item->getPathname(),$uid);chgrp($item->getPathname(),$gid);
    }
    chown($path, $uid); chgrp($path, $gid); chmod($path,0700);
}
foreach (['key' => random_bytes(32), 'setup-token' => bin2hex(random_bytes(24))] as $name => $value) {
    $file = BASE_PATH . '/data/control/' . $name;
    if (!file_exists($file)) { file_put_contents($file, $value); chmod($file,0600); }
    chown($file,$uid); chgrp($file,$gid);
}
$recovery=BASE_PATH.'/data/recovery';
if (!is_file($recovery.'/keypair')) {
    if (is_file($recovery.'/public.key')) throw new RuntimeException('Restore the missing recovery key; do not generate a replacement.');
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH.'/backups',FilesystemIterator::SKIP_DOTS)) as $backup) {
        if ($backup->isFile() && str_ends_with($backup->getFilename(),'.enc')) throw new RuntimeException('Encrypted backups exist. Import their recovery key before starting; a replacement would not unlock them.');
    }
    $pair=sodium_crypto_box_keypair();
    file_put_contents($recovery.'/keypair',$pair);file_put_contents($recovery.'/public.key',sodium_crypto_box_publickey($pair));
}
if (!hash_equals(sodium_crypto_box_publickey(file_get_contents($recovery.'/keypair')),file_get_contents($recovery.'/public.key'))) throw new RuntimeException('Recovery key files do not match. Restore the matching pair before starting.');
foreach (['keypair','public.key'] as $name) {chmod($recovery.'/'.$name,0600);chown($recovery.'/'.$name,$uid);chgrp($recovery.'/'.$name,$gid);}
echo "Private local storage is ready.\n";
