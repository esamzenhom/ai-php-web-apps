<?php
declare(strict_types=1);
require __DIR__.'/../admin/app/bootstrap.php';
use App\Admin\{Backup,Changes};
set_error_handler(static function($level,$message,$file,$line){throw new ErrorException($message,0,$level,$file,$line);});
// Called only after reset.sh confirms, backs up and stops all services.
$dir=BASE_PATH.'/backups/reset-'.date('Ymd-His').'-'.bin2hex(random_bytes(3));
mkdir($dir,0700,true);$temp=Backup::temporary();
try {
    Changes::command(['tar','-cf',$temp,'--exclude=./recovery','--exclude=./control/backup-work','-C',BASE_PATH.'/data','.']);
    Backup::encryptFile($temp,$dir.'/data.tar.enc');
    Backup::encryptFile(BASE_PATH.'/.env',$dir.'/environment.enc');
} finally { if(is_file($temp))unlink($temp); }
// Leave the verified archive readable by the owner on Linux as well as Docker Desktop.
$uid=fileowner(BASE_PATH.'/data/recovery/keypair');$gid=filegroup(BASE_PATH.'/data/recovery/keypair');
foreach([$dir,$dir.'/data.tar.enc',$dir.'/environment.enc'] as $path){chown($path,$uid);chgrp($path,$gid);}
// Only remove state after both archives have passed authenticated round-trip verification.
foreach(iterator_to_array(new FilesystemIterator(BASE_PATH.'/data',FilesystemIterator::SKIP_DOTS)) as $entry) {
    if($entry->getFilename()==='recovery')continue;
    if($entry->isLink())throw new RuntimeException('Unexpected storage symlink; reset stopped.');
    if($entry->isDir()) {
        $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($entry->getPathname(),FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach(iterator_to_array($items) as $item) {
            if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());
        }
        rmdir($entry->getPathname());
    } else unlink($entry->getPathname());
}
echo 'Previous state saved in encrypted '.basename($dir).". The separate recovery key was preserved in data/recovery.\n";
